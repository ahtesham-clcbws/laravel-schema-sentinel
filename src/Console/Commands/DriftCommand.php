<?php

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sentinel\SchemaSentinel\Core\DiffEngine;
use Sentinel\SchemaSentinel\Core\SchemaParser;
use Sentinel\SchemaSentinel\Core\ShadowMigrationRunner;
use Sentinel\SchemaSentinel\Core\MigrationGenerator;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;

/**
 * The Artisan command for managing schema drift.
 */
class DriftCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schema:drift 
                            {--fix : Generate a new migration to fix the drift}
                            {--strict : Report extra tables and columns in the live DB}
                            {--interactive : Confirm each fix step-by-step}';
    
    /**
     * The console command description.
     */
    protected $description = 'Detect and report schema drift between migrations and the live database.';

    /**
     * Execute the console command.
     */
    public function handle(
        ShadowMigrationRunner $shadowRunner,
        SchemaParser $parser,
        DiffEngine $diffEngine,
        MigrationGenerator $generator
    ): int {
        $this->components->info('Starting Schema Drift Analysis...');

        $shadowConn = null;
        $liveSchema = [];
        $idealSchema = [];

        // 1. Simulate migrations
        try {
            $this->components->task('Simulating migrations on Shadow DB', function () use ($shadowRunner, &$shadowConn) {
                $shadowConn = $shadowRunner->run();
            });
        } catch (\Exception $e) {
            $this->newLine();
            $this->components->error('Shadow migration simulation failed!');
            $this->line("  <fg=red>Error:</> " . $e->getMessage());
            
            // Try to extract the failing migration filename from the stack trace
            $failingFile = null;
            if (preg_match('/database\/migrations\/([^\s:]+\.php)/', $e->getTraceAsString(), $matches)) {
                $failingFile = $matches[1];
            }

            $this->newLine();
            $this->components->warn('Potential Solutions:');
            
            if ($failingFile) {
                $this->line("  <fg=white>1. Skip the failing migration:</>");
                $this->line("     Add <fg=cyan>'{$failingFile}'</> to the <fg=green>'skip_migrations'</> array in <fg=gray>config/schema-sentinel.php</>");
                
                if ($this->confirm("Would you like me to add it for you automatically?")) {
                    $this->autoSkipMigration($failingFile);
                    $this->components->info("Migration added to skip list. Please run schema:drift again.");
                    return 1;
                }
                $this->newLine();
            }

            $this->line('  <fg=white>' . ($failingFile ? '2' : '1') . '. Check for Missing Tables:</> This migration might be trying to modify a table that was never created.');
            $this->line('  <fg=white>' . ($failingFile ? '3' : '2') . '. SQLite Incompatibility:</> Your migrations might use MySQL-specific features.');
            $this->line('    <fg=gray>Solution: Change "shadow_connection" to a MySQL driver in config/schema-sentinel.php</>');
            $this->newLine();
            
            return 1;
        }

        // 2. Parse schemas
        $this->components->task('Parsing schemas', function () use ($parser, $shadowConn, &$liveSchema, &$idealSchema) {
            if (!$shadowConn instanceof \Illuminate\Database\Connection) {
                throw new \RuntimeException('Shadow connection failed to initialize.');
            }
            $liveSchema = $parser->parse(DB::connection());
            $idealSchema = $parser->parse($shadowConn);
        });

        // 3. Diff Analysis
        $diff = $diffEngine->compare($liveSchema, $idealSchema, $this->option('strict'));

        // 4. Visual Reporting
        $this->renderReport($diff);

        // 5. Cleanup
        $shadowRunner->cleanup();

        if ($diff->hasDifferences()) {
            if ($this->option('fix')) {
                $path = $generator->generate($diff, $this->option('interactive'));
                $this->components->info("Migration generated: " . basename($path));
            }
            
            return 1; // Exit code 1 for CI if drift detected
        }

        return 0;
    }

    /**
     * Render the visual report to the console.
     */
    protected function renderReport(SchemaDiff $diff): void
    {
        if (!$diff->hasDifferences()) {
            $this->components->info('No schema drift detected. Database is in sync with migrations.');
            return;
        }

        $this->newLine();
        $this->components->warn('Schema Drift Detected:');

        // Missing Tables
        if (!empty($diff->missingTables)) {
            $this->components->error('Missing Tables in Live DB:');
            foreach ($diff->missingTables as $table) {
                $this->line("  <fg=red>•</> {$table->name}");
            }
        }

        // Extra Tables (Strict Mode)
        if (!empty($diff->extraTables)) {
            $this->newLine();
            $this->components->warn('Extra Tables in Live DB (Strict Mode):');
            foreach ($diff->extraTables as $table) {
                $this->line("  <fg=yellow>•</> {$table->name}");
            }
        }

        // Missing Columns
        if (!empty($diff->missingColumns)) {
            $this->newLine();
            $this->components->error('Missing Columns in Live DB:');
            foreach ($diff->missingColumns as $data) {
                $this->line("  <fg=red>•</> <fg=cyan>{$data['table']}</>->{$data['column']->name} (<fg=gray>{$data['column']->type}</>)");
            }
        }

        // Column Mismatches
        if (!empty($diff->mismatchedColumns)) {
            $this->newLine();
            $this->components->error('Column Mismatches:');
            foreach ($diff->mismatchedColumns as $key => $data) {
                $columnName = $data['live']->name;
                $this->line("  <fg=red>•</> <fg=cyan>{$data['table']}</>->{$columnName}");
                foreach ($data['diffs'] as $attr => $val) {
                    $liveVal = is_bool($val['live']) ? ($val['live'] ? 'true' : 'false') : ($val['live'] ?? 'null');
                    $idealVal = is_bool($val['ideal']) ? ($val['ideal'] ? 'true' : 'false') : ($val['ideal'] ?? 'null');
                    $this->line("    <fg=gray>-</> $attr: <fg=red>Live [{$liveVal}]</> vs <fg=green>Ideal [{$idealVal}]</>");
                }
            }
        }

        // Missing Indexes
        if (!empty($diff->missingIndexes)) {
            $this->newLine();
            $this->components->error('Missing Indexes in Live DB:');
            foreach ($diff->missingIndexes as $data) {
                $cols = implode(', ', $data['index']->columns);
                $this->line("  <fg=red>•</> <fg=cyan>{$data['table']}</> index [{$data['index']->name}] on ({$cols})");
            }
        }

        // Missing Foreign Keys
        if (!empty($diff->missingForeignKeys)) {
            $this->newLine();
            $this->components->error('Missing Foreign Keys in Live DB:');
            foreach ($diff->missingForeignKeys as $data) {
                $cols = implode(', ', $data['fk']->columns);
                $fCols = implode(', ', $data['fk']->foreignColumns);
                $this->line("  <fg=red>•</> <fg=cyan>{$data['table']}</>->({$cols}) references <fg=cyan>{$data['fk']->foreignTable}</>({$fCols})");
            }
        }
        
    }

    /**
     * Automatically add a migration filename to the skip_migrations array in the config file.
     */
    protected function autoSkipMigration(string $filename): void
    {
        $path = \Illuminate\Support\Facades\App::configPath('schema-sentinel.php');
        
        if (!file_exists($path)) {
            // Fallback to local config if not published (unlikely in production usage)
            return;
        }

        $content = file_get_contents($path);
        
        if (str_contains($content, "'{$filename}'") || str_contains($content, "\"{$filename}\"")) {
            return; // Already skipped
        }

        // Regex to find the skip_migrations array and append the new file
        $pattern = "/(['\"]skip_migrations['\"]\s*=>\s*\[)/";
        $replacement = "$1\n        '{$filename}',";
        
        $newContent = preg_replace($pattern, $replacement, $content);
        
        if ($newContent !== $content) {
            file_put_contents($path, $newContent);
        }
    }
}
