<?php

namespace Sentinel\SchemaSentinel\DTOs;

readonly class SchemaDiff
{
    /**
     * @param array<string, TableDefinition> $missingTables
     * @param array<string, TableDefinition> $extraTables
     * @param array<string, array{table: string, column: ColumnDefinition}> $missingColumns
     * @param array<string, array{table: string, column: ColumnDefinition}> $extraColumns
     * @param array<string, array{table: string, live: ColumnDefinition, ideal: ColumnDefinition, diffs: array}> $mismatchedColumns
     * @param array<string, array{table: string, index: IndexDefinition}> $missingIndexes
     * @param array<string, array{table: string, fk: ForeignKeyDefinition}> $missingForeignKeys
     */
    public function __construct(
        public array $missingTables = [],
        public array $extraTables = [],
        public array $missingColumns = [],
        public array $extraColumns = [],
        public array $mismatchedColumns = [],
        public array $missingIndexes = [],
        public array $missingForeignKeys = [],
    ) {}

    public function hasDifferences(): bool
    {
        return !empty($this->missingTables) || 
               !empty($this->extraTables) || 
               !empty($this->missingColumns) || 
               !empty($this->extraColumns) || 
               !empty($this->mismatchedColumns) ||
               !empty($this->missingIndexes) ||
               !empty($this->missingForeignKeys);
    }
}
