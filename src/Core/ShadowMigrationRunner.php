<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Core;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Connection;
use RuntimeException;
use Sentinel\SchemaSentinel\Support\PreMigrationGuard;

/**
 * Executes migrations against a dedicated, isolated shadow database.
 */
class ShadowMigrationRunner
{
    public const CONNECTION_NAME = 'sentinel_shadow';

    /**
     * @var array<string, float>
     */
    protected array $migrationTimes = [];

    /**
     * Run migrations on the isolated shadow connection.
     */
    public function run(?string $tag = null, bool $testRollback = false): Connection
    {
        $this->migrationTimes = [];
        $this->setupShadowConnection();

        $originalDefault = Config::get('database.default');
        $createdPaths = [];

        try {
            // Guard against recursion while tracking previous bypass state
            $previousBypass = PreMigrationGuard::isBypassed();
            $attachedInterceptors = [];
            $interceptorClosure = function ($query, $bindings, $connection) {
                $name = $connection->getName();
                if ($name !== self::CONNECTION_NAME) {
                    throw new RuntimeException(
                        "CRITICAL DATABASE SAFETY VIOLATION: Migration attempted executing query on live/non-shadow connection ['{$name}'] during shadow simulation! Query: [{$query}]."
                    );
                }
            };

            // Attach runtime safety interceptor to all currently resolved database connections
            $dbManager = DB::getFacadeRoot();
            if ($dbManager instanceof \Illuminate\Database\DatabaseManager) {
                foreach ($dbManager->getConnections() as $name => $activeConn) {
                    if ($name !== self::CONNECTION_NAME && $activeConn instanceof \Illuminate\Database\Connection) {
                        $activeConn->beforeExecuting($interceptorClosure);
                        $attachedInterceptors[$name] = $activeConn;
                    }
                }
            }

            PreMigrationGuard::bypass(true);

            // Temporarily set default connection to sentinel_shadow to ensure any
            // unqualified DB queries in migrations target shadow.
            Config::set('database.default', self::CONNECTION_NAME);
            DB::setDefaultConnection(self::CONNECTION_NAME);

            $paths = Config::get('schema-sentinel.migration_paths', [\Illuminate\Support\Facades\App::databasePath('migrations')]);
            $skipMigrations = Config::get('schema-sentinel.skip_migrations', []);

            $totalFilesMigrated = 0;

            foreach ($paths as $path) {
                $runPath = $this->prepareMigrationPath($path, $skipMigrations, $tag);
                if ($runPath !== $path && $runPath !== base_path($path)) {
                    $createdPaths[] = $runPath;
                }

                $files = array_diff(scandir($runPath) ?: [], ['.', '..']);
                sort($files);

                foreach ($files as $file) {
                    if (!str_ends_with($file, '.php')) {
                        continue;
                    }

                    $filePath = rtrim($runPath, '/') . '/' . $file;
                    $this->validateMigrationConnectionSafety($filePath);

                    // Re-scan active connections in case new ones were resolved
                    if ($dbManager instanceof \Illuminate\Database\DatabaseManager) {
                        foreach ($dbManager->getConnections() as $name => $activeConn) {
                            if ($name !== self::CONNECTION_NAME && !isset($attachedInterceptors[$name]) && $activeConn instanceof \Illuminate\Database\Connection) {
                                $activeConn->beforeExecuting($interceptorClosure);
                                $attachedInterceptors[$name] = $activeConn;
                            }
                        }
                    }

                    $start = microtime(true);

                    $exitCode = Artisan::call('migrate', [
                        '--database' => self::CONNECTION_NAME,
                        '--path' => $filePath,
                        '--realpath' => true,
                        '--force' => true,
                    ]);

                    if ($exitCode !== 0) {
                        $output = trim(Artisan::output());
                        throw new RuntimeException("Migration failed during shadow simulation on [{$file}]: {$output}");
                    }

                    $this->migrationTimes[$file] = microtime(true) - $start;
                    $totalFilesMigrated++;

                    if ($testRollback) {
                        $rbExit = Artisan::call('migrate:rollback', [
                            '--database' => self::CONNECTION_NAME,
                            '--path' => $filePath,
                            '--realpath' => true,
                            '--force' => true,
                        ]);

                        if ($rbExit !== 0) {
                            $rbOut = trim(Artisan::output());
                            throw new RuntimeException("Rollback failed during shadow simulation on [{$file}]: {$rbOut}");
                        }

                        $reMigrateExit = Artisan::call('migrate', [
                            '--database' => self::CONNECTION_NAME,
                            '--path' => $filePath,
                            '--realpath' => true,
                            '--force' => true,
                        ]);

                        if ($reMigrateExit !== 0) {
                            $reOut = trim(Artisan::output());
                            throw new RuntimeException("Re-applying migration failed after rollback on [{$file}]: {$reOut}");
                        }
                    }
                }
            }
        } finally {
            // Detach runtime query interceptors
            foreach ($attachedInterceptors as $conn) {
                try {
                    $ref = new \ReflectionProperty(\Illuminate\Database\Connection::class, 'beforeExecutingCallbacks');
                    $ref->setAccessible(true);
                    $cbs = $ref->getValue($conn);
                    if (is_array($cbs)) {
                        $cbs = array_values(array_filter($cbs, fn($cb) => $cb !== $interceptorClosure));
                        $ref->setValue($conn, $cbs);
                    }
                } catch (\Throwable) {
                    // Ignore reflection teardown issues
                }
            }

            // Restore default connection
            Config::set('database.default', $originalDefault);
            if ($originalDefault) {
                DB::setDefaultConnection($originalDefault);
            }

            // Always clean up any generated temporary migration directories
            foreach ($createdPaths as $tempDir) {
                $this->cleanupTemporaryPath($tempDir);
            }

            // Restore previous bypass state instead of blindly setting false
            PreMigrationGuard::bypass($previousBypass ?? false);
        }

        return DB::connection(self::CONNECTION_NAME);
    }

    public function getMigrationTimes(): array
    {
        return $this->migrationTimes;
    }

    /**
     * Inspect migration file source using AST tokens to detect dangerous explicit connection overrides.
     */
    protected function validateMigrationConnectionSafety(string $filePath): void
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $tokens = token_get_all($code);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            // 1. Detect calls like DB::connection(...), Schema::connection(...), $schema->connection(...)
            if (is_array($t) && $t[0] === T_STRING && strtolower($t[1]) === 'connection') {
                $p = $i - 1;
                while ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                    $p--;
                }
                if ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
                    // Inspect parameter passed inside connection(...)
                    $argToken = $this->getFirstArgumentToken($tokens, $i + 1, $count);
                    $literalName = null;
                    if ($argToken !== null && is_array($argToken) && $argToken[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $literalName = trim($argToken[1], "'\"");
                    }

                    if ($literalName !== self::CONNECTION_NAME) {
                        $desc = $literalName !== null ? "'{$literalName}'" : "dynamic expression";
                        throw new RuntimeException(
                            "CRITICAL DATABASE SAFETY ERROR: Migration [{$filePath}] explicitly invokes connection({$desc}). Shadow simulation cannot safely isolate explicit connection calls without risking mutations to live databases."
                        );
                    }
                }
            }

            // 2. Detect models or classes defining protected/public $connection = '...';
            if (is_array($t) && $t[0] === T_VARIABLE && $t[1] === '$connection') {
                $p = $i - 1;
                while ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                    $p--;
                }
                if ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_PROTECTED, T_PUBLIC, T_PRIVATE, T_VAR])) {
                    throw new RuntimeException(
                        "CRITICAL DATABASE SAFETY ERROR: Migration [{$filePath}] defines a class property [\$connection]. Explicit model/class connection overrides cannot be safely redirected during shadow simulation."
                    );
                }
            }
        }
    }

    /**
     * Find the first non-whitespace/comment token inside parentheses.
     */
    protected function getFirstArgumentToken(array $tokens, int $startIndex, int $totalCount): ?array
    {
        $openParenFound = false;
        for ($i = $startIndex; $i < $totalCount; $i++) {
            $t = $tokens[$i];
            if ($t === '(') {
                $openParenFound = true;
                continue;
            }
            if ($openParenFound) {
                if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                    continue;
                }
                return is_array($t) ? $t : null;
            }
        }
        return null;
    }

    /**
     * Set up and validate the shadow connection configuration.
     */
    protected function setupShadowConnection(): void
    {
        $config = Config::get('schema-sentinel.shadow_connection', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            'foreign_key_constraints' => false,
        ]);

        $this->validateShadowSafety($config);

        Config::set('database.connections.' . self::CONNECTION_NAME, $config);
        DB::purge(self::CONNECTION_NAME);
    }

    /**
     * Prevent shadow simulation from pointing to live or production database configurations.
     */
    protected function validateShadowSafety(array $shadowConfig): void
    {
        $resolvedShadow = $this->resolveConnectionConfig($shadowConfig);
        $driver = $resolvedShadow['driver'] ?? 'sqlite';

        // Check against ALL configured connections in application
        $configuredConnections = Config::get('database.connections', []);

        if ($driver === 'sqlite') {
            $db = (string) ($resolvedShadow['database'] ?? ':memory:');

            // Reject unsafe SQLite URI configurations (e.g. file:... mode=... or query strings)
            if (str_starts_with($db, 'file:') || str_contains($db, '?') || str_contains($db, '#')) {
                throw new RuntimeException(
                    "CRITICAL DATABASE SAFETY ERROR: Unsafe SQLite database URI detected in shadow connection [{$db}]. SQLite shadow must use ':memory:' or an explicit filesystem path."
                );
            }

            if ($db === ':memory:' || $db === '') {
                return;
            }

            // Resolve real filesystem paths for SQLite file
            $shadowResolved = $this->resolvePath($db);

            foreach ($configuredConnections as $name => $conn) {
                if ($name === self::CONNECTION_NAME) {
                    continue;
                }
                $resolvedLive = $this->resolveConnectionConfig($conn);
                if (($resolvedLive['driver'] ?? '') === 'sqlite') {
                    $liveDb = (string) ($resolvedLive['database'] ?? '');
                    if ($liveDb !== '' && $liveDb !== ':memory:') {
                        $liveResolved = $this->resolvePath($liveDb);
                        if ($shadowResolved !== '' && $liveResolved !== '' && $shadowResolved === $liveResolved) {
                            throw new RuntimeException(
                                "CRITICAL DATABASE SAFETY ERROR: 'schema-sentinel.shadow_connection' SQLite file [{$db}] resolves to the same database file as your configured '{$name}' connection [{$liveDb}]. Shadow simulation must NEVER target a live database file."
                            );
                        }
                    }
                }
            }

            return;
        }

        // For client-server DBs (mysql, pgsql, etc.), verify against all live configured connections
        $shadowHost = strtolower((string) ($resolvedShadow['host'] ?? 'localhost'));
        $shadowPort = (string) ($resolvedShadow['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
        $shadowDatabase = (string) ($resolvedShadow['database'] ?? '');
        $shadowSocket = (string) ($resolvedShadow['unix_socket'] ?? '');

        if ($shadowDatabase === '') {
            throw new RuntimeException("CRITICAL DATABASE SAFETY ERROR: Shadow database name cannot be empty for driver [{$driver}].");
        }

        foreach ($configuredConnections as $name => $conn) {
            if ($name === self::CONNECTION_NAME) {
                continue;
            }

            $resolvedLive = $this->resolveConnectionConfig($conn);
            $liveDriver = $resolvedLive['driver'] ?? '';
            if ($liveDriver !== $driver) {
                continue;
            }

            $liveHost = strtolower((string) ($resolvedLive['host'] ?? 'localhost'));
            $livePort = (string) ($resolvedLive['port'] ?? ($liveDriver === 'pgsql' ? '5432' : '3306'));
            $liveDatabase = (string) ($resolvedLive['database'] ?? '');
            $liveSocket = (string) ($resolvedLive['unix_socket'] ?? '');

            // Normalize localhost representations
            $localhosts = ['127.0.0.1', 'localhost', '::1', '0.0.0.0'];
            $isSameHost = ($shadowHost === $liveHost) ||
                (in_array($shadowHost, $localhosts, true) && in_array($liveHost, $localhosts, true));

            $isSamePort = ($shadowPort === $livePort);
            $isSameDb = ($shadowDatabase === $liveDatabase);
            $isSameSocket = ($shadowSocket !== '' && $shadowSocket === $liveSocket);

            if ($isSameDb && ($isSameSocket || ($isSameHost && $isSamePort))) {
                throw new RuntimeException(
                    "CRITICAL DATABASE SAFETY ERROR: 'schema-sentinel.shadow_connection' [{$shadowDatabase}] collides with live connection '{$name}' [{$liveDatabase}] on host [{$shadowHost}:{$shadowPort}]. Shadow simulations must NEVER target a live database."
                );
            }
        }
    }

    /**
     * Resolve connection configuration, parsing connection URLs (e.g. mysql:// or postgres://) if present.
     */
    protected function resolveConnectionConfig(array $config): array
    {
        if (!empty($config['url'])) {
            $parser = new \Illuminate\Database\ConfigurationUrlParser();
            $parsed = $parser->parseConfiguration($config);
            return array_merge($config, $parsed);
        }
        return $config;
    }

    /**
     * Resolve a path canonicalized for comparison.
     */
    protected function resolvePath(string $path): string
    {
        $absolute = str_starts_with($path, '/') ? $path : base_path($path);
        $real = realpath($absolute);
        return $real !== false ? $real : $absolute;
    }

    /**
     * Prepare a temporary migration path to handle skipped migrations and tags.
     */
    protected function prepareMigrationPath(string $originalPath, array $skipMigrations, ?string $tag = null): string
    {
        $absolutePath = str_starts_with($originalPath, '/') ? $originalPath : base_path($originalPath);

        if (!file_exists($absolutePath) || !is_dir($absolutePath)) {
            throw new RuntimeException(
                "Configured migration path does not exist or is not a directory: [{$originalPath}] (resolved to [{$absolutePath}])."
            );
        }

        if (empty($skipMigrations) && empty($tag)) {
            return $absolutePath;
        }

        $tempPath = storage_path('framework/sentinel_migrations_' . uniqid());
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $files = scandir($absolutePath) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                continue;
            }

            $filePath = $absolutePath . '/' . $file;

            if (in_array($file, $skipMigrations, true)) {
                continue;
            }

            if ($tag && !$this->fileHasTag($filePath, $tag)) {
                continue;
            }

            copy($filePath, $tempPath . '/' . $file);
        }

        return $tempPath;
    }

    /**
     * Check if a migration file contains a specific tag.
     */
    protected function fileHasTag(string $path, string $tag): bool
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }
        return (bool) preg_match("/@sentinel-tag[:\s]+{$tag}/i", $content);
    }

    /**
     * Cleanup the temporary migration path.
     */
    public function cleanupTemporaryPath(string $path): void
    {
        if (str_contains($path, 'sentinel_migrations_') && is_dir($path)) {
            $files = array_diff(scandir($path) ?: [], ['.', '..']);
            foreach ($files as $file) {
                @unlink($path . '/' . $file);
            }
            @rmdir($path);
        }
    }

    /**
     * Clean up shadow database connection and purge resources.
     */
    public function cleanup(): void
    {
        try {
            DB::disconnect(self::CONNECTION_NAME);
            DB::purge(self::CONNECTION_NAME);
        } catch (\Throwable) {
            // Ignore disconnect errors during teardown
        }

        $connections = Config::get('database.connections', []);
        unset($connections[self::CONNECTION_NAME]);
        Config::set('database.connections', $connections);
    }
}
