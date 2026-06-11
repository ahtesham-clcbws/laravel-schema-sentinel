<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\DTOs;

/**
 * Represents a database table and all its associated artifacts.
 * 
 * This DTO aggregates columns, indexes, and foreign keys to provide
 * a complete snapshot of a table's structure.
 */
readonly class TableDefinition
{
    /**
     * @param string $name The name of the table.
     * @param array<string, ColumnDefinition> $columns Map of column names to their definitions.
     * @param array<string, IndexDefinition> $indexes Map of index names to their definitions.
     * @param array<string, ForeignKeyDefinition> $foreignKeys Map of FK names to their definitions.
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {}
}
