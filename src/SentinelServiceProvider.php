<?php

namespace Sentinel\SchemaSentinel;

use Illuminate\Support\ServiceProvider;
use Sentinel\SchemaSentinel\Console\Commands\DriftCommand;
use Sentinel\SchemaSentinel\Console\Commands\DoctorCommand;
use Illuminate\Support\Facades\App;

class SentinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('laravel-schema-sentinel', function ($app) {
            return new Sentinel(
                $app->make(\Sentinel\SchemaSentinel\Core\ShadowMigrationRunner::class),
                $app->make(\Sentinel\SchemaSentinel\Core\SchemaParser::class),
                $app->make(\Sentinel\SchemaSentinel\Core\DiffEngine::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/schema-sentinel.php' => App::configPath('schema-sentinel.php'),
            ], 'schema-sentinel-config');

            $this->commands([
                DriftCommand::class,
                DoctorCommand::class,
            ]);
        }
    }
}
