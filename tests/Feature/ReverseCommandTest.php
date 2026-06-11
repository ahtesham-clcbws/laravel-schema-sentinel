<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Sentinel\SchemaSentinel\Tests\TestCase;

class ReverseCommandTest extends TestCase
{
    protected string $tempDir;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->tempDir = __DIR__ . '/../../scratch/reverse_test_' . time();
        File::ensureDirectoryExists($this->tempDir);

        $app['config']->set('database.default', 'reverser_db');
        $app['config']->set('database.connections.reverser_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_it_reverse_engineers_database_into_laravel_files()
    {
        // 1. Create table structure with foreign keys
        Schema::create('test_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('test_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status'); // enum candidate
            $table->unsignedBigInteger('admin_id');
            $table->foreign('admin_id')->references('id')->on('test_admins')->onDelete('cascade');
        });

        // 2. Run schema:reverse command pointing to our temp directory
        $this->artisan("schema:reverse --path={$this->tempDir}")
            ->expectsOutputToContain('Reverse-engineering database schema...')
            ->expectsOutputToContain('TestAdmin.php')
            ->expectsOutputToContain('TestPost.php')
            ->assertExitCode(0);

        // 3. Verify generated Model files
        $this->assertTrue(File::exists("{$this->tempDir}/app/Models/TestAdmin.php"));
        $this->assertTrue(File::exists("{$this->tempDir}/app/Models/TestPost.php"));

        // Verify model relations and enum casts code
        $postModelContent = File::get("{$this->tempDir}/app/Models/TestPost.php");
        $this->assertStringContainsString('public function admin()', $postModelContent);
        $this->assertStringContainsString('belongsTo', $postModelContent);
        $this->assertStringContainsString('TestPostStatus::class', $postModelContent);

        // Verify generated migrations
        $migrations = File::files("{$this->tempDir}/database/migrations");
        $this->assertCount(2, $migrations);

        // Verify generated enums
        $this->assertTrue(File::exists("{$this->tempDir}/app/Enums/TestPostStatus.php"));
    }
}
