<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel;

use Illuminate\Support\ServiceProvider;
use Sentinel\SchemaSentinel\Console\Commands\DriftCommand;
use Sentinel\SchemaSentinel\Console\Commands\DoctorCommand;
use Sentinel\SchemaSentinel\Console\Commands\SnapshotCommand;
use Sentinel\SchemaSentinel\Console\Commands\InstallCommand;
use Sentinel\SchemaSentinel\Console\Commands\LintCommand;
use Sentinel\SchemaSentinel\Console\Commands\DocsCommand;
use Sentinel\SchemaSentinel\Console\Commands\StandardizeIndexesCommand;
use Sentinel\SchemaSentinel\Console\Commands\DataDriftCommand;
use Sentinel\SchemaSentinel\Console\Commands\ReverseCommand;
use Sentinel\SchemaSentinel\Console\Commands\HelpCommand;
use Illuminate\Support\Facades\App;

class SentinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('laravel-schema-sentinel', function ($app) {
            return new Sentinel(
                $app->make(\Sentinel\SchemaSentinel\Core\ShadowMigrationRunner::class),
                $app->make(\Sentinel\SchemaSentinel\Core\SchemaParser::class),
                $app->make(\Sentinel\SchemaSentinel\Core\DiffEngine::class),
                $app->make(\Sentinel\SchemaSentinel\Core\ReverseEngineer::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sentinel');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/schema-sentinel.php' => App::configPath('schema-sentinel.php'),
            ], 'schema-sentinel-config');

            $this->commands([
                DriftCommand::class,
                DoctorCommand::class,
                SnapshotCommand::class,
                InstallCommand::class,
                LintCommand::class,
                DocsCommand::class,
                StandardizeIndexesCommand::class,
                DataDriftCommand::class,
                ReverseCommand::class,
                HelpCommand::class,
            ]);
        }

        if (class_exists('Livewire\Livewire')) {
            \Livewire\Livewire::component('sentinel-database-health', \Sentinel\SchemaSentinel\Support\Livewire\DatabaseHealth::class);
        }

        // Register the Pre-Migration Guard
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\MigrationsStarted::class,
            [\Sentinel\SchemaSentinel\Support\PreMigrationGuard::class, 'handle']
        );
    }
}
