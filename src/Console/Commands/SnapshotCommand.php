<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Sentinel\SchemaSentinel\Core\ShadowMigrationRunner;
use Sentinel\SchemaSentinel\Core\SchemaParser;
use Sentinel\SchemaSentinel\Core\SnapshotManager;

/**
 * Creates a schema snapshot from current migrations.
 */
#[Signature('schema:snapshot {name=latest : Name of the snapshot file}')]
#[Description('Create a schema snapshot from your current migration files.')]
class SnapshotCommand extends Command
{
    /**
     * The console command help.
     */
    protected $help = "Create a schema snapshot from your current migration files.

Examples:
  <fg=green>php artisan schema:snapshot</>
  <fg=green>php artisan schema:snapshot release-v1.0</>

Arguments:
  name                The name of the snapshot file (defaults to latest).";

    public function handle(
        ShadowMigrationRunner $shadowRunner,
        SchemaParser $parser,
        SnapshotManager $snapshotManager
    ): int {
        $this->components->info('Creating Schema Snapshot...');

        $shadowConn = null;
        
        $this->components->task('Simulating migrations on Shadow DB', function () use ($shadowRunner, &$shadowConn) {
            $shadowConn = $shadowRunner->run();
        });

        $schema = [];
        $this->components->task('Parsing schema', function () use ($parser, $shadowConn, &$schema) {
            if (!$shadowConn instanceof \Illuminate\Database\Connection) {
                throw new \RuntimeException('Shadow connection failed to initialize.');
            }
            $schema = $parser->parse($shadowConn);
        });

        $path = '';
        $this->components->task('Saving snapshot', function () use ($snapshotManager, $schema, &$path) {
            $path = $snapshotManager->save($schema, $this->argument('name'));
        });

        $this->newLine();
        $this->components->info("Snapshot created successfully at: " . basename($path));
        
        $shadowRunner->cleanup();

        return 0;
    }
}
