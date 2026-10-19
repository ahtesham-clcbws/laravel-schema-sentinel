<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    public function test_it_rejects_literal_connection_calls_in_migrations(): void
    {
        $tempDir = storage_path('framework/testing/test_literal_conn_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_literal_connection.php';
        file_put_contents($migrationFile, "<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::connection('production')->create('leaked_users', function (\$table) { \$table->id(); });
    }
};");

        Config::set('schema-sentinel.migration_paths', [$tempDir]);
        $runner = new ShadowMigrationRunner();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("explicitly invokes connection('production')");
            $runner->run();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_rejects_dynamic_variable_connection_calls_in_migrations(): void
    {
        $tempDir = storage_path('framework/testing/test_dyn_conn_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_dyn_connection.php';
        file_put_contents($migrationFile, "<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;
return new class extends Migration {
    public function up(): void {
        \$target = 'live_db';
        DB::connection(\$target)->select('SELECT 1');
    }
};");

        Config::set('schema-sentinel.migration_paths', [$tempDir]);
        $runner = new ShadowMigrationRunner();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('explicitly invokes connection(dynamic expression)');
            $runner->run();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_rejects_model_with_connection_property_in_migrations(): void
    {
        $tempDir = storage_path('framework/testing/test_model_conn_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_model_connection.php';
        file_put_contents($migrationFile, "<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Eloquent\\Model;
class TenantUser extends Model {
    protected \$connection = 'tenant_mysql';
}
return new class extends Migration {
    public function up(): void {
        TenantUser::create(['name' => 'admin']);
    }
};");

        Config::set('schema-sentinel.migration_paths', [$tempDir]);
        $runner = new ShadowMigrationRunner();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('defines a class property [$connection]');
            $runner->run();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_detects_collision_via_connection_urls(): void
    {
        Config::set('database.connections.live_remote', [
            'url' => 'mysql://app_user:secret@127.0.0.1:3306/production_corp_db',
        ]);

        Config::set('schema-sentinel.shadow_connection', [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'production_corp_db',
        ]);

        $runner = new ShadowMigrationRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("collides with live connection 'live_remote'");

        $runner->run();
    }

    public function test_it_cloaks_and_intercepts_dynamically_resolved_connections_during_simulation(): void
    {
        // Configure a mock live connection that would otherwise be reachable
        Config::set('database.connections.remote_warehouse', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $tempDir = storage_path('framework/testing/test_cloak_conn_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_cloak_connection.php';
        // Simulates indirect execution (e.g. third-party repository/helper accessing live connection without literal token)
        file_put_contents($migrationFile, "<?php
use Illuminate\\Database\\Migrations\\Migration;
return new class extends Migration {
    public function up(): void {
        \$db = app('db');
        \$connName = base64_decode('cmVtb3RlX3dhcmVob3VzZQ=='); // 'remote_warehouse'
        \$fn = 'connec' . 'tion';
        \$conn = \$db->\$fn(\$connName);
        \$conn->select('SELECT 1');
    }
};");

        Config::set('schema-sentinel.migration_paths', [$tempDir]);
        $runner = new ShadowMigrationRunner();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("CRITICAL DATABASE SAFETY VIOLATION: Migration attempted executing query on non-shadow connection ['remote_warehouse']");
            $runner->run();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_rejects_direct_pdo_instantiations_in_migrations(): void
    {
        $tempDir = storage_path('framework/testing/test_pdo_conn_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_pdo_connection.php';
        file_put_contents($migrationFile, "<?php
use Illuminate\\Database\\Migrations\\Migration;
return new class extends Migration {
    public function up(): void {
        \$pdo = new \\PDO('sqlite::memory:');
    }
};");

        Config::set('schema-sentinel.migration_paths', [$tempDir]);
        $runner = new ShadowMigrationRunner();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('instantiates a direct PDO object');
            $runner->run();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_rejects_process_execution_calls_in_migrations(): void
    {
        $tempDir = storage_path('framework/testing/test_exec_conn_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_exec_connection.php';
        file_put_contents($migrationFile, "<?php
use Illuminate\\Database\\Migrations\\Migration;
return new class extends Migration {
    public function up(): void {
        exec('whoami');
    }
};");

        Config::set('schema-sentinel.migration_paths', [$tempDir]);
        $runner = new ShadowMigrationRunner();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('invokes external process execution function [exec()]');
            $runner->run();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_rejects_shadow_simulation_when_active_transaction_exists_and_preserves_transaction(): void
    {
        // 1. Set up an active live connection with a table and open transaction
        $conn = DB::connection('testing');
        $conn->statement('CREATE TABLE test_tx_preservation (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->beginTransaction();
        $conn->statement("INSERT INTO test_tx_preservation (title) VALUES ('before_sentinel')");

        $this->assertSame(1, $conn->transactionLevel());

        $runner = new ShadowMigrationRunner();

        // 2. Attempting to run shadow simulation must throw RuntimeException aborting before purging/disconnecting
        $exceptionCaught = false;
        try {
            $runner->run();
        } catch (\RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('Active transaction detected on connection', $e->getMessage());
            $this->assertStringContainsString('testing', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Expected RuntimeException when running Sentinel during active transaction');

        // 3. Verify the transaction and connection state remained intact
        $this->assertSame(1, $conn->transactionLevel());
        $count = $conn->table('test_tx_preservation')->count();
        $this->assertSame(1, $count);

        // 4. Commit the transaction and verify persistence
        $conn->commit();
        $this->assertSame(0, $conn->transactionLevel());
        $this->assertSame(1, $conn->table('test_tx_preservation')->count());

        // Cleanup
        $conn->statement('DROP TABLE test_tx_preservation');
    }
}
