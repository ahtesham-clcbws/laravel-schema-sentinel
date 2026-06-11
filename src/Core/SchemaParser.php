<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Core;

use Illuminate\Database\Connection;
use Sentinel\SchemaSentinel\DTOs\ColumnDefinition;
use Sentinel\SchemaSentinel\DTOs\TableDefinition;
use Sentinel\SchemaSentinel\DTOs\IndexDefinition;
use Sentinel\SchemaSentinel\DTOs\ForeignKeyDefinition;

/**
 * Extracts and normalizes schema information from a database connection.
 * 
 * It maps raw database metadata into typed DTOs for consistent comparison.
 */
class SchemaParser
{
    /**
     * Parse the entire schema for a given connection.
     * 
     * @param Connection $connection
     * @return array<string, TableDefinition>
     */
    public function parse(Connection $connection): array
    {
        $schema = $connection->getSchemaBuilder();
        $tables = $schema->getTables();
        $definitions = [];
        
        $ignoredTables = \Illuminate\Support\Facades\Config::get('schema-sentinel.ignore_tables', ['migrations']);

        foreach ($tables as $table) {
            // Support both array (Laravel 11/12) and potential objects (Laravel 13+)
            $tableName = is_array($table) ? $table['name'] : $table->name;
            
            if (in_array($tableName, $ignoredTables)) {
                continue;
            }

            // Extract Columns
            $columns = $schema->getColumns($tableName);
            $columnDefinitions = [];
            foreach ($columns as $column) {
                $c = (object) $column; // Cast to object for uniform access
                $columnDefinitions[$c->name] = new ColumnDefinition(
                    name: $c->name,
                    type: $this->normalizeType($c->type),
                    nullable: $c->nullable,
                    default: $c->default,
                    length: $c->length ?? null,
                    unsigned: $c->unsigned ?? false,
                    comment: $c->comment ?? null,
                );
            }

            // Extract Indexes
            $indexes = $schema->getIndexes($tableName);
            $indexDefinitions = [];
            foreach ($indexes as $index) {
                $idx = (object) $index;
                $type = $idx->type ?? 'index';
                if (!empty($idx->primary)) {
                    $type = 'primary';
                } elseif (!empty($idx->unique)) {
                    $type = 'unique';
                }

                $indexDefinitions[$idx->name] = new IndexDefinition(
                    name: $idx->name,
                    type: $type,
                    columns: $idx->columns,
                );
            }

            // Extract Foreign Keys
            $fks = $schema->getForeignKeys($tableName);
            $fkDefinitions = [];
            foreach ($fks as $fk) {
                $f = (object) $fk;
                $name = $f->name ?? ($tableName . '_' . implode('_', $f->columns) . '_foreign');
                $fkDefinitions[$name] = new ForeignKeyDefinition(
                    name: $name,
                    columns: $f->columns,
                    foreignTable: $f->foreign_table,
                    foreignColumns: $f->foreign_columns,
                    onDelete: strtolower($f->on_delete ?? 'no action'),
                    onUpdate: strtolower($f->on_update ?? 'no action'),
                );
            }

            $definitions[$tableName] = new TableDefinition(
                name: $tableName,
                columns: $columnDefinitions,
                indexes: $indexDefinitions,
                foreignKeys: $fkDefinitions,
            );
        }

        return $definitions;
    }

    /**
     * Normalize DB specific types to a common standard for accurate diffing.
     */
    protected function normalizeType(string $type): string
    {
        return strtolower($type);
    }
}
