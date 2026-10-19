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
     * Inspect migration file source to detect dangerous explicit connection overrides.
     */
    protected function validateMigrationConnectionSafety(string $filePath): void
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        // Detect patterns like DB::connection('mysql') or Schema::connection('production')
        if (preg_match("/(?:DB|Schema)::connection\(\s*['\"]([^'\"]+)['\"]\s*\)/i", $code, $matches)) {
            $connName = $matches[1];
            if ($connName !== self::CONNECTION_NAME) {
                throw new RuntimeException(
                    "CRITICAL DATABASE SAFETY ERROR: Migration [{$filePath}] explicitly specifies database connection ['{$connName}']. Shadow simulation cannot safely isolate explicit connection calls without risking mutations to live databases."
                );
            }
        }
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
        $driver = $shadowConfig['driver'] ?? 'sqlite';

        // Check against ALL configured connections in application
        $configuredConnections = Config::get('database.connections', []);

        if ($driver === 'sqlite') {
            $db = (string) ($shadowConfig['database'] ?? ':memory:');

            // Reject unsafe SQLite URI configurations (e.g. file:... mode=...)
            if (str_starts_with($db, 'file:') || str_contains($db, '?')) {
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
                if (($conn['driver'] ?? '') === 'sqlite') {
                    $liveDb = (string) ($conn['database'] ?? '');
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
        $shadowHost = strtolower((string) ($shadowConfig['host'] ?? 'localhost'));
        $shadowPort = (string) ($shadowConfig['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
        $shadowDatabase = (string) ($shadowConfig['database'] ?? '');
        $shadowSocket = (string) ($shadowConfig['unix_socket'] ?? '');

        if ($shadowDatabase === '') {
            throw new RuntimeException("CRITICAL DATABASE SAFETY ERROR: Shadow database name cannot be empty for driver [{$driver}].");
        }

        foreach ($configuredConnections as $name => $conn) {
            if ($name === self::CONNECTION_NAME) {
                continue;
            }

            $liveDriver = $conn['driver'] ?? '';
            if ($liveDriver !== $driver) {
                continue;
            }

            $liveHost = strtolower((string) ($conn['host'] ?? 'localhost'));
            $livePort = (string) ($conn['port'] ?? ($liveDriver === 'pgsql' ? '5432' : '3306'));
            $liveDatabase = (string) ($conn['database'] ?? '');
            $liveSocket = (string) ($conn['unix_socket'] ?? '');

            // Normalize localhost variants
            $isSameHost = ($shadowHost === $liveHost) ||
                (in_array($shadowHost, ['127.0.0.1', 'localhost']) && in_array($liveHost, ['127.0.0.1', 'localhost']));

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
