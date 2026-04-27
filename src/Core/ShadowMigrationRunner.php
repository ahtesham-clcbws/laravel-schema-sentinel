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

        $paths = \Illuminate\Support\Facades\Config::get('schema-sentinel.migration_paths', [\Illuminate\Support\Facades\App::databasePath('migrations')]);

        foreach ($paths as $path) {
            Artisan::call('migrate', [
                '--database' => self::CONNECTION_NAME,
                '--path' => $path,
                '--force' => true,
            ]);
        }

        return DB::connection(self::CONNECTION_NAME);
    }

    protected function setupShadowConnection(): void
    {
        $config = \Illuminate\Support\Facades\Config::get('schema-sentinel.shadow_connection', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            'foreign_key_constraints' => false,
        ]);

        Config::set('database.connections.' . self::CONNECTION_NAME, $config);

        DB::purge(self::CONNECTION_NAME);
    }

    public function cleanup(): void
    {
        DB::disconnect(self::CONNECTION_NAME);
        Config::set('database.connections.' . self::CONNECTION_NAME, null);
    }
}
