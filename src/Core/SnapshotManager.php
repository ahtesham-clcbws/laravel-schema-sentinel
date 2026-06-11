<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Core;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;

/**
 * Manages schema snapshots for faster drift analysis.
 */
class SnapshotManager
{
    protected string $storagePath;

    public function __construct()
    {
        $this->storagePath = storage_path('sentinel/snapshots');
    }

    /**
     * Save a schema definition to a snapshot file.
     */
    public function save(array $schema, string $name = 'latest'): string
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $path = "{$this->storagePath}/{$name}.json";
        File::put($path, json_encode($schema, JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * Load a schema definition from a snapshot file.
     */
    public function load(string $name): array
    {
        $path = str_ends_with($name, '.json') ? $name : "{$this->storagePath}/{$name}.json";

        if (!File::exists($path)) {
            throw new \RuntimeException("Snapshot not found at: {$path}");
        }

        $data = json_decode(File::get($path), true);
        
        // Re-hydrate DTOs
        return $this->hydrate($data);
    }

    protected function hydrate(array $data): array
    {
        $schema = [];
        foreach ($data as $tableName => $tableData) {
            $table = new \Sentinel\SchemaSentinel\DTOs\TableDefinition($tableName);
            
            foreach ($tableData['columns'] as $colName => $c) {
                $table->columns[$colName] = new \Sentinel\SchemaSentinel\DTOs\ColumnDefinition(
                    $c['name'], $c['type'], $c['nullable'], $c['default']
                );
            }

            foreach ($tableData['indexes'] as $idxName => $i) {
                $table->indexes[$idxName] = new \Sentinel\SchemaSentinel\DTOs\IndexDefinition(
                    $i['name'], $i['columns'], $i['type']
                );
            }

            foreach ($tableData['foreignKeys'] as $fkName => $f) {
                $table->foreignKeys[$fkName] = new \Sentinel\SchemaSentinel\DTOs\ForeignKeyDefinition(
                    $f['name'], $f['columns'], $f['foreignTable'], $f['foreignColumns'], $f['onUpdate'], $f['onDelete']
                );
            }

            $schema[$tableName] = $table;
        }

        return $schema;
    }
}
