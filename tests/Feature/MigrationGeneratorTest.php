<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Sentinel\SchemaSentinel\Core\MigrationGenerator;
use Sentinel\SchemaSentinel\DTOs\ColumnDefinition;
use Sentinel\SchemaSentinel\DTOs\ForeignKeyDefinition;
use Sentinel\SchemaSentinel\DTOs\IndexDefinition;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;
use Sentinel\SchemaSentinel\DTOs\TableDefinition;
use Sentinel\SchemaSentinel\Tests\TestCase;

class MigrationGeneratorTest extends TestCase
{
    public function test_it_generates_safe_escaped_php_migration_syntax(): void
    {
        $generator = new MigrationGenerator();

        $table = new TableDefinition(
            name: "weird_'table\"_name",
            columns: [
                'id' => new ColumnDefinition('id', 'integer', false, null, null, true),
                'weird_\'col' => new ColumnDefinition("weird_'col", 'string', true, 'default\'val', 100),
            ],
            indexes: [
                'weird_idx' => new IndexDefinition("weird_'idx", 'index', ["weird_'col"]),
            ],
            foreignKeys: []
        );

        $diff = new SchemaDiff(
            missingTables: [$table->name => $table],
            extraTables: [],
            missingColumns: [],
            extraColumns: [],
            mismatchedColumns: [],
            missingIndexes: [],
            missingForeignKeys: []
        );

        $code = $generator->buildMigrationCode($diff, false);

        // Assert valid PHP syntax
        $tempFile = tempnam(sys_get_temp_dir(), 'mig_test_') . '.php';
        File::put($tempFile, $code);

        exec("php -l " . escapeshellarg($tempFile), $output, $exitCode);
        unlink($tempFile);

        $this->assertSame(0, $exitCode, 'Generated migration must have valid PHP syntax: ' . implode("\n", $output));
    }

    public function test_it_generates_bidirectional_rollback_for_changed_columns(): void
    {
        $generator = new MigrationGenerator();

        $liveCol = new ColumnDefinition('status', 'string', true, 'pending');
        $idealCol = new ColumnDefinition('status', 'string', false, 'active');

        $diff = new SchemaDiff(
            missingTables: [],
            extraTables: [],
            missingColumns: [],
            extraColumns: [],
            mismatchedColumns: [
                'users.status' => [
                    'table' => 'users',
                    'live' => $liveCol,
                    'ideal' => $idealCol,
                    'diffs' => ['nullable' => ['live' => true, 'ideal' => false]]
                ]
            ],
            missingIndexes: [],
            missingForeignKeys: []
        );

        $code = $generator->buildMigrationCode($diff, false);

        // Up should change to ideal ('active')
        $this->assertStringContainsString("default('active')->change()", $code);
        // Down should change back to live ('pending') and nullable()
        $this->assertStringContainsString("->nullable()->default('pending')->change()", $code);
    }

    public function test_it_throws_meaningful_exception_for_unsupported_column_types(): void
    {
        $generator = new MigrationGenerator();

        $table = new TableDefinition(
            name: 'geo_table',
            columns: [
                'geom' => new ColumnDefinition('geom', 'geometry_unsupported_type', false, null),
            ],
            indexes: [],
            foreignKeys: []
        );

        $diff = new SchemaDiff(
            missingTables: [$table->name => $table],
            extraTables: [],
            missingColumns: [],
            extraColumns: [],
            mismatchedColumns: [],
            missingIndexes: [],
            missingForeignKeys: []
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported column type [geometry_unsupported_type]');

        $generator->buildMigrationCode($diff, false);
    }

    public function test_it_executes_and_rolls_back_generated_migration_on_live_database(): void
    {
        // 1. Create a base table
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $this->assertTrue(Schema::hasTable('articles'));
        $this->assertFalse(Schema::hasColumn('articles', 'summary'));

        // 2. Generate migration code to add 'summary' column
        $col = new ColumnDefinition('summary', 'string', true, 'default_summary', 255);
        $diff = new SchemaDiff(
            missingTables: [],
            extraTables: [],
            missingColumns: ['articles.summary' => ['table' => 'articles', 'column' => $col]],
            extraColumns: [],
            mismatchedColumns: [],
            missingIndexes: [],
            missingForeignKeys: []
        );

        $generator = new MigrationGenerator();
        $code = $generator->buildMigrationCode($diff, false);

        // 3. Save to a temporary migration file
        $tempDir = storage_path('framework/testing/test_exec_mig_' . uniqid());
        File::ensureDirectoryExists($tempDir);
        $migrationFile = $tempDir . '/2026_01_01_000000_add_summary_to_articles.php';
        File::put($migrationFile, $code);

        try {
            // Instantiate and run up()
            $migration = require $migrationFile;
            $migration->up();

            // Verify column exists
            $this->assertTrue(Schema::hasColumn('articles', 'summary'));

            // Run down()
            $migration->down();

            // Verify column is removed
            $this->assertFalse(Schema::hasColumn('articles', 'summary'));
        } finally {
            Schema::dropIfExists('articles');
            File::deleteDirectory($tempDir);
        }
    }

    public function test_it_generates_replacement_for_changed_foreign_keys_with_safe_rollback(): void
    {
        $generator = new MigrationGenerator();

        $liveFk = new ForeignKeyDefinition('posts_user_fk', ['user_id'], 'users', ['id'], 'no action', 'no action');
        $idealFk = new ForeignKeyDefinition('posts_user_fk', ['user_id'], 'users', ['id'], 'cascade', 'no action');

        $diff = new SchemaDiff(
            missingTables: [],
            extraTables: [],
            missingColumns: [],
            extraColumns: [],
            mismatchedColumns: [],
            missingIndexes: [],
            missingForeignKeys: [],
            changedForeignKeys: [
                'posts.posts_user_fk' => [
                    'table' => 'posts',
                    'live' => $liveFk,
                    'ideal' => $idealFk,
                    'diffs' => ['on_delete' => ['live' => 'no action', 'ideal' => 'cascade']],
                ]
            ]
        );

        $code = $generator->buildMigrationCode($diff, false);

        $this->assertStringContainsString("\$table->dropForeign('posts_user_fk');", $code);
        $this->assertStringContainsString("->onDelete('cascade')", $code);

        // Verify valid PHP syntax
        $tempFile = tempnam(sys_get_temp_dir(), 'fk_mig_') . '.php';
        File::put($tempFile, $code);
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($tempFile), $output, $returnVar);
        unlink($tempFile);
        $this->assertSame(0, $returnVar);
    }

    public function test_it_preserves_decimal_precision_and_scale_in_blueprint(): void
    {
        $generator = new MigrationGenerator();

        $col = new ColumnDefinition('rate', 'decimal', false, null, null, false, null, 12, 4);
        $diff = new SchemaDiff(
            missingColumns: ['rates.rate' => ['table' => 'rates', 'column' => $col]],
        );

        $code = $generator->buildMigrationCode($diff, false);

        $this->assertStringContainsString("\$table->decimal('rate', 12, 4);", $code);
    }

    public function test_it_prevents_duplicate_migration_filenames(): void
    {
        $generator = new MigrationGenerator();
        $diff = new SchemaDiff();

        $path1 = $generator->generate($diff, false);
        $path2 = $generator->generate($diff, false);

        $this->assertNotSame($path1, $path2);
        $this->assertFileExists($path1);
        $this->assertFileExists($path2);

        @unlink($path1);
        @unlink($path2);
    }
}
