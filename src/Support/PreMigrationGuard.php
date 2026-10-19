<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Support;

use Illuminate\Database\Events\MigrationsStarted;
use Sentinel\SchemaSentinel\Facades\Sentinel;
use Sentinel\SchemaSentinel\Exceptions\MigrationBlockedException;

/**
 * Prevents migrations from running if the database is in a drifted state.
 */
class PreMigrationGuard
{
    /**
     * Internal flag to deterministically bypass the guard during shadow simulations or explicit bypass.
     */
    protected static bool $bypassed = false;

    public static function bypass(bool $state = true): void
    {
        self::$bypassed = $state;
    }

    public static function isBypassed(): bool
    {
        return self::$bypassed;
    }

    public function handle(MigrationsStarted $event): void
    {
        if (!config('schema-sentinel.guard.enabled', false)) {
            return;
        }

        // Deterministic bypass during shadow simulation or when explicitly bypassed
        if (self::$bypassed) {
            return;
        }

        // Avoid infinite recursion or blocking Sentinel internal commands
        $argvString = implode(' ', $_SERVER['argv'] ?? []);
        if (app()->runningInConsole() && (str_contains($argvString, 'schema:') || str_contains($argvString, 'vendor:publish'))) {
            return;
        }

        // Track previous bypass state to ensure nested operations do not leak or reset incorrectly
        $previousBypass = self::$bypassed;
        self::bypass(true);
        try {
            $diff = Sentinel::check();
        } finally {
            self::bypass($previousBypass);
        }

        if ($diff->hasDifferences()) {
            throw new MigrationBlockedException(
                "Sentinel Guard blocked this migration: schema drift detected between migrations and the live database. Run 'php artisan schema:drift' to review."
            );
        }
    }
}
