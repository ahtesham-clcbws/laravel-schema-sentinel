<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Sentinel\SchemaSentinel\Tests\TestCase;

class StandardizeIndexesTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        
        // Define a test connection using SQLite
        $app['config']->set('database.default', 'testdb');
        $app['config']->set('database.connections.testdb', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_it_detects_non_standard_indexes_and_redundant_indexes()
    {
        // Set up test tables and indexes
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');

            // Non-standard index name
            $table->index('email', 'custom_email_index_name');

            // Composite index
            $table->index(['first_name', 'last_name'], 'composite_first_last');
            
            // Redundant index (covered by the composite prefix 'first_name')
            $table->index('first_name', 'redundant_first_name_idx');
        });

        $this->artisan('schema:standardize-indexes')
            ->expectsOutputToContain('custom_email_index_name')
            ->expectsOutputToContain('redundant_first_name_idx')
            ->assertExitCode(0);
    }
}
