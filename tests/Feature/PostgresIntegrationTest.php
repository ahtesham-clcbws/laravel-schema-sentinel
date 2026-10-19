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

        // 5. Cleanup
        Schema::dropIfExists('pg_test_posts');
        Schema::dropIfExists('pg_test_users');
    }
}
