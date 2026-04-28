<?php

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Audits migration files for anti-patterns and drift-prone code.
 */
class LintCommand extends Command
{
    protected $signature = 'schema:sentinel-lint';
    protected $description = 'Audit your migration files for potential drift-causing patterns.';

    public function handle(): int
    {
        $this->components->info('Auditing Migration Files...');

        $migrationFiles = File::files(database_path('migrations'));
        $issues = 0;

        foreach ($migrationFiles as $file) {
            $content = File::get($file->getPathname());
            $filename = $file->getFilename();

            // 1. Check for DB::statement
            if (str_contains($content, 'DB::statement')) {
                $this->warnIssue($filename, 'Uses raw DB::statement. This can cause drift as Sentinel may not parse complex raw SQL perfectly.');
                $issues++;
            }

            // 2. Check for hardcoded platform-specific defaults
            if (preg_match("/->default\(['\"]CURRENT_TIMESTAMP['\"]\)/i", $content)) {
                $this->warnIssue($filename, 'Uses hardcoded CURRENT_TIMESTAMP. Use ->useCurrent() for better cross-platform compatibility.');
                $issues++;
            }

            // 3. Check for Schema::drop (without ifExists)
            if (str_contains($content, 'Schema::drop(') && !str_contains($content, 'Schema::dropIfExists(')) {
                $this->warnIssue($filename, 'Uses Schema::drop() instead of Schema::dropIfExists(). This might crash during fresh simulations.');
                $issues++;
            }
        }

        if ($issues === 0) {
            $this->components->info('No major issues found in your migrations. Good job!');
        } else {
            $this->newLine();
            $this->components->warn("Found {$issues} potential issues. Addressing these will make Sentinel even more accurate.");
        }

        return 0;
    }

    protected function warnIssue(string $file, string $message): void
    {
        $this->line("  <fg=yellow>⚠</> <fg=cyan>{$file}</>");
        $this->line("    <fg=gray>{$message}</>");
        $this->newLine();
    }
}
