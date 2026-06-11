<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Core;

use Sentinel\SchemaSentinel\DTOs\SchemaDiff;
use Sentinel\SchemaSentinel\DTOs\TableDefinition;
use Sentinel\SchemaSentinel\DTOs\ColumnDefinition;

/**
 * The analytical core that compares two schema blueprints.
 * 
 * It identifies discrepancies between the "Live" database state 
 * and the "Ideal" code-defined state.
 */
class DiffEngine
{
    /**
     * Compare two schema definitions.
     * 
     * @param array<string, TableDefinition> $live
     * @param array<string, TableDefinition> $ideal
     * @param bool $strict If true, reports artifacts present in Live but missing in Ideal.
     * @return SchemaDiff
     */
    public function compare(array $live, array $ideal, bool $strict = false): SchemaDiff
    {
        $missingTables = array_diff_key($ideal, $live);
        $extraTables = $strict ? array_diff_key($live, $ideal) : [];
        
        $missingColumns = [];
        $extraColumns = [];
        $mismatchedColumns = [];
        $missingIndexes = [];
        $missingForeignKeys = [];

        foreach ($ideal as $tableName => $idealTable) {
            if (isset($live[$tableName])) {
                $liveTable = $live[$tableName];
                
                // 1. Column Analysis
                $missing = array_diff_key($idealTable->columns, $liveTable->columns);
                foreach ($missing as $colName => $col) {
                    $missingColumns["$tableName.$colName"] = ['table' => $tableName, 'column' => $col];
                }

                if ($strict) {
                    $extra = array_diff_key($liveTable->columns, $idealTable->columns);
                    foreach ($extra as $colName => $col) {
                        $extraColumns["$tableName.$colName"] = ['table' => $tableName, 'column' => $col];
                    }
                }

                foreach ($idealTable->columns as $colName => $idealCol) {
                    if (isset($liveTable->columns[$colName])) {
                        $liveCol = $liveTable->columns[$colName];
                        $diffs = $this->getColumnDiffs($liveCol, $idealCol);
                        if (!empty($diffs)) {
                            $mismatchedColumns["$tableName.$colName"] = ['table' => $tableName, 'live' => $liveCol, 'ideal' => $idealCol, 'diffs' => $diffs];
                        }
                    }
                }

                // 2. Index Analysis (with Normalization)
                foreach ($idealTable->indexes as $idealIdxName => $idealIdx) {
                    $foundMatch = false;
                    foreach ($liveTable->indexes as $liveIdxName => $liveIdx) {
                        // Check if columns and type match (Normalization)
                        if ($idealIdx->columns == $liveIdx->columns && $idealIdx->type == $liveIdx->type) {
                            $foundMatch = true;
                            
                            // If names are different, we could report it, but for now we treat as match
                            // to avoid redundant index creation.
                            break;
                        }
                    }
                    
                    if (!$foundMatch) {
                        $missingIndexes["$tableName.$idealIdxName"] = ['table' => $tableName, 'index' => $idealIdx];
                    }
                }

                // 3. Foreign Key Analysis
                $missingFks = array_diff_key($idealTable->foreignKeys, $liveTable->foreignKeys);
                foreach ($missingFks as $fkName => $fk) {
                    $missingForeignKeys["$tableName.$fkName"] = ['table' => $tableName, 'fk' => $fk];
                }
            }
        }

        return new SchemaDiff(
            missingTables: $missingTables,
            extraTables: $extraTables,
            missingColumns: $missingColumns,
            extraColumns: $extraColumns,
            mismatchedColumns: $mismatchedColumns,
            missingIndexes: $missingIndexes,
            missingForeignKeys: $missingForeignKeys
        );
    }

    /**
     * Identify specific attribute differences between two columns.
     */
    protected function getColumnDiffs(ColumnDefinition $live, ColumnDefinition $ideal): array
    {
        $diffs = [];
        
        if ($live->type !== $ideal->type) {
            $diffs['type'] = ['live' => $live->type, 'ideal' => $ideal->type];
        }

        if ($live->nullable !== $ideal->nullable) {
            $diffs['nullable'] = ['live' => $live->nullable, 'ideal' => $ideal->nullable];
        }

        if ($this->normalizeDefault($live->default) !== $this->normalizeDefault($ideal->default)) {
            $diffs['default'] = ['live' => $live->default, 'ideal' => $ideal->default];
        }

        return $diffs;
    }

    /**
     * Normalize default values for comparison.
     */
    protected function normalizeDefault(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value, "'");
        }
        return $value;
    }
}
