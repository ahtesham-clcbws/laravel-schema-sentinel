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
}
