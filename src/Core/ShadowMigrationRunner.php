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
            // Guard against recursion
            PreMigrationGuard::bypass(true);

            // Temporarily set default connection to sentinel_shadow to ensure any
            // unqualified DB queries in migrations target shadow, but DO NOT mutate other connection configs.
            Config::set('database.default', self::CONNECTION_NAME);
            DB::setDefaultConnection(self::CONNECTION_NAME);

            $paths = Config::get('schema-sentinel.migration_paths', [\Illuminate\Support\Facades\App::databasePath('migrations')]);
            $skipMigrations = Config::get('schema-sentinel.skip_migrations', []);

            foreach ($paths as $path) {
                $runPath = $this->prepareMigrationPath($path, $skipMigrations, $tag);
                if ($runPath !== $path && $runPath !== base_path($path)) {
                    $createdPaths[] = $runPath;
                }

                if (!is_dir($runPath)) {
                    continue;
                }

                $files = array_diff(scandir($runPath) ?: [], ['.', '..']);
                sort($files);

                foreach ($files as $file) {
                    if (!str_ends_with($file, '.php')) {
                        continue;
                    }

                    $filePath = rtrim($runPath, '/') . '/' . $file;
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

            PreMigrationGuard::bypass(false);
        }

        return DB::connection(self::CONNECTION_NAME);
    }

    public function getMigrationTimes(): array
    {
        return $this->migrationTimes;
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

        if ($driver === 'sqlite') {
            $db = $shadowConfig['database'] ?? ':memory:';
            // Allow :memory: or files located in system temp / storage
            return;
        }

        // For client-server DBs (mysql, pgsql, etc.), verify it does not collide with the live default connection
        $defaultConnName = Config::get('database.default');
        $defaultConfig = Config::get("database.connections.{$defaultConnName}", []);

        if (
            ($shadowConfig['host'] ?? '') === ($defaultConfig['host'] ?? null) &&
            ($shadowConfig['database'] ?? '') === ($defaultConfig['database'] ?? null) &&
            !empty($shadowConfig['database'])
        ) {
            throw new RuntimeException(
                "CRITICAL DATABASE SAFETY ERROR: 'schema-sentinel.shadow_connection' is configured with the same host and database as your live '{$defaultConnName}' connection [{$shadowConfig['database']}]. Shadow simulations must NEVER target a live database."
            );
        }
    }

    /**
     * Prepare a temporary migration path to handle skipped migrations and tags.
     */
    protected function prepareMigrationPath(string $originalPath, array $skipMigrations, ?string $tag = null): string
    {
        $absolutePath = str_starts_with($originalPath, '/') ? $originalPath : base_path($originalPath);

        if (!is_dir($absolutePath)) {
            return $originalPath;
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
