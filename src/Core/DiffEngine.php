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
        $changedForeignKeys = [];

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

                    // Check if columns and index type match (preserving column order)
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
                $idealLocalCols = array_values($idealFk->columns);
                $idealTargetCols = array_values($idealFk->foreignColumns);

                $matchingFk = null;
                $hasStructuralMatch = false;

                foreach ($liveTable->foreignKeys as $liveFkName => $liveFk) {
                    $liveLocalCols = array_values($liveFk->columns);
                    $liveTargetCols = array_values($liveFk->foreignColumns);

                    // A structural match occurs if local columns match (or constraint name matches on same local columns)
                    if (
                        $idealLocalCols === $liveLocalCols ||
                        (strtolower($idealFkName) === strtolower($liveFkName) && $idealLocalCols === $liveLocalCols)
                    ) {
                        $hasStructuralMatch = true;
                        $matchingFk = $liveFk;

                        $diffs = [];
                        if (strtolower($idealFk->foreignTable) !== strtolower($liveFk->foreignTable)) {
                            $diffs['foreign_table'] = ['live' => $liveFk->foreignTable, 'ideal' => $idealFk->foreignTable];
                        }
                        if ($idealTargetCols !== $liveTargetCols) {
                            $diffs['foreign_columns'] = ['live' => $liveTargetCols, 'ideal' => $idealTargetCols];
                        }
                        if ($this->normalizeFkAction($idealFk->onDelete) !== $this->normalizeFkAction($liveFk->onDelete)) {
                            $diffs['on_delete'] = ['live' => $liveFk->onDelete, 'ideal' => $idealFk->onDelete];
                        }
                        if ($this->normalizeFkAction($idealFk->onUpdate) !== $this->normalizeFkAction($liveFk->onUpdate)) {
                            $diffs['on_update'] = ['live' => $liveFk->onUpdate, 'ideal' => $idealFk->onUpdate];
                        }

                        if (!empty($diffs)) {
                            $changedForeignKeys["$tableName.$idealFkName"] = [
                                'table' => $tableName,
                                'live' => $liveFk,
                                'ideal' => $idealFk,
                                'diffs' => $diffs,
                            ];
                        }
                        break;
                    }
                }

                if (!$hasStructuralMatch) {
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
            missingForeignKeys: $missingForeignKeys,
            changedForeignKeys: $changedForeignKeys
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

        // Length comparison for string/char columns
        // Only compare if both have an explicit length, or if ideal has a length that differs from live length
        if (in_array($idealType, ['string', 'char'], true)) {
            $liveLength = $live->length ?? $this->extractLength($live->type);
            $idealLength = $ideal->length ?? $this->extractLength($ideal->type);

            if ($liveLength !== null && $idealLength !== null && $liveLength !== $idealLength) {
                $diffs['length'] = ['live' => $liveLength, 'ideal' => $idealLength];
            }
        }

        // Decimal precision and scale comparison
        if (in_array($idealType, ['decimal'], true)) {
            $livePrecision = $live->precision ?? $this->extractPrecision($live->type);
            $idealPrecision = $ideal->precision ?? $this->extractPrecision($ideal->type);

            $liveScale = $live->scale ?? $this->extractScale($live->type);
            $idealScale = $ideal->scale ?? $this->extractScale($ideal->type);

            if ($livePrecision !== null && $idealPrecision !== null && $livePrecision !== $idealPrecision) {
                $diffs['precision'] = ['live' => $livePrecision, 'ideal' => $idealPrecision];
            }
            if ($liveScale !== null && $idealScale !== null && $liveScale !== $idealScale) {
                $diffs['scale'] = ['live' => $liveScale, 'ideal' => $idealScale];
            }
        }

        // Unsigned attribute comparison (for numeric types)
        if (in_array($idealType, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float'], true)) {
            if ($live->unsigned !== $ideal->unsigned) {
                $diffs['unsigned'] = ['live' => $live->unsigned, 'ideal' => $ideal->unsigned];
            }
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

    protected function extractLength(string $type): ?int
    {
        if (preg_match('/\(\s*(\d+)\s*\)/', $type, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    protected function extractPrecision(string $type): ?int
    {
        if (preg_match('/\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/', $type, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    protected function extractScale(string $type): ?int
    {
        if (preg_match('/\(\s*(\d+)\s*,\s*(\d+)\s*\)/', $type, $matches)) {
            return (int) $matches[2];
        }
        return null;
    }

    /**
     * Map driver-specific types to canonical comparison types.
     */
    public function normalizeType(string $type): string
    {
        $raw = strtolower(trim($type));

        // Detect tinyint(1) or boolean semantics
        // In MySQL / SQLite, tinyint(1) is commonly used for boolean, but tinyint without length or > 1 is numeric tinyint
        if (preg_match('/^tinyint\s*\(\s*1\s*\)$/i', $raw) || $raw === 'bool' || $raw === 'boolean') {
            return 'boolean';
        }
        if (preg_match('/^tinyint(\s*\(\s*(\d+)\s*\))?/i', $raw, $matches)) {
            $len = isset($matches[2]) ? (int) $matches[2] : null;
            if ($len === 1) {
                return 'boolean';
            }
            return 'tinyint';
        }

        // Strip length/precision modifiers for base type equivalence (e.g., varchar(255) -> varchar)
        $baseType = $raw;
        if (preg_match('/^([a-z_]+)\s*\(/i', $raw, $matches)) {
            $baseType = $matches[1];
        }

        return match ($baseType) {
            'int', 'integer', 'int4' => 'integer',
            'bigint', 'int8', 'bigserial' => 'bigint',
            'smallint', 'int2' => 'smallint',
            'mediumint' => 'mediumint',
            'varchar', 'string', 'character varying', 'char', 'nvarchar' => 'string',
            'text', 'longtext', 'mediumtext', 'tinytext' => 'text',
            'datetime', 'timestamp', 'timestamptz', 'datetime2' => 'datetime',
            'double', 'float', 'real', 'float4', 'float8' => 'float',
            'numeric', 'decimal' => 'decimal',
            'json', 'jsonb' => 'json',
            default => $baseType,
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
            // Normalize floating/integer string representations: 0 or 0.00
            $floatVal = (float) $value;
            if ($floatVal == (int) $value && !str_contains((string)$value, '.')) {
                return (string) (int) $value;
            }
            return (string) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            
            // Normalize CURRENT_TIMESTAMP, NOW(), etc.
            if (preg_match('/^(current_timestamp(\(\))?|now\(\)|datetime\(\'now\'\)|localtimestamp)$/i', $trimmed)) {
                return 'CURRENT_TIMESTAMP';
            }

            // Strip casting syntax in Postgres defaults (e.g. 'draft'::character varying or false::boolean or '0'::smallint)
            if (str_contains($trimmed, '::')) {
                $trimmed = explode('::', $trimmed)[0];
                $trimmed = trim($trimmed);
            }

            // Strip wrapping parentheses (e.g. SQLite or MySQL expressions: ('default') or (0))
            while (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')') && strlen($trimmed) >= 2) {
                $trimmed = trim(substr($trimmed, 1, -1));
            }

            // Handle repeated SQL quoted strings like '''Guest''' or "'Guest'" from diverse dialects
            while (
                (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'") && strlen($trimmed) >= 2) ||
                (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"') && strlen($trimmed) >= 2)
            ) {
                $trimmed = substr($trimmed, 1, -1);
            }

            // After stripping quotes, check again if casting was inside quotes or remains
            if (str_contains($trimmed, '::')) {
                $trimmed = trim(explode('::', $trimmed)[0]);
            }

            if (strtolower($trimmed) === 'null') {
                return null;
            }
            if (strtolower($trimmed) === 'true' || $trimmed === "'1'" || $trimmed === "b'1'") {
                return '1';
            }
            if (strtolower($trimmed) === 'false' || $trimmed === "'0'" || $trimmed === "b'0'") {
                return '0';
            }

            // If numeric after unwrapping quotes
            if (is_numeric($trimmed)) {
                $floatVal = (float) $trimmed;
                if ($floatVal == (int) $trimmed && !str_contains($trimmed, '.')) {
                    return (string) (int) $trimmed;
                }
                return (string) $trimmed;
            }

            return $trimmed;
        }

        return $value;
    }
}
