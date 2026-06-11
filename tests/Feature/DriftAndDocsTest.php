<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Sentinel\SchemaSentinel\Tests\TestCase;

class DriftAndDocsTest extends TestCase
{
    protected string $sqlitePath;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->sqlitePath = __DIR__ . '/../../scratch/drift_test_' . time() . '.sqlite';
        File::ensureDirectoryExists(dirname($this->sqlitePath));
        touch($this->sqlitePath);

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => $this->sqlitePath,
            'prefix' => '',
        ]);
        $app['config']->set('schema-sentinel.ignore_tables', ['migrations']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->sqlitePath)) {
            unlink($this->sqlitePath);
        }
        parent::tearDown();
    }

    public function test_it_runs_schema_drift_command_without_hangs()
    {
        // 1. Create a table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 2. Run drift detection
        $this->artisan('schema:drift --strict')
            ->expectsOutputToContain('Extra Tables in Live DB')
            ->assertExitCode(1); // Exit code 1 because drift was detected
    }

    public function test_it_generates_database_documentation_without_crashes()
    {
        // 1. Create a table with index and foreign key
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles');
            $table->index('email', 'members_email_idx');
        });

        $outputPath = base_path('DATABASE_TEST.md');
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        // 2. Run docs command
        $this->artisan('schema:docs --output=DATABASE_TEST.md')
            ->expectsOutputToContain('Documentation generated successfully')
            ->assertExitCode(0);

        // 3. Assert file exists and contains correct markup
        $this->assertTrue(File::exists($outputPath));
        $content = File::get($outputPath);
        $this->assertStringContainsString('members_email_idx', $content);
        $this->assertStringContainsString('role_id', $content);

        // Clean up
        File::delete($outputPath);
    }
}
