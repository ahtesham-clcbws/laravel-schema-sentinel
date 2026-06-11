<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Sentinel\SchemaSentinel\DTOs\TableDefinition;

/**
 * Handles the reverse-engineering of parsed database schemas into
 * standard migrations, seeders, backed enums, and Eloquent models.
 */
class ReverseEngineer
{
    /**
     * Reverse-engineer database tables into clean Laravel files.
     *
     * @param array<string, TableDefinition> $schema
     * @param string $basePath
     * @param bool $models
     * @param bool $migrations
     * @param bool $seeders
     * @param bool $enums
     * @return array{migrations: string[], models: string[], seeders: string[], enums: string[]}
     */
    public function reverse(
        array $schema,
        string $basePath,
        bool $models = true,
        bool $migrations = true,
        bool $seeders = true,
        bool $enums = true
    ): array {
        $basePath = rtrim($basePath, '/');
        $relations = $this->buildRelationshipMapping($schema);
        $results = [
            'migrations' => [],
            'models' => [],
            'seeders' => [],
            'enums' => [],
        ];

        foreach ($schema as $tableName => $table) {
            if ($migrations) {
                $path = $this->generateMigrationFile($tableName, $table, $basePath);
                $results['migrations'][] = $path;
            }

            $enumClasses = [];
            if ($enums) {
                $enumClasses = $this->generateEnumsForTable($tableName, $table, $basePath);
                $results['enums'] = array_merge($results['enums'], array_values($enumClasses));
            }

            if ($models) {
                $path = $this->generateModelFile($tableName, $table, $relations[$tableName] ?? [], $enumClasses, $basePath);
                $results['models'][] = $path;
            }

            if ($seeders) {
                $path = $this->generateSeederFile($tableName, $basePath);
                $results['seeders'][] = $path;
            }
        }

        return $results;
    }

    /**
     * Build the Eloquent relationship mapping based on foreign key metadata.
     *
     * @param array<string, TableDefinition> $schema
     * @return array
     */
    protected function buildRelationshipMapping(array $schema): array
    {
        $mapping = [];

        foreach ($schema as $tableName => $table) {
            $mapping[$tableName] = $mapping[$tableName] ?? [];

            foreach ($table->foreignKeys as $fk) {
                $targetTable = $fk->foreignTable;
                $mapping[$targetTable] = $mapping[$targetTable] ?? [];

                // local belongsTo relationship
                $localCol = $fk->columns[0] ?? '';
                $methodName = Str::camel(Str::replaceLast('_id', '', $localCol));

                $mapping[$tableName][] = [
                    'type' => 'belongsTo',
                    'method' => $methodName,
                    'model' => Str::studly(Str::singular($targetTable)),
                    'foreign_key' => $localCol,
                ];

                // target hasMany relationship
                $targetMethodName = Str::camel(Str::plural($tableName));
                $mapping[$targetTable][] = [
                    'type' => 'hasMany',
                    'method' => $targetMethodName,
                    'model' => Str::studly(Str::singular($tableName)),
                    'foreign_key' => $localCol,
                ];
            }
        }

        return $mapping;
    }

    /**
     * Generate migration file code for a table definition.
     */
    protected function generateMigrationFile(string $tableName, TableDefinition $table, string $basePath): string
    {
        $columnsCode = "";
        foreach ($table->columns as $col) {
            if ($col->name === 'id') {
                $columnsCode .= "            \$table->id();\n";
                continue;
            }

            $type = $col->type;
            if ($type === 'integer' && $col->unsigned) {
                $type = 'unsignedInteger';
            } elseif ($type === 'bigint') {
                $type = $col->unsigned ? 'unsignedBigInteger' : 'bigInteger';
            } elseif ($type === 'tinyint') {
                $type = 'boolean';
            } elseif ($type === 'enum') {
                $type = 'string';
            }

            $colCode = "            \$table->{$type}('{$col->name}')";
            if ($col->nullable) {
                $colCode .= "->nullable()";
            }
            if ($col->default !== null) {
                $defaultVal = $col->default;
                if (strtoupper((string) $defaultVal) === 'CURRENT_TIMESTAMP') {
                    $colCode .= "->useCurrent()";
                } elseif (strtolower((string) $defaultVal) === 'true' || $defaultVal === '1' && $type === 'boolean') {
                    $colCode .= "->default(true)";
                } elseif (strtolower((string) $defaultVal) === 'false' || $defaultVal === '0' && $type === 'boolean') {
                    $colCode .= "->default(false)";
                } else {
                    $val = is_numeric($defaultVal) ? $defaultVal : "'{$defaultVal}'";
                    $colCode .= "->default({$val})";
                }
            }
            if ($col->comment) {
                $colCode .= "->comment('{$col->comment}')";
            }

            $columnsCode .= $colCode . ";\n";
        }

        // Add indexes
        foreach ($table->indexes as $index) {
            if ($index->type === 'primary') continue;
            $colsFormatted = count($index->columns) === 1 ? "'{$index->columns[0]}'" : var_export($index->columns, true);
            $columnsCode .= "            \$table->{$index->type}({$colsFormatted}, '{$index->name}');\n";
        }

        // Add foreign keys
        foreach ($table->foreignKeys as $fk) {
            $colsFormatted = count($fk->columns) === 1 ? "'{$fk->columns[0]}'" : var_export($fk->columns, true);
            $fColsFormatted = count($fk->foreignColumns) === 1 ? "'{$fk->foreignColumns[0]}'" : var_export($fk->foreignColumns, true);
            $columnsCode .= "            \$table->foreign({$colsFormatted}, '{$fk->name}')->references({$fColsFormatted})->on('{$fk->foreignTable}')->onDelete('{$fk->onDelete}');\n";
        }

        $template = <<<PHP
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
        Schema::create('{$tableName}', function (Blueprint \$table) {
{$columnsCode}        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;

        $timestamp = date('Y_m_d_His');
        $dir = "{$basePath}/database/migrations";
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$timestamp}_create_{$tableName}_table.php";
        File::put($path, $template);
        return $path;
    }

    /**
     * Generate PHP 8.4 Backed Enums for enum columns.
     */
    protected function generateEnumsForTable(string $tableName, TableDefinition $table, string $basePath): array
    {
        $enumClasses = [];
        $dir = "{$basePath}/app/Enums";

        foreach ($table->columns as $col) {
            if ($col->type === 'enum' || (str_contains(strtolower($col->name), 'status') && in_array($col->type, ['string', 'varchar', 'text']))) {
                $cases = ['Active', 'Inactive', 'Pending'];
                $enumName = Str::studly(Str::singular($tableName)) . Str::studly($col->name);

                $casesCode = "";
                foreach ($cases as $c) {
                    $val = strtolower($c);
                    $casesCode .= "    case {$c} = '{$val}';\n";
                }

                $template = <<<PHP
<?php

namespace App\Enums;

enum {$enumName}: string
{
{$casesCode}}
PHP;

                File::ensureDirectoryExists($dir);
                $path = "{$dir}/{$enumName}.php";
                File::put($path, $template);
                $enumClasses[$col->name] = "\\App\\Enums\\{$enumName}";
            }
        }

        return $enumClasses;
    }

    /**
     * Generate Model code with casts and capitalized relationship methods.
     */
    protected function generateModelFile(string $tableName, TableDefinition $table, array $tableRelations, array $enumClasses, string $basePath): string
    {
        $modelName = Str::studly(Str::singular($tableName));
        $dir = "{$basePath}/app/Models";

        $casts = [];
        foreach ($table->columns as $col) {
            if ($col->type === 'tinyint') {
                $casts[$col->name] = 'boolean';
            } elseif ($col->type === 'json') {
                $casts[$col->name] = 'array';
            } elseif (in_array($col->type, ['datetime', 'timestamp'])) {
                $casts[$col->name] = 'datetime';
            } elseif (isset($enumClasses[$col->name])) {
                $casts[$col->name] = $enumClasses[$col->name] . '::class';
            }
        }

        $castsCode = "";
        if (!empty($casts)) {
            $castsCode .= "    protected \$casts = [\n";
            foreach ($casts as $colName => $castType) {
                if (str_contains($castType, '::class')) {
                    $castsCode .= "        '{$colName}' => {$castType},\n";
                } else {
                    $castsCode .= "        '{$colName}' => '{$castType}',\n";
                }
            }
            $castsCode .= "    ];\n\n";
        }

        $relationsCode = "";
        foreach ($tableRelations as $rel) {
            $studlyType = Str::studly($rel['type']);
            $relationsCode .= "    public function {$rel['method']}(): \Illuminate\Database\Eloquent\Relations\\{$studlyType}\n";
            $relationsCode .= "    {\n";
            $relationsCode .= "        return \$this->{$rel['type']}(\\{$rel['model']}::class, '{$rel['foreign_key']}');\n";
            $relationsCode .= "    }\n\n";
        }

        $template = <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class {$modelName} extends Model
{
    protected \$table = '{$tableName}';
    protected \$guarded = [];

{$castsCode}{$relationsCode}}
PHP;

        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$modelName}.php";
        File::put($path, $template);
        return $path;
    }

    /**
     * Generate database seeder files.
     */
    protected function generateSeederFile(string $tableName, string $basePath): string
    {
        $seederName = Str::studly($tableName) . "TableSeeder";
        $dir = "{$basePath}/database/seeders";

        $rows = [];
        try {
            $rows = DB::table($tableName)->limit(5)->get()->map(fn($r) => (array) $r)->toArray();
        } catch (\Exception $e) {
            // Table doesn't exist yet
        }

        $rowsCode = "";
        if (!empty($rows)) {
            $rowsCode .= "        DB::table('{$tableName}')->insert([\n";
            foreach ($rows as $row) {
                $rowFormatted = var_export($row, true);
                $rowFormatted = str_replace("\n", "\n            ", $rowFormatted);
                $rowsCode .= "            {$rowFormatted},\n";
            }
            $rowsCode .= "        ]);\n";
        } else {
            $rowsCode .= "        // Seed data here\n";
        }

        $template = <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$seederName} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
{$rowsCode}    }
}
PHP;

        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$seederName}.php";
        File::put($path, $template);
        return $path;
    }
}
