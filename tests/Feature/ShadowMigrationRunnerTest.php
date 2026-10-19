<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Sentinel\SchemaSentinel\Core\ShadowMigrationRunner;
use Sentinel\SchemaSentinel\Tests\TestCase;

class ShadowMigrationRunnerTest extends TestCase
{
    public function test_it_runs_shadow_migrations_in_isolation_without_corrupting_connections(): void
    {
        $runner = new ShadowMigrationRunner();

        $originalDefault = Config::get('database.default');
        $originalConnections = Config::get('database.connections');

        // Run shadow runner
        $shadowConn = $runner->run();

        $this->assertInstanceOf(\Illuminate\Database\Connection::class, $shadowConn);
        $this->assertSame('sentinel_shadow', $shadowConn->getName());

        // Assert original connections were NOT mutated or purged
        $this->assertSame($originalDefault, Config::get('database.default'));
        foreach ($originalConnections as $name => $cfg) {
            $this->assertEquals($cfg, Config::get("database.connections.{$name}"));
        }

        $runner->cleanup();
        $this->assertEquals($originalConnections, Config::get('database.connections'));
    }

    public function test_it_rejects_shadow_connection_targeting_live_database(): void
    {
        Config::set('database.default', 'mysql_live');
        Config::set('database.connections.mysql_live', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'production_app_db',
        ]);

        Config::set('schema-sentinel.shadow_connection', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'production_app_db',
        ]);

        $runner = new ShadowMigrationRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CRITICAL DATABASE SAFETY ERROR');

        $runner->run();
    }

    public function test_it_rejects_shadow_sqlite_targeting_same_live_file(): void
    {
        $liveFile = tempnam(sys_get_temp_dir(), 'live_db_');
        $symlinkFile = sys_get_temp_dir() . '/symlink_live_db_' . uniqid() . '.sqlite';
        symlink($liveFile, $symlinkFile);

        try {
            Config::set('database.default', 'sqlite_live');
            Config::set('database.connections.sqlite_live', [
                'driver' => 'sqlite',
                'database' => $liveFile,
            ]);

            Config::set('schema-sentinel.shadow_connection', [
                'driver' => 'sqlite',
                'database' => $symlinkFile,
            ]);

            $runner = new ShadowMigrationRunner();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('CRITICAL DATABASE SAFETY ERROR');

            $runner->run();
        } finally {
            @unlink($symlinkFile);
            @unlink($liveFile);
        }
    }

    public function test_it_rejects_unsafe_sqlite_uri(): void
    {
        Config::set('schema-sentinel.shadow_connection', [
            'driver' => 'sqlite',
            'database' => 'file:/var/data/app.db?mode=ro',
        ]);

        $runner = new ShadowMigrationRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe SQLite database URI detected');

        $runner->run();
    }

    public function test_it_rejects_missing_configured_migration_path(): void
    {
        Config::set('schema-sentinel.migration_paths', ['/path/that/does/not/exist/at/all']);

        $runner = new ShadowMigrationRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configured migration path does not exist');

        $runner->run();
    }
}
