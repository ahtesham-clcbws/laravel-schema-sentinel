<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Str;

/**
 * Intelligent helper and command spellchecker.
 */
#[Signature('schema:help {command_name? : The command to get help for}')]
#[Description('Intelligent help board and spelling suggestion advisor for Schema Sentinel.')]
class HelpCommand extends Command
{
    public function handle(): int
    {
        $query = $this->argument('command_name');

        // Dynamically resolve all registered Sentinel commands under the schema: namespace
        $schemaCommands = [];
        foreach ($this->getApplication()->all() as $name => $cmd) {
            if (str_starts_with($name, 'schema:')) {
                $schemaCommands[$name] = [
                    'name' => $name,
                    'description' => $cmd->getDescription(),
                    'help' => $cmd->getHelp(),
                ];
            }
        }

        if (!$query) {
            $this->displayWelcomeBoard($schemaCommands);
            return 0;
        }

        // Normalize namespaces (allow typing drift, schema:drift, help, etc)
        $normalizedQuery = str_contains($query, ':') ? $query : 'schema:' . $query;
        if (!str_starts_with($normalizedQuery, 'schema:')) {
            $normalizedQuery = 'schema:' . $normalizedQuery;
        }

        // 1. Exact Match
        if (isset($schemaCommands[$normalizedQuery])) {
            $this->displayHelpFor($schemaCommands[$normalizedQuery]);
            return 0;
        }

        // 2. Levenshtein Look-alike matching
        $closest = null;
        $shortest = -1;

        foreach (array_keys($schemaCommands) as $cmdName) {
            $lev = levenshtein($normalizedQuery, $cmdName);

            if ($lev === 0) {
                $closest = $cmdName;
                $shortest = 0;
                break;
            }

            if ($lev <= $shortest || $shortest < 0) {
                $closest = $cmdName;
                $shortest = $lev;
            }
        }

        // If distance is small enough (threshold of 4 characters), suggest it
        if ($closest && $shortest <= 4) {
            $this->newLine();
            $this->components->warn("Command \"{$query}\" not found. Did you mean \"{$closest}\"?");
            $this->newLine();
            $this->displayHelpFor($schemaCommands[$closest]);
            return 0;
        }

        $this->components->error("Command \"{$query}\" not recognized in Schema Sentinel.");
        $this->displayWelcomeBoard($schemaCommands);
        return 1;
    }

    protected function displayWelcomeBoard(array $commands): void
    {
        $this->components->info('Laravel Schema Sentinel - Interactive Help Board');
        $this->line('  Retrieve detailed command information by running:');
        $this->line('  <fg=cyan>php artisan schema:help {command}</>');
        $this->newLine();

        $this->components->info('Available Commands:');

        foreach ($commands as $name => $info) {
            $nameStr = str_pad($name, 30);
            $this->line("  <fg=green>{$nameStr}</> {$info['description']}");
        }

        $this->newLine();
    }

    protected function displayHelpFor(array $cmd): void
    {
        $this->components->info("Command: {$cmd['name']}");
        $this->line("  <fg=gray>{$cmd['description']}</>");
        $this->newLine();

        if ($cmd['help']) {
            $this->line($cmd['help']);
            $this->newLine();
        } else {
            $this->line("  No detailed help examples configured for this command.");
            $this->newLine();
        }
    }
}
