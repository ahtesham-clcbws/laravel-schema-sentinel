<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;

/**
 * Handles the installation and intelligent upgrading of the package configuration.
 */
#[Signature('schema:sentinel-install')]
#[Description('Install or upgrade the Sentinel configuration file without losing existing settings.')]
class InstallCommand extends Command
{
    /**
     * The console command help.
     */
    protected $help = "Install or upgrade the Sentinel configuration file without losing existing settings.

Examples:
  <fg=green>php artisan schema:sentinel-install</>";

    public function handle(): int
    {
        \Sentinel\SchemaSentinel\Support\Telemetry::dispatch();

        $this->components->info('Schema Sentinel Installation/Upgrade');

        $configPath = App::configPath('schema-sentinel.php');
        $packageConfigPath = __DIR__.'/../../../config/schema-sentinel.php';

        if (!File::exists($configPath)) {
            $this->components->task('Publishing initial configuration', function () {
                $this->call('vendor:publish', ['--tag' => 'schema-sentinel-config']);
            });
            return 0;
        }

        $this->components->task('Checking for missing configuration options', function () use ($configPath, $packageConfigPath) {
            $this->upgradeConfig($configPath, $packageConfigPath);
        });

        if (class_exists('Livewire\Livewire')) {
            $this->newLine();
            $this->line("  <fg=magenta>💡 Tip:</> You can use the Visual Dashboard in your Blade files:");
            $this->line("     <fg=gray><livewire:sentinel-database-health /></>");
        }

        $this->newLine();
        $this->components->info('Sentinel is up to date and ready!');

        return 0;
    }

    /**
     * Intelligently merges new config options into the user's existing config file.
     */
    protected function upgradeConfig(string $userPath, string $packagePath): void
    {
        $userContent = File::get($userPath);
        $packageContent = File::get($packagePath);

        // List of keys introduced in recent versions
        $newOptions = [
            'skip_migrations' => 'Skip Migrations',
            'data_audit_tables' => 'Data Consistency Audit',
            'custom_types' => 'Custom Type Mapping',
            'notifications' => 'Notifications',
            'guard' => 'Pre-Migration Guard',
            'index_standards' => 'Index Standardization Settings',
        ];

        $updated = false;

        foreach ($newOptions as $key => $title) {
            // If the key is missing from user config, try to import the block from package config
            if (!str_contains($userContent, "'$key'") && !str_contains($userContent, "\"$key\"")) {
                
                // Find the full configuration block (including comments) in the package config
                $pattern = "/\/\*\s*\|-+\s*\| {$title}.*?['\"]{$key}['\"]\s*=>\s*\[.*?\]\s*,/s";
                
                if (preg_match($pattern, $packageContent, $matches)) {
                    $block = "\n    " . trim($matches[0]);
                    
                    // Insert the new block before the final closing bracket
                    $userContent = preg_replace("/(\]\s*;\s*)$/", "{$block}\n\n$1", $userContent);
                    $updated = true;
                    $this->newLine();
                    $this->line("  <fg=green>✔</> Added missing option: <fg=cyan>{$key}</>");
                }
            }
        }

        if ($updated) {
            File::put($userPath, $userContent);
        }
    }
}
