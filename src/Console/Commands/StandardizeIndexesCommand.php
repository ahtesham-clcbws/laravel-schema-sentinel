<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Sentinel\SchemaSentinel\Facades\Sentinel;
use Sentinel\SchemaSentinel\DTOs\IndexDefinition;

/**
 * Scans database tables and standardizes index names to match Laravel's conventions.
 * Also intelligently identifies redundant/duplicate indexes to optimize DB performance.
 */
#[Signature('schema:standardize-indexes {--fix : Generate a migration to drop redundant indexes and rename non-standard indexes}')]
#[Description('Scan database tables and optimize/standardize index names to Laravel conventions.')]
class StandardizeIndexesCommand extends Command
{
    /**
     * The console command help.
     */
    protected $help = "Scan database tables to validate index naming conventions and optimize index structures.

Examples:
  <fg=green>php artisan schema:standardize-indexes</>
  <fg=green>php artisan schema:standardize-indexes --fix</>

Options:
  --fix               Generate a migration file to rename deviant index names and drop duplicate/redundant indexes.";

    public function handle(): int
    {
        $this->components->info('Scanning database for non-standard and redundant indexes...');

        $schema = Sentinel::parse();
        $deviations = [];
        $redundant = [];

        foreach ($schema as $tableName => $table) {
            $indexes = array_values($table->indexes);
            $indexCount = count($indexes);

            // 1. Scan for naming deviations and long names
            foreach ($indexes as $index) {
                if ($index->type === 'primary') {
                    if ($index->name !== 'primary') {
                        $expected = $this->generateLaravelIndexName($tableName, $index->columns, 'primary');
                        if ($index->name !== $expected) {
                            $deviations[] = $this->buildDeviationPayload($tableName, $index, $expected);
                        }
                    }
                    continue;
                }

                $expected = $this->generateLaravelIndexName($tableName, $index->columns, $index->type);
                if ($index->name !== $expected) {
                    $deviations[] = $this->buildDeviationPayload($tableName, $index, $expected);
                }
            }

            // 2. Scan for duplicate/redundant indexes (Intelligence)
            for ($i = 0; $i < $indexCount; $i++) {
                for ($j = 0; $j < $indexCount; $j++) {
                    if ($i === $j) {
                        continue;
                    }

                    $idxA = $indexes[$i];
                    $idxB = $indexes[$j];

                    // Primary indexes are never redundant
                    if ($idxA->type === 'primary' || $idxB->type === 'primary') {
                        continue;
                    }

                    // If index B columns are a left-prefix of index A columns, and B is not unique
                    if ($idxB->type !== 'unique' && $this->isLeftPrefix($idxB->columns, $idxA->columns)) {
                        $redundant[] = [
                            'table' => $tableName,
                            'redundant_index' => $idxB->name,
                            'redundant_columns' => $idxB->columns,
                            'covered_by_index' => $idxA->name,
                            'covered_by_columns' => $idxA->columns,
                        ];
                    }
                }
            }
        }

        // Report deviations
        if (!empty($deviations)) {
            $this->components->warn('Found ' . count($deviations) . ' non-standard index names:');
            $this->table(
                ['Table', 'Columns', 'Type', 'Current Name', 'Expected Name'],
                array_map(fn($d) => [$d['table'], implode(', ', $d['columns']), $d['type'], $d['current'], $d['expected']], $deviations)
            );
        } else {
            $this->components->info('All indexes conform to Laravel naming standards.');
        }

        // Report redundant indexes
        if (!empty($redundant)) {
            $this->newLine();
            $this->components->warn('Found ' . count($redundant) . ' redundant/duplicate indexes (Prefix Coverage):');
            $this->table(
                ['Table', 'Redundant Index', 'Columns', 'Covered By Index', 'Covered Columns'],
                array_map(fn($r) => [$r['table'], $r['redundant_index'], implode(', ', $r['redundant_columns']), $r['covered_by_index'], implode(', ', $r['covered_by_columns'])], $redundant)
            );
        } else {
            $this->components->info('No duplicate/redundant indexes detected.');
        }

        if ($this->option('fix') && (!empty($deviations) || !empty($redundant))) {
            $this->generateOptimizationMigration($deviations, $redundant);
        }

        return 0;
    }

    protected function buildDeviationPayload(string $table, IndexDefinition $index, string $expected): array
    {
        return [
            'table' => $table,
            'columns' => $index->columns,
            'type' => $index->type,
            'current' => $index->name,
            'expected' => $expected,
        ];
    }

    protected function isLeftPrefix(array $colsB, array $colsA): bool
    {
        if (count($colsB) >= count($colsA)) {
            return false;
        }

        for ($i = 0; $i < count($colsB); $i++) {
            if ($colsB[$i] !== $colsA[$i]) {
                return false;
            }
        }

        return true;
    }

    protected function generateLaravelIndexName(string $table, array $columns, string $type): string
    {
        $index = strtolower($table.'_'.implode('_', $columns).'_'.$type);
        $name = str_replace(['-', '.'], '_', $index);

        // Intelligently shorten name if it exceeds DB limit (64 chars)
        if (strlen($name) > 64) {
            $hashed = substr(md5($name), 0, 8);
            $prefix = substr($table, 0, 20) . '_' . substr(implode('_', $columns), 0, 20);
            $name = strtolower($prefix . '_' . $hashed . '_' . $type);
        }

        return $name;
    }

    protected function generateOptimizationMigration(array $deviations, array $redundant): void
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_optimize_and_standardize_indexes.php";
        $path = database_path("migrations/{$filename}");

        $upCode = "";
        $downCode = "";

        // Handle renames
        foreach ($deviations as $d) {
            $upCode .= "        Schema::table('{$d['table']}', function (Blueprint \$table) {\n";
            $upCode .= "            \$table->renameIndex('{$d['current']}', '{$d['expected']}');\n";
            $upCode .= "        });\n\n";

            $downCode .= "        Schema::table('{$d['table']}', function (Blueprint \$table) {\n";
            $downCode .= "            \$table->renameIndex('{$d['expected']}', '{$d['current']}');\n";
            $downCode .= "        });\n\n";
        }

        // Handle redundant drops
        foreach ($redundant as $r) {
            $colsFormatted = implode("', '", $r['redundant_columns']);
            $upCode .= "        Schema::table('{$r['table']}', function (Blueprint \$table) {\n";
            $upCode .= "            \$table->dropIndex('{$r['redundant_index']}');\n";
            $upCode .= "        });\n\n";

            $downCode .= "        Schema::table('{$r['table']}', function (Blueprint \$table) {\n";
            $downCode .= "            \$table->index(['{$colsFormatted}'], '{$r['redundant_index']}');\n";
            $downCode .= "        });\n\n";
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
{$upCode}    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downCode}    }
};
PHP;

        File::put($path, $template);
        $this->components->info("Optimization migration generated: " . basename($path));
    }
}
