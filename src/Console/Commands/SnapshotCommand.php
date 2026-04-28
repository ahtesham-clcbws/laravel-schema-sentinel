<?php

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Sentinel\SchemaSentinel\Core\ShadowMigrationRunner;
use Sentinel\SchemaSentinel\Core\SchemaParser;
use Sentinel\SchemaSentinel\Core\SnapshotManager;

/**
 * Creates a schema snapshot from current migrations.
 */
class SnapshotCommand extends Command
{
    protected $signature = 'schema:snapshot {name=latest : Name of the snapshot file}';
    protected $description = 'Create a schema snapshot from your current migration files.';

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
