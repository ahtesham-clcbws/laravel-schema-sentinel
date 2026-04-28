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
     * Run all migrations on a temporary in-memory database.
     */
    public function run(): Connection
    {
        $this->setupShadowConnection();

        $shadowConfig = Config::get('database.connections.' . self::CONNECTION_NAME);
        $originalConnections = Config::get('database.connections');
        $originalDefault = Config::get('database.default');

        try {
            // Total Isolation: Redirect ALL configured connections to the shadow DB.
            // This prevents migrations that hardcode a connection (like Passport) 
            // from accidentally hitting the live database.
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
                $runPath = $this->prepareMigrationPath($path, $skipMigrations);
                
                Artisan::call('migrate', [
                    '--database' => self::CONNECTION_NAME,
                    '--path' => $runPath,
                    '--force' => true,
                ]);

                $this->cleanupTemporaryPath($runPath);
            }
        } finally {
            // Restore original connection configurations
            foreach ($originalConnections as $name => $config) {
                Config::set("database.connections.{$name}", $config);
                DB::purge($name);
            }
            Config::set('database.default', $originalDefault);
            DB::setDefaultConnection($originalDefault);
        }

        return DB::connection(self::CONNECTION_NAME);
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
     * Prepare a temporary migration path to handle skipped migrations.
     */
    protected function prepareMigrationPath(string $originalPath, array $skipMigrations): string
    {
        $absolutePath = base_path($originalPath);
        
        if (!is_dir($absolutePath)) {
            return $originalPath; // Fallback to original if not a directory
        }

        if (empty($skipMigrations)) {
            return $originalPath;
        }

        $tempPath = storage_path('framework/sentinel_migrations_' . uniqid());
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $files = scandir($absolutePath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            if (in_array($file, $skipMigrations)) {
                continue; // Skip this migration
            }

            copy($absolutePath . '/' . $file, $tempPath . '/' . $file);
        }

        return $tempPath;
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
