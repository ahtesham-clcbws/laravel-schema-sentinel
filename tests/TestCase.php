<?php

declare(strict_types=1);

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
        $driver = env('DB_DRIVER', 'sqlite');

        if ($driver === 'pgsql') {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'sentinel_pg_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'password'),
                'prefix' => '',
                'schema' => 'public',
            ]);
        } elseif ($driver === 'mysql') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'sentinel_my_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'password'),
                'prefix' => '',
            ]);
        } else {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }
    }
}
