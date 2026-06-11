<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Sentinel\SchemaSentinel\Tests\TestCase;

class DataDriftTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        
        // Define local connection
        $app['config']->set('database.connections.localdb', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Define target comparison connection
        $app['config']->set('database.connections.targetdb', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('database.default', 'localdb');
        $app['config']->set('schema-sentinel.data_audit_tables', ['test_settings']);
    }

    public function test_it_detects_data_drift()
    {
        // 1. Create table locally and in target DB
        Schema::connection('localdb')->create('test_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value');
        });

        Schema::connection('targetdb')->create('test_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value');
        });

        // 2. Seed slightly different data
        DB::connection('localdb')->table('test_settings')->insert([
            ['id' => 1, 'key' => 'site_name', 'value' => 'Local Site'],
            ['id' => 2, 'key' => 'debug_mode', 'value' => 'true'],
        ]);

        DB::connection('targetdb')->table('test_settings')->insert([
            ['id' => 1, 'key' => 'site_name', 'value' => 'Production Site'], // Mismatch
            ['id' => 3, 'key' => 'maintenance', 'value' => 'false'],        // Missing locally
        ]);

        // Run data drift comparison against targetdb connection
        $this->artisan('schema:data-drift --compare-env=targetdb')
            ->expectsOutputToContain('Production Site')
            ->expectsOutputToContain('Missing rows')
            ->assertExitCode(1); // Exit code 1 indicates drift was detected
    }
}
