<?php

namespace Sentinel\SchemaSentinel\Support;

use Illuminate\Database\Events\MigrationsStarted;
use Sentinel\SchemaSentinel\Facades\Sentinel;
use Illuminate\Support\Facades\Artisan;

/**
 * Prevents migrations from running if the database is in a drifted state.
 */
class PreMigrationGuard
{
    public function handle(MigrationsStarted $event): void
    {
        if (!config('schema-sentinel.guard.enabled')) {
            return;
        }

        // We skip the guard check if it's already a Sentinel shadow migration
        // or if we are in the middle of a drift check to avoid infinite loops.
        if (str_contains(request()->fullUrl() ?? '', 'sentinel') || app()->runningInConsole() && str_contains(implode(' ', $_SERVER['argv'] ?? []), 'schema:')) {
            return;
        }

        $diff = Sentinel::check();

        if ($diff->hasDifferences()) {
            echo "\n\033[41m ERROR \033[0m \033[31mSchema Drift Detected!\033[0m\n";
            echo "Sentinel Guard has blocked this migration because your Live DB is out of sync.\n";
            echo "Please run \033[36mphp artisan schema:drift\033[0m first.\n\n";
            exit(1);
        }
    }
}
