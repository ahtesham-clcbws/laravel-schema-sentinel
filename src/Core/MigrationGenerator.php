<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Core;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;

/**
 * Automates the creation of migration files to fix schema drift.
 * 
 * It generates bi-directional migrations (up/down) to ensure that 
 * changes can be rolled back safely.
 */
class MigrationGenerator
{
    /**
     * Generate a new migration file to fix the detected drift.
     * 
     * @param SchemaDiff $diff The detected differences.
     * @param bool $interactive If true, prompts the user for each change.
     * @return string The path to the generated migration file.
     */
    public function generate(SchemaDiff $diff, bool $interactive = false): string
    {
        // Prevent filename collision even when multiple migrations are generated within the same second
        $micro = sprintf("%06d", (int) (microtime(true) * 1000000) % 1000000);
        $name = 'fix_schema_drift_' . date('Y_m_d_His') . '_' . $micro;
        $fileName = date('Y_m_d_His') . '_' . $name . '.php';
        $path = App::databasePath('migrations/' . $fileName);

        // Ensure collision safety if file already exists
        $counter = 1;
        while (File::exists($path)) {
            $name = 'fix_schema_drift_' . date('Y_m_d_His') . '_' . $micro . '_' . $counter;
            $fileName = date('Y_m_d_His') . '_' . $name . '.php';
            $path = App::databasePath('migrations/' . $fileName);
            $counter++;
        }

        $content = $this->buildMigrationCode($diff, $interactive);

        File::put($path, $content);

        return $path;
    }

    /**
     * Assemble the migration file content.
     */
    public function buildMigrationCode(SchemaDiff $diff, bool $interactive): string
    {
        $up = [];
        $down = [];
        $command = App::make(\Illuminate\Console\Command::class);

        // 1. Missing Tables Logic
        foreach ($diff->missingTables as $tableName => $table) {
            if ($interactive && !$command->confirm("Create missing table [$tableName]?", true)) {
                continue;
            }
            $tableLiteral = var_export($tableName, true);
            $up[] = "if (!Schema::hasTable({$tableLiteral})) {\n            " . $this->buildCreateTableCode($table) . "\n        }";
            $down[] = "Schema::dropIfExists({$tableLiteral});";
        }

        // 2. Missing Columns Logic
        $groupedMissing = $this->groupByTable($diff->missingColumns);
        foreach ($groupedMissing as $tableName => $columns) {
            $colNames = implode(', ', array_map(fn($c) => $c['column']->name, $columns));
            if ($interactive && !$command->confirm("Add columns [$colNames] to table [$tableName]?", true)) {
                continue;
            }
            $tableLiteral = var_export($tableName, true);
            $up[] = $this->buildAddColumnsCode($tableName, $columns);
            $colsArrayLiteral = var_export(array_map(fn($c) => $c['column']->name, $columns), true);
            $down[] = "Schema::table({$tableLiteral}, function (Blueprint \$table) {\n            \$table->dropColumn({$colsArrayLiteral});\n        });";
        }

        // 3. Mismatched Columns Logic
        $groupedMismatched = $this->groupByTable($diff->mismatchedColumns);
        foreach ($groupedMismatched as $tableName => $mismatches) {
            $colNames = implode(', ', array_map(fn($m) => $m['live']->name, $mismatches));
            if ($interactive && !$command->confirm("Change columns [$colNames] in table [$tableName]?", true)) {
                continue;
            }
            $up[] = $this->buildChangeColumnsCode($tableName, $mismatches);
            $down[] = $this->buildRollbackChangeColumnsCode($tableName, $mismatches);
        }

        // 4. Missing Indexes Logic
        $groupedIdx = $this->groupByTable($diff->missingIndexes);
        foreach ($groupedIdx as $tableName => $indexes) {
            if ($interactive && !$command->confirm("Add indexes to table [$tableName]?", true)) {
                continue;
            }
            $tableLiteral = var_export($tableName, true);
            $up[] = $this->buildAddIndexesCode($tableName, $indexes);
            foreach ($indexes as $idx) {
                $idxNameLiteral = var_export($idx['index']->name, true);
                if ($idx['index']->type === 'primary') {
                    $down[] = "Schema::table({$tableLiteral}, function (Blueprint \$table) { \$table->dropPrimary(); });";
                } elseif ($idx['index']->type === 'unique') {
                    $down[] = "Schema::table({$tableLiteral}, function (Blueprint \$table) { \$table->dropUnique({$idxNameLiteral}); });";
                } else {
                    $down[] = "Schema::table({$tableLiteral}, function (Blueprint \$table) { \$table->dropIndex({$idxNameLiteral}); });";
                }
            }
        }

        // 5. Missing Foreign Keys Logic
        $groupedFks = $this->groupByTable($diff->missingForeignKeys);
        foreach ($groupedFks as $tableName => $fks) {
            if ($interactive && !$command->confirm("Add foreign keys to table [$tableName]?", true)) {
                continue;
            }
            $tableLiteral = var_export($tableName, true);
            $up[] = $this->buildAddForeignKeysCode($tableName, $fks);
            foreach ($fks as $fk) {
                $fkNameLiteral = var_export($fk['fk']->name, true);
                $down[] = "Schema::table({$tableLiteral}, function (Blueprint \$table) {\n            \$table->dropForeign({$fkNameLiteral});\n        });";
            }
        }

        // 6. Changed Foreign Keys Logic (Drop and Recreate safely)
        $groupedChangedFks = $this->groupByTable($diff->changedForeignKeys ?? []);
        foreach ($groupedChangedFks as $tableName => $items) {
            if ($interactive && !$command->confirm("Update altered foreign keys on table [$tableName]?", true)) {
                continue;
            }
            $tableLiteral = var_export($tableName, true);
            $up[] = $this->buildReplaceForeignKeysCode($tableName, $items, 'ideal');
            $down[] = $this->buildReplaceForeignKeysCode($tableName, $items, 'live');
        }

        $upContent = implode("\n\n        ", $up);
        $downContent = implode("\n\n        ", $down);

        return $this->getStubContent($upContent, $downContent);
    }

    /**
     * Get the final migration file template.
     */
    protected function getStubContent(string $up, string $down): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $up
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $down
    }
};
PHP;
    }

    protected function groupByTable(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['table']][] = $item;
        }
        return $grouped;
    }

    protected function buildCreateTableCode($table): string
    {
        $tableLiteral = var_export($table->name, true);
        $lines = ["Schema::create({$tableLiteral}, function (Blueprint \$table) {"];
        foreach ($table->columns as $col) {
            $lines[] = "            " . $this->columnToBlueprint($col) . ";";
        }
        if (!empty($table->indexes)) {
            foreach ($table->indexes as $idx) {
                $colsLiteral = var_export($idx->columns, true);
                $nameLiteral = var_export($idx->name, true);
                if ($idx->type === 'primary') {
                    $lines[] = "            \$table->primary({$colsLiteral});";
                } elseif (in_array($idx->type, ['unique', 'index', 'spatial', 'fulltext'])) {
                    $lines[] = "            \$table->{$idx->type}({$colsLiteral}, {$nameLiteral});";
                }
            }
        }
        if (!empty($table->foreignKeys)) {
            foreach ($table->foreignKeys as $fk) {
                $colsLiteral = var_export($fk->columns, true);
                $fColsLiteral = var_export($fk->foreignColumns, true);
                $nameLiteral = var_export($fk->name, true);
                $fTableLiteral = var_export($fk->foreignTable, true);
                $onDeleteLiteral = var_export($fk->onDelete, true);
                $onUpdateLiteral = var_export($fk->onUpdate, true);

                $lines[] = "            \$table->foreign({$colsLiteral}, {$nameLiteral})\n                  ->references({$fColsLiteral})->on({$fTableLiteral})\n                  ->onDelete({$onDeleteLiteral})->onUpdate({$onUpdateLiteral});";
            }
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildAddColumnsCode(string $tableName, array $columns): string
    {
        $tableLiteral = var_export($tableName, true);
        $lines = ["Schema::table({$tableLiteral}, function (Blueprint \$table) {"];
        foreach ($columns as $item) {
            $lines[] = "            " . $this->columnToBlueprint($item['column']) . ";";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildChangeColumnsCode(string $tableName, array $mismatches): string
    {
        $tableLiteral = var_export($tableName, true);
        $lines = ["Schema::table({$tableLiteral}, function (Blueprint \$table) {"];
        foreach ($mismatches as $item) {
            $lines[] = "            " . $this->columnToBlueprint($item['ideal']) . "->change();";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildRollbackChangeColumnsCode(string $tableName, array $mismatches): string
    {
        $tableLiteral = var_export($tableName, true);
        $lines = ["Schema::table({$tableLiteral}, function (Blueprint \$table) {"];
        foreach ($mismatches as $item) {
            $lines[] = "            " . $this->columnToBlueprint($item['live']) . "->change();";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildAddIndexesCode(string $tableName, array $indexes): string
    {
        $tableLiteral = var_export($tableName, true);
        $lines = ["Schema::table({$tableLiteral}, function (Blueprint \$table) {"];
        foreach ($indexes as $item) {
            $idx = $item['index'];
            $colsLiteral = var_export($idx->columns, true);
            $nameLiteral = var_export($idx->name, true);
            if ($idx->type === 'primary') {
                $lines[] = "            \$table->primary({$colsLiteral});";
            } else {
                $lines[] = "            \$table->{$idx->type}({$colsLiteral}, {$nameLiteral});";
            }
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildAddForeignKeysCode(string $tableName, array $fks): string
    {
        $tableLiteral = var_export($tableName, true);
        $lines = ["Schema::table({$tableLiteral}, function (Blueprint \$table) {"];
        foreach ($fks as $item) {
            $fk = $item['fk'];
            $colsLiteral = var_export($fk->columns, true);
            $fColsLiteral = var_export($fk->foreignColumns, true);
            $nameLiteral = var_export($fk->name, true);
            $fTableLiteral = var_export($fk->foreignTable, true);
            $onDeleteLiteral = var_export($fk->onDelete, true);
            $onUpdateLiteral = var_export($fk->onUpdate, true);

            $lines[] = "            \$table->foreign({$colsLiteral}, {$nameLiteral})\n                  ->references({$fColsLiteral})->on({$fTableLiteral})\n                  ->onDelete({$onDeleteLiteral})->onUpdate({$onUpdateLiteral});";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildReplaceForeignKeysCode(string $tableName, array $items, string $targetState): string
    {
        $tableLiteral = var_export($tableName, true);
        $lines = ["Schema::table({$tableLiteral}, function (Blueprint \$table) {"];
        
        // 1. Drop the constraint from the opposing state first
        $opposingState = ($targetState === 'ideal') ? 'live' : 'ideal';
        foreach ($items as $item) {
            $opposingFk = $item[$opposingState];
            $dropNameLiteral = var_export($opposingFk->name, true);
            $lines[] = "            \$table->dropForeign({$dropNameLiteral});";
        }

        // 2. Add the target constraint
        foreach ($items as $item) {
            $fk = $item[$targetState];
            $colsLiteral = var_export($fk->columns, true);
            $fColsLiteral = var_export($fk->foreignColumns, true);
            $nameLiteral = var_export($fk->name, true);
            $fTableLiteral = var_export($fk->foreignTable, true);
            $onDeleteLiteral = var_export($fk->onDelete, true);
            $onUpdateLiteral = var_export($fk->onUpdate, true);

            $lines[] = "            \$table->foreign({$colsLiteral}, {$nameLiteral})\n                  ->references({$fColsLiteral})->on({$fTableLiteral})\n                  ->onDelete({$onDeleteLiteral})->onUpdate({$onUpdateLiteral});";
        }

        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function columnToBlueprint($col): string
    {
        $method = $this->mapTypeToMethod($col->type);
        $nameLiteral = var_export($col->name, true);

        if ($col->length !== null && in_array($method, ['string', 'char'])) {
            $code = "\$table->{$method}({$nameLiteral}, {$col->length})";
        } elseif ($method === 'decimal' && $col->precision !== null) {
            $scale = $col->scale ?? 0;
            $code = "\$table->decimal({$nameLiteral}, {$col->precision}, {$scale})";
        } else {
            $code = "\$table->{$method}({$nameLiteral})";
        }

        if ($col->unsigned && in_array($method, ['integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'mediumInteger', 'decimal', 'float', 'double'])) {
            $code .= "->unsigned()";
        }

        if ($col->nullable) {
            $code .= "->nullable()";
        }

        if ($col->default !== null) {
            $normalizedDefault = is_string($col->default) ? trim($col->default, "'\" \t\n\r\0\x0B") : $col->default;
            if (is_string($normalizedDefault) && strtolower($normalizedDefault) === 'null') {
                if (!$col->nullable) {
                    $code .= "->nullable()";
                }
            } else {
                $defaultLiteral = var_export($col->default, true);
                $code .= "->default({$defaultLiteral})";
            }
        }

        if (!empty($col->comment)) {
            $commentLiteral = var_export($col->comment, true);
            $code .= "->comment({$commentLiteral})";
        }

        return $code;
    }

    protected function mapTypeToMethod(string $type): string
    {
        $rawType = trim($type);
        $type = strtolower($rawType);

        // Check for tinyint(1)
        if (preg_match('/^tinyint\s*\(\s*1\s*\)$/i', $type)) {
            return 'boolean';
        }

        // Strip length/precision if present
        if (preg_match('/^([a-z_]+)\s*\(/i', $type, $matches)) {
            $type = $matches[1];
        }

        $custom = config('schema-sentinel.custom_types', []);

        if (isset($custom[$type])) {
            return $custom[$type];
        }

        return match ($type) {
            'integer', 'int', 'int4' => 'integer',
            'bigint', 'int8', 'bigserial' => 'bigInteger',
            'smallint', 'int2' => 'smallInteger',
            'mediumint' => 'mediumInteger',
            'tinyint' => 'tinyInteger',
            'string', 'varchar', 'character varying', 'nvarchar' => 'string',
            'char' => 'char',
            'text', 'longtext', 'mediumtext', 'tinytext' => 'text',
            'boolean', 'bool' => 'boolean',
            'timestamp', 'timestamptz' => 'timestamp',
            'datetime', 'datetime2' => 'dateTime',
            'date' => 'date',
            'time' => 'time',
            'decimal', 'numeric' => 'decimal',
            'float', 'float4', 'real' => 'float',
            'double', 'float8' => 'double',
            'json', 'jsonb' => 'json',
            'uuid' => 'uuid',
            'ipaddress' => 'ipAddress',
            'macaddress' => 'macAddress',
            'binary', 'blob' => 'binary',
            default => throw new \RuntimeException("Unsupported column type [{$rawType}] detected for repair migration generation."),
        };
    }
}
