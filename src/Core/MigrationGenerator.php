<?php

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
        $name = 'fix_schema_drift_' . date('Y_m_d_His');
        $fileName = date('Y_m_d_His') . '_' . $name . '.php';
        $path = App::databasePath('migrations/' . $fileName);

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
            $up[] = $this->buildCreateTableCode($table);
            $down[] = "Schema::dropIfExists('{$tableName}');";
        }

        // 2. Missing Columns Logic
        $groupedMissing = $this->groupByTable($diff->missingColumns);
        foreach ($groupedMissing as $tableName => $columns) {
            $colNames = implode(', ', array_map(fn($c) => $c['column']->name, $columns));
            if ($interactive && !$command->confirm("Add columns [$colNames] to table [$tableName]?", true)) {
                continue;
            }
            $up[] = $this->buildAddColumnsCode($tableName, $columns);
            $down[] = "Schema::table('$tableName', function (Blueprint \$table) {\n            \$table->dropColumn([" . implode(', ', array_map(fn($c) => "'{$c['column']->name}'", $columns)) . "]);\n        });";
        }

        // 3. Mismatched Columns Logic
        $groupedMismatched = $this->groupByTable($diff->mismatchedColumns);
        foreach ($groupedMismatched as $tableName => $mismatches) {
            $colNames = implode(', ', array_map(fn($m) => $m['live']->name, $mismatches));
            if ($interactive && !$command->confirm("Change columns [$colNames] in table [$tableName]?", true)) {
                continue;
            }
            $up[] = $this->buildChangeColumnsCode($tableName, $mismatches);
            $down[] = "// Manual rollback required for column changes in table [$tableName]";
        }

        // 4. Missing Indexes Logic
        $groupedIdx = $this->groupByTable($diff->missingIndexes);
        foreach ($groupedIdx as $tableName => $indexes) {
            if ($interactive && !$command->confirm("Add indexes to table [$tableName]?", true)) {
                continue;
            }
            $up[] = $this->buildAddIndexesCode($tableName, $indexes);
            foreach ($indexes as $idx) {
                $down[] = "Schema::table('$tableName', function (Blueprint \$table) { \$table->dropIndex('{$idx['index']->name}'); });";
            }
        }

        // 5. Missing Foreign Keys Logic
        $groupedFks = $this->groupByTable($diff->missingForeignKeys);
        foreach ($groupedFks as $tableName => $fks) {
            if ($interactive && !$command->confirm("Add foreign keys to table [$tableName]?", true)) {
                continue;
            }
            $up[] = $this->buildAddForeignKeysCode($tableName, $fks);
            foreach ($fks as $fk) {
                $down[] = "Schema::table('$tableName', function (Blueprint \$table) { \$table->dropForeign('{$fk['fk']->name}'); });";
            }
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
        $lines = ["Schema::create('{$table->name}', function (Blueprint \$table) {"];
        foreach ($table->columns as $col) {
            $lines[] = "            " . $this->columnToBlueprint($col) . ";";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildAddColumnsCode(string $tableName, array $columns): string
    {
        $lines = ["Schema::table('$tableName', function (Blueprint \$table) {"];
        foreach ($columns as $item) {
            $lines[] = "            " . $this->columnToBlueprint($item['column']) . ";";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildChangeColumnsCode(string $tableName, array $mismatches): string
    {
        $lines = ["Schema::table('$tableName', function (Blueprint \$table) {"];
        foreach ($mismatches as $item) {
            $lines[] = "            " . $this->columnToBlueprint($item['ideal']) . "->change();";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildAddIndexesCode(string $tableName, array $indexes): string
    {
        $lines = ["Schema::table('$tableName', function (Blueprint \$table) {"];
        foreach ($indexes as $item) {
            $idx = $item['index'];
            $cols = "['" . implode("', '", $idx->columns) . "']";
            $lines[] = "            \$table->{$idx->type}($cols, '{$idx->name}');";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function buildAddForeignKeysCode(string $tableName, array $fks): string
    {
        $lines = ["Schema::table('$tableName', function (Blueprint \$table) {"];
        foreach ($fks as $item) {
            $fk = $item['fk'];
            $cols = "['" . implode("', '", $fk->columns) . "']";
            $fCols = "['" . implode("', '", $fk->foreignColumns) . "']";
            $lines[] = "            \$table->foreign($cols, '{$fk->name}')\n                  ->references($fCols)->on('{$fk->foreignTable}')\n                  ->onDelete('{$fk->onDelete}')->onUpdate('{$fk->onUpdate}');";
        }
        $lines[] = "        });";
        return implode("\n", $lines);
    }

    protected function columnToBlueprint($col): string
    {
        $method = $this->mapTypeToMethod($col->type);
        $code = "\$table->{$method}('{$col->name}')";

        if ($col->nullable) { $code .= "->nullable()"; }
        if ($col->default !== null) {
            $default = is_string($col->default) ? "'{$col->default}'" : $col->default;
            $code .= "->default({$default})";
        }

        return $code;
    }

    protected function mapTypeToMethod(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'int' => 'integer',
            'bigint' => 'bigint',
            'tinyint' => 'tinyInteger',
            'smallint' => 'smallInteger',
            'mediumint' => 'mediumInteger',
            'string', 'varchar' => 'string',
            'text', 'longtext', 'mediumtext' => 'text',
            'boolean', 'bool' => 'boolean',
            'timestamp' => 'timestamp',
            'datetime' => 'dateTime',
            'date' => 'date',
            'time' => 'time',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'json', 'jsonb' => 'json',
            'uuid' => 'uuid',
            'ipaddress' => 'ipAddress',
            'macaddress' => 'macAddress',
            default => 'string',
        };
    }
}
