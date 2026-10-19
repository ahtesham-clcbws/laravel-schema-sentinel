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

        // 12-Step Lifecycle: Introduce drift, fix, migrate, verify, rollback
        $diffEngine = new \Sentinel\SchemaSentinel\Core\DiffEngine();
        $diff = $diffEngine->compare([
            'my_test_posts' => $postTable,
        ], [
            'my_test_posts' => new \Sentinel\SchemaSentinel\DTOs\TableDefinition(
                name: 'my_test_posts',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'my_mig_') . '.php';
        file_put_contents($tempFile, $fixCode);
        exec("php -l " . escapeshellarg($tempFile), $lOutput, $lExit);
        $this->assertSame(0, $lExit);

        // Apply generated fix
        $migration = require $tempFile;
        $migration->up();
        $this->assertTrue(Schema::hasColumn('my_test_posts', 'views_count'));

        // Roll back generated fix
        $migration->down();
        $this->assertFalse(Schema::hasColumn('my_test_posts', 'views_count'));
        @unlink($tempFile);

        // 5. Active Transaction Safety Verification on MySQL
        DB::beginTransaction();
        DB::table('my_test_users')->insert(['email' => 'active_tx_my@example.com', 'is_active' => true]);
        $this->assertSame(1, DB::transactionLevel());

        $runner = new \Sentinel\SchemaSentinel\Core\ShadowMigrationRunner();
        $txBlocked = false;
        try {
            $runner->run();
        } catch (\RuntimeException $e) {
            $txBlocked = true;
            $this->assertStringContainsString('Active transaction detected on connection', $e->getMessage());
            $this->assertStringContainsString('mysql', $e->getMessage());
        }
        $this->assertTrue($txBlocked, 'Expected Sentinel to block simulation during active MySQL transaction');

        // Confirm active MySQL transaction remains intact
        $this->assertSame(1, DB::transactionLevel());
        $this->assertSame(1, DB::table('my_test_users')->where('email', 'active_tx_my@example.com')->count());
        DB::rollBack();
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('my_test_users')->where('email', 'active_tx_my@example.com')->count());

        // 6. Cleanup
        Schema::dropIfExists('my_test_posts');
        Schema::dropIfExists('my_test_users');
    }
}
