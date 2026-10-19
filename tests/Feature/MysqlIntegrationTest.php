<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Sentinel\SchemaSentinel\Facades\Sentinel;
use Sentinel\SchemaSentinel\Tests\TestCase;

class MysqlIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('DB_DRIVER') !== 'mysql') {
            $this->markTestSkipped('MySQL integration test requires DB_DRIVER=mysql.');
        }

        Config::set('schema-sentinel.shadow_connection', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => 'sentinel_my_shadow',
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'password'),
            'prefix' => '',
        ]);
    }

    public function test_it_detects_drift_and_standardizes_indexes_on_mysql(): void
    {
        Schema::dropIfExists('my_test_posts');
        Schema::dropIfExists('my_test_users');

        Schema::create('my_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('my_test_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('my_test_users')->onDelete('cascade');
            $table->index(['user_id', 'title'], 'custom_compound_my_idx');
        });

        $schema = Sentinel::parse();
        $this->assertArrayHasKey('my_test_users', $schema);
        $this->assertArrayHasKey('my_test_posts', $schema);

        $postTable = $schema['my_test_posts'];
        $this->assertNotEmpty($postTable->foreignKeys);
        $this->assertNotEmpty($postTable->indexes);

        $standardized = Sentinel::standardizeIndexes();
        $this->assertIsArray($standardized['deviations']);

        Schema::dropIfExists('my_test_posts');
        Schema::dropIfExists('my_test_users');
    }
}
