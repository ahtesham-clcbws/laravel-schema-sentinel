<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Sentinel\SchemaSentinel\Facades\Sentinel;
use Sentinel\SchemaSentinel\Tests\TestCase;

class PostgresIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('DB_DRIVER') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL integration test requires DB_DRIVER=pgsql.');
        }

        Config::set('schema-sentinel.shadow_connection', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '5432'),
            'database' => 'sentinel_pg_shadow',
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
            'schema' => 'public',
        ]);
    }

    public function test_it_detects_drift_and_standardizes_indexes_on_postgresql(): void
    {
        // 1. Setup live schema in PostgreSQL
        Schema::dropIfExists('pg_test_posts');
        Schema::dropIfExists('pg_test_users');

        Schema::create('pg_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pg_test_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('pg_test_users')->onDelete('cascade');
            $table->index(['user_id', 'title'], 'custom_compound_idx');
        });

        // 2. Parse live schema using Sentinel
        $schema = Sentinel::parse();
        $this->assertArrayHasKey('pg_test_users', $schema);
        $this->assertArrayHasKey('pg_test_posts', $schema);

        // 3. Verify Foreign key and Index parsed correctly on PostgreSQL
        $postTable = $schema['pg_test_posts'];
        $this->assertNotEmpty($postTable->foreignKeys);
        $this->assertNotEmpty($postTable->indexes);

        // 4. Test index standardization analyzer on PostgreSQL
        $standardized = Sentinel::standardizeIndexes();
        $this->assertIsArray($standardized['deviations']);

        // 12-Step Lifecycle: Introduce drift, fix, migrate, verify, rollback
        $diffEngine = new \Sentinel\SchemaSentinel\Core\DiffEngine();
        $diff = $diffEngine->compare([
            'pg_test_posts' => $postTable,
        ], [
            'pg_test_posts' => new \Sentinel\SchemaSentinel\DTOs\TableDefinition(
                name: 'pg_test_posts',
                columns: array_merge($postTable->columns, [
                    'views_count' => new \Sentinel\SchemaSentinel\DTOs\ColumnDefinition('views_count', 'integer', false, 0, null, true),
                ]),
                indexes: $postTable->indexes,
                foreignKeys: $postTable->foreignKeys,
            ),
        ]);

        $this->assertNotEmpty($diff->missingColumns);

        $generator = new \Sentinel\SchemaSentinel\Core\MigrationGenerator();
        $fixCode = $generator->buildMigrationCode($diff, false);
        $this->assertStringContainsString("\$table->integer('views_count')", $fixCode);

        // Verify PHP Syntax
        $tempFile = tempnam(sys_get_temp_dir(), 'pg_mig_') . '.php';
        file_put_contents($tempFile, $fixCode);
        exec("php -l " . escapeshellarg($tempFile), $lOutput, $lExit);
        $this->assertSame(0, $lExit);

        // Apply generated fix
        $migration = require $tempFile;
        $migration->up();
        $this->assertTrue(Schema::hasColumn('pg_test_posts', 'views_count'));

        // Roll back generated fix
        $migration->down();
        $this->assertFalse(Schema::hasColumn('pg_test_posts', 'views_count'));
        @unlink($tempFile);

        // 5. Cleanup
        Schema::dropIfExists('pg_test_posts');
        Schema::dropIfExists('pg_test_users');
    }
}
