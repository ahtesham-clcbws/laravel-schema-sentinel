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
 * and the "Ideal" code-defined state without emitting false positives
 * caused by database-specific metadata dialects.
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
            if (!isset($live[$tableName])) {
                continue;
            }

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
                        $mismatchedColumns["$tableName.$colName"] = [
                            'table' => $tableName,
                            'live' => $liveCol,
                            'ideal' => $idealCol,
                            'diffs' => $diffs
                        ];
                    }
                }
            }

            // 2. Index Analysis (with Normalization)
            foreach ($idealTable->indexes as $idealIdxName => $idealIdx) {
                $foundMatch = false;
                $idealCols = array_values($idealIdx->columns);

                foreach ($liveTable->indexes as $liveIdxName => $liveIdx) {
                    $liveCols = array_values($liveIdx->columns);

                    // Check if columns and index type match
                    if ($idealCols === $liveCols && $this->normalizeIndexType($idealIdx->type) === $this->normalizeIndexType($liveIdx->type)) {
                        $foundMatch = true;
                        break;
                    }
                }
                
                if (!$foundMatch) {
                    $missingIndexes["$tableName.$idealIdxName"] = ['table' => $tableName, 'index' => $idealIdx];
                }
            }

            // 3. Foreign Key Analysis
            foreach ($idealTable->foreignKeys as $idealFkName => $idealFk) {
                $foundFk = false;
                $idealLocalCols = array_values($idealFk->columns);
                $idealTargetCols = array_values($idealFk->foreignColumns);

                foreach ($liveTable->foreignKeys as $liveFkName => $liveFk) {
                    $liveLocalCols = array_values($liveFk->columns);
                    $liveTargetCols = array_values($liveFk->foreignColumns);

                    if (
                        $idealLocalCols === $liveLocalCols &&
                        strtolower($idealFk->foreignTable) === strtolower($liveFk->foreignTable) &&
                        $idealTargetCols === $liveTargetCols
                    ) {
                        // Compare referential actions if specified
                        if ($this->normalizeFkAction($idealFk->onDelete) === $this->normalizeFkAction($liveFk->onDelete) &&
                            $this->normalizeFkAction($idealFk->onUpdate) === $this->normalizeFkAction($liveFk->onUpdate)) {
                            $foundFk = true;
                            break;
                        }
                    }
                }

                if (!$foundFk) {
                    $missingForeignKeys["$tableName.$idealFkName"] = ['table' => $tableName, 'fk' => $idealFk];
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
        
        $liveType = $this->normalizeType($live->type);
        $idealType = $this->normalizeType($ideal->type);

        if ($liveType !== $idealType) {
            $diffs['type'] = ['live' => $live->type, 'ideal' => $ideal->type];
        }

        if ($live->nullable !== $ideal->nullable) {
            $diffs['nullable'] = ['live' => $live->nullable, 'ideal' => $ideal->nullable];
        }

        $normLiveDefault = $this->normalizeDefault($live->default);
        $normIdealDefault = $this->normalizeDefault($ideal->default);

        if ($normLiveDefault !== $normIdealDefault) {
            $diffs['default'] = ['live' => $live->default, 'ideal' => $ideal->default];
        }

        return $diffs;
    }

    /**
     * Map driver-specific types to canonical comparison types.
     */
    public function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        // Strip length/precision modifiers for base type equivalence (e.g., varchar(255) -> varchar)
        if (preg_match('/^([a-z_]+)\s*\(/i', $type, $matches)) {
            $type = $matches[1];
        }

        return match ($type) {
            'int', 'integer', 'int4' => 'integer',
            'bigint', 'int8', 'bigserial' => 'bigint',
            'smallint', 'int2' => 'smallint',
            'tinyint', 'bool', 'boolean' => 'boolean',
            'varchar', 'string', 'character varying', 'char', 'nvarchar' => 'string',
            'text', 'longtext', 'mediumtext', 'tinytext' => 'text',
            'datetime', 'timestamp', 'timestamptz', 'datetime2' => 'datetime',
            'double', 'float', 'real', 'float4', 'float8' => 'float',
            'numeric', 'decimal' => 'decimal',
            'json', 'jsonb' => 'json',
            default => $type,
        };
    }

    protected function normalizeIndexType(string $type): string
    {
        $type = strtolower($type);
        return match ($type) {
            'primary', 'primary key' => 'primary',
            'unique' => 'unique',
            default => 'index',
        };
    }

    protected function normalizeFkAction(?string $action): string
    {
        $action = strtolower(trim($action ?? 'no action'));
        return match ($action) {
            'restrict', 'no action', '' => 'no action',
            'cascade' => 'cascade',
            'set null' => 'set null',
            'set default' => 'set default',
            default => $action,
        };
    }

    /**
     * Normalize default values across dialect differences.
     */
    public function normalizeDefault(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            // Handle repeated SQL quoted strings like '''Guest''' or "'Guest'" from diverse dialects
            while (
                (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'") && strlen($trimmed) >= 2) ||
                (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"') && strlen($trimmed) >= 2)
            ) {
                $trimmed = substr($trimmed, 1, -1);
            }
            // Strip casting syntax in Postgres defaults (e.g. 'draft'::character varying or false::boolean)
            if (str_contains($trimmed, '::')) {
                $trimmed = explode('::', $trimmed)[0];
                while (
                    (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'") && strlen($trimmed) >= 2) ||
                    (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"') && strlen($trimmed) >= 2)
                ) {
                    $trimmed = substr($trimmed, 1, -1);
                }
            }

            if (strtolower($trimmed) === 'null') {
                return null;
            }
            if (strtolower($trimmed) === 'true') {
                return '1';
            }
            if (strtolower($trimmed) === 'false') {
                return '0';
            }

            return $trimmed;
        }

        return $value;
    }
}
