<?php

namespace Sentinel\SchemaSentinel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sentinel\SchemaSentinel\SentinelServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            SentinelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        \Illuminate\Support\Facades\Config::set('database.default', 'testing');
        \Illuminate\Support\Facades\Config::set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
