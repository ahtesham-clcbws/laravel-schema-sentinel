<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Compares data content in critical reference tables between environments.
 */
#[Signature('schema:data-drift {--compare-env= : The database connection to compare against}')]
#[Description('Scan database tables for data content drift against a target environment.')]
class DataDriftCommand extends Command
{
    /**
     * The console command help.
     */
    protected $help = "Scan database tables for data content drift against a target environment.

Examples:
  <fg=green>php artisan schema:data-drift --compare-env=production</>

Options:
  --compare-env=name  The target database connection name to compare rows and records against.";

    public function handle(): int
    {
        $targetEnv = $this->option('compare-env');

        if (!$targetEnv) {
            $this->components->error('Missing required option: --compare-env');
            $this->line('  Please specify a target database connection, e.g. <fg=cyan>--compare-env=staging</>');
            return 1;
        }

        $this->components->info("Comparing data against environment: {$targetEnv}");

        $tables = Config::get('schema-sentinel.data_audit_tables', []);

        if (empty($tables)) {
            $this->components->warn('No tables configured for data auditing. Add them to "data_audit_tables" in config/schema-sentinel.php.');
            return 0;
        }

        $hasDrift = false;

        foreach ($tables as $table) {
            $this->newLine();
            $this->components->task("Auditing table: {$table}", function () use ($table, $targetEnv, &$hasDrift) {
                return $this->auditTable($table, $targetEnv, $hasDrift);
            });
        }

        return $hasDrift ? 1 : 0;
    }

    protected function auditTable(string $table, string $targetEnv, bool &$hasDrift): bool
    {
        try {
            $localData = DB::table($table)->orderBy('id')->get()->keyBy('id')->toArray();
        } catch (\Exception $e) {
            $this->line("  <fg=red>✕</> Local table <fg=cyan>{$table}</> does not exist or failed to query.");
            $hasDrift = true;
            return false;
        }

        try {
            $targetData = DB::connection($targetEnv)->table($table)->orderBy('id')->get()->keyBy('id')->toArray();
        } catch (\Exception $e) {
            $this->line("  <fg=red>✕</> Target connection table <fg=cyan>{$targetEnv}.{$table}</> does not exist or failed to query.");
            $hasDrift = true;
            return false;
        }

        $localKeys = array_keys($localData);
        $targetKeys = array_keys($targetData);

        $missingLocally = array_diff($targetKeys, $localKeys);
        $extraLocally = array_diff($localKeys, $targetKeys);

        $mismatches = [];

        // Check common rows for field values differences
        $commonKeys = array_intersect($localKeys, $targetKeys);
        foreach ($commonKeys as $key) {
            $localRow = (array) $localData[$key];
            $targetRow = (array) $targetData[$key];

            foreach ($localRow as $col => $localVal) {
                $targetVal = $targetRow[$col] ?? null;
                if ($localVal !== $targetVal) {
                    $mismatches[] = [
                        'id' => $key,
                        'column' => $col,
                        'local' => is_scalar($localVal) ? $localVal : json_encode($localVal),
                        'target' => is_scalar($targetVal) ? $targetVal : json_encode($targetVal),
                    ];
                }
            }
        }

        if (empty($missingLocally) && empty($extraLocally) && empty($mismatches)) {
            return true;
        }

        $hasDrift = true;

        $this->newLine();
        $this->line("  <fg=red>⚠️ Data Drift Detected in '{$table}'</>");

        if (!empty($missingLocally)) {
            $this->line("  <fg=red>• Missing rows (exist in target but not local):</> " . implode(', ', $missingLocally));
        }

        if (!empty($extraLocally)) {
            $this->line("  <fg=yellow>• Extra rows (exist locally but not in target):</> " . implode(', ', $extraLocally));
        }

        if (!empty($mismatches)) {
            $this->line("  <fg=red>• Row content differences:</>");
            $this->table(
                ['Row ID', 'Column', 'Local Value', 'Target Value'],
                array_map(fn($m) => [$m['id'], $m['column'], $m['local'], $m['target']], $mismatches)
            );
        }

        return false;
    }
}
