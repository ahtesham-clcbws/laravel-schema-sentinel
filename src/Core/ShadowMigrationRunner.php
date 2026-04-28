<?php

namespace Sentinel\SchemaSentinel\Core;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Connection;

/**
 * Executes migrations against a temporary, in-memory database.
 */
class ShadowMigrationRunner
{
    private const CONNECTION_NAME = 'sentinel_shadow';

    /**
     * @var array<string, float>
     */
    protected array $migrationTimes = [];

    /**
     * Run all migrations on a temporary in-memory database.
     */
    public function run(?string $tag = null, bool $testRollback = false): Connection
    {
        $this->migrationTimes = [];
        $this->setupShadowConnection();

        $shadowConfig = Config::get('database.connections.' . self::CONNECTION_NAME);
        $originalConnections = Config::get('database.connections');
        $originalDefault = Config::get('database.default');

        try {
            // Total Isolation: Redirect ALL configured connections to the shadow DB.
            foreach (array_keys($originalConnections) as $name) {
                if ($name === self::CONNECTION_NAME) {
                    continue;
                }
                Config::set("database.connections.{$name}", $shadowConfig);
                DB::purge($name);
            }

            Config::set('database.default', self::CONNECTION_NAME);
            DB::setDefaultConnection(self::CONNECTION_NAME);
            
            $paths = Config::get('schema-sentinel.migration_paths', [\Illuminate\Support\Facades\App::databasePath('migrations')]);
            $skipMigrations = Config::get('schema-sentinel.skip_migrations', []);

            foreach ($paths as $path) {
                $runPath = $this->prepareMigrationPath($path, $skipMigrations, $tag);
                
                $files = array_diff(scandir($runPath), ['.', '..']);
                foreach ($files as $file) {
                    $start = microtime(true);
                    
                    Artisan::call('migrate', [
                        '--database' => self::CONNECTION_NAME,
                        '--path' => $runPath . '/' . $file,
                        '--force' => true,
                    ]);

                    $this->migrationTimes[$file] = microtime(true) - $start;

                    if ($testRollback) {
                        Artisan::call('migrate:rollback', [
                            '--database' => self::CONNECTION_NAME,
                            '--path' => $runPath . '/' . $file,
                            '--force' => true,
                        ]);
                        
                        Artisan::call('migrate', [
                            '--database' => self::CONNECTION_NAME,
                            '--path' => $runPath . '/' . $file,
                            '--force' => true,
                        ]);
                    }
                }

                $this->cleanupTemporaryPath($runPath);
            }
        } finally {
            // Restore original connections
            foreach ($originalConnections as $name => $config) {
                Config::set("database.connections.{$name}", $config);
                DB::purge($name);
            }
            Config::set('database.default', $originalDefault);
            DB::setDefaultConnection($originalDefault);
        }

        return DB::connection(self::CONNECTION_NAME);
    }

    public function getMigrationTimes(): array
    {
        return $this->migrationTimes;
    }

    protected function setupShadowConnection(): void
    {
        $config = Config::get('schema-sentinel.shadow_connection', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            'foreign_key_constraints' => false,
        ]);

        Config::set('database.connections.' . self::CONNECTION_NAME, $config);

        DB::purge(self::CONNECTION_NAME);
    }

    /**
     * Prepare a temporary migration path to handle skipped migrations and tags.
     */
    protected function prepareMigrationPath(string $originalPath, array $skipMigrations, ?string $tag = null): string
    {
        $absolutePath = base_path($originalPath);
        
        if (!is_dir($absolutePath)) {
            return $originalPath; // Fallback to original if not a directory
        }

        // If no skip and no tag, use the original path directly
        if (empty($skipMigrations) && empty($tag)) {
            return $originalPath;
        }

        $tempPath = storage_path('framework/sentinel_migrations_' . uniqid());
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $files = scandir($absolutePath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $absolutePath . '/' . $file;

            if (in_array($file, $skipMigrations)) {
                continue; // Skip this migration
            }

            if ($tag && !$this->fileHasTag($filePath, $tag)) {
                continue; // Skip if it doesn't have the required tag
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
        // Supports @sentinel-tag name or @sentinel-tag:name
        return (bool) preg_match("/@sentinel-tag[:\s]+{$tag}/i", $content);
    }

    /**
     * Cleanup the temporary migration path.
     */
    protected function cleanupTemporaryPath(string $path): void
    {
        if (str_contains($path, 'sentinel_migrations_') && is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                unlink($path . '/' . $file);
            }
            rmdir($path);
        }
    }

    public function cleanup(): void
    {
        DB::disconnect(self::CONNECTION_NAME);
        Config::set('database.connections.' . self::CONNECTION_NAME, null);
    }
}
