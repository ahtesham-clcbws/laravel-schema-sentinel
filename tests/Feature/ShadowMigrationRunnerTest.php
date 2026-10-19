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
}
