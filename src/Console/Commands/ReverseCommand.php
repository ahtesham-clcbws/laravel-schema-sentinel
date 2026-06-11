<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Sentinel\SchemaSentinel\Facades\Sentinel;

/**
 * Intelligent reverse-engineering bridge.
 */
#[Signature('schema:reverse {--path= : Base export directory (defaults to project root)} {--models : Generate Eloquent Models} {--migrations : Generate Migration files} {--seeders : Generate Database Seeders} {--enums : Generate PHP 8.4 Backed Enums for enum columns}')]
#[Description('Reverse engineer database schema into clean migrations, models, seeders, and PHP 8.4 Enums.')]
class ReverseCommand extends Command
{
    /**
     * The console command help.
     */
    protected $help = "Reverse engineer database schema into clean Laravel codebase migrations, models, seeders, and PHP 8.4 Enums.

Examples:
  <fg=green>php artisan schema:reverse --path=database/reverse</>
  <fg=green>php artisan schema:reverse --models --enums</>

Options:
  --path=directory    Base export directory (defaults to project root).
  --models            Only generate Eloquent Models with typed relations and casts.
  --migrations        Only generate database migration blueprint files.
  --seeders           Only generate database seeder classes with record values.
  --enums             Only generate PHP 8.4 Backed Enums for status/type columns.";

    public function handle(): int
    {
        $basePath = $this->option('path') ? rtrim($this->option('path'), '/') : base_path();

        $generateModels = $this->option('models');
        $generateMigrations = $this->option('migrations');
        $generateSeeders = $this->option('seeders');
        $generateEnums = $this->option('enums');

        // If no specific options are chosen, do all of them
        if (!$generateModels && !$generateMigrations && !$generateSeeders && !$generateEnums) {
            $generateModels = $generateMigrations = $generateSeeders = $generateEnums = true;
        }

        $this->components->info('Reverse-engineering database schema...');

        $results = Sentinel::reverse([
            'path' => $basePath,
            'models' => $generateModels,
            'migrations' => $generateMigrations,
            'seeders' => $generateSeeders,
            'enums' => $generateEnums,
        ]);

        $this->newLine();

        if (!empty($results['migrations'])) {
            $this->components->info('Generated Migrations:');
            foreach ($results['migrations'] as $path) {
                $this->line("  <fg=green>✔</> " . basename($path));
            }
            $this->newLine();
        }

        if (!empty($results['enums'])) {
            $this->components->info('Generated Enums:');
            foreach ($results['results'] ?? $results['enums'] as $path) {
                $this->line("  <fg=green>✔</> " . basename($path));
            }
            $this->newLine();
        }

        if (!empty($results['models'])) {
            $this->components->info('Generated Models:');
            foreach ($results['models'] as $path) {
                $this->line("  <fg=green>✔</> " . basename($path));
            }
            $this->newLine();
        }

        if (!empty($results['seeders'])) {
            $this->components->info('Generated Seeders:');
            foreach ($results['seeders'] as $path) {
                $this->line("  <fg=green>✔</> " . basename($path));
            }
            $this->newLine();
        }

        $this->components->info('Reverse engineering completed successfully!');
        return 0;
    }
}
