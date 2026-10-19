<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Sentinel\SchemaSentinel\Exceptions\MigrationBlockedException;
use Sentinel\SchemaSentinel\Support\PreMigrationGuard;
use Sentinel\SchemaSentinel\Tests\TestCase;

class PreMigrationGuardTest extends TestCase
{
    public function test_it_does_nothing_when_guard_is_disabled(): void
    {
        Config::set('schema-sentinel.guard.enabled', false);

        $guard = new PreMigrationGuard();
        $event = new MigrationsStarted('up');

        // Should not throw any exception
        $guard->handle($event);
        $this->assertTrue(true);
    }

    public function test_it_bypasses_when_bypass_flag_is_set(): void
    {
        Config::set('schema-sentinel.guard.enabled', true);
        PreMigrationGuard::bypass(true);

        $guard = new PreMigrationGuard();
        $event = new MigrationsStarted('up');

        try {
            $guard->handle($event);
            $this->assertTrue(true);
        } finally {
            PreMigrationGuard::bypass(false);
        }
    }
}
