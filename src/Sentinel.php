<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel;

use Illuminate\Support\Facades\DB;
use Sentinel\SchemaSentinel\Core\DiffEngine;
use Sentinel\SchemaSentinel\Core\SchemaParser;
use Sentinel\SchemaSentinel\Core\ShadowMigrationRunner;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;

/**
 * The core service for Laravel Schema Sentinel.
 * 
 * Provides programmatic access to schema drift detection.
 */
class Sentinel
{
    public function __construct(
        protected ShadowMigrationRunner $shadowRunner,
        protected SchemaParser $parser,
        protected DiffEngine $diffEngine,
        protected \Sentinel\SchemaSentinel\Core\ReverseEngineer $reverser
    ) {}

    /**
     * Reverse-engineer database tables into clean migrations, models, seeders, and enums.
     * 
     * @param array $options
     * @return array
     */
    public function reverse(array $options = []): array
    {
        $basePath = $options['path'] ?? base_path();
        
        $generateModels = $options['models'] ?? true;
        $generateMigrations = $options['migrations'] ?? true;
        $generateSeeders = $options['seeders'] ?? true;
        $generateEnums = $options['enums'] ?? true;

        $schema = $this->parse($options['connection'] ?? null);

        return $this->reverser->reverse(
            $schema,
            $basePath,
            $generateModels,
            $generateMigrations,
            $generateSeeders,
            $generateEnums
        );
    }

    /**
     * Perform a schema drift analysis.
     * 
     * @param bool $strict If true, will report extra tables/columns in the live DB.
     * @return SchemaDiff
     */
    public function check(bool $strict = false): SchemaDiff
    {
        $shadowConn = $this->shadowRunner->run();
        
        try {
            $liveSchema = $this->parser->parse(DB::connection());
            $idealSchema = $this->parser->parse($shadowConn);

            return $this->diffEngine->compare($liveSchema, $idealSchema, $strict);
        } finally {
            $this->shadowRunner->cleanup();
        }
    }

    /**
     * Parse the database schema.
     * 
     * @param \Illuminate\Database\Connection|null $connection
     * @return array
     */
    public function parse(?\Illuminate\Database\Connection $connection = null): array
    {
        $connection = $connection ?? DB::connection();
        return $this->parser->parse($connection);
    }

    /**
     * Scan database for non-standard index names and duplicate indexes.
     * 
     * @return array{deviations: array, redundant: array}
     */
    public function standardizeIndexes(): array
    {
        $schema = $this->parse();
        $deviations = [];
        $redundant = [];

        foreach ($schema as $tableName => $table) {
            $indexes = array_values($table->indexes);
            $indexCount = count($indexes);

            foreach ($indexes as $index) {
                if ($index->type === 'primary') {
                    if ($index->name !== 'primary') {
                        $expected = $this->generateLaravelIndexName($tableName, $index->columns, 'primary');
                        if ($index->name !== $expected) {
                            $deviations[] = [
                                'table' => $tableName,
                                'columns' => $index->columns,
                                'type' => $index->type,
                                'current' => $index->name,
                                'expected' => $expected,
                            ];
                        }
                    }
                    continue;
                }

                $expected = $this->generateLaravelIndexName($tableName, $index->columns, $index->type);
                if ($index->name !== $expected) {
                    $deviations[] = [
                        'table' => $tableName,
                        'columns' => $index->columns,
                        'type' => $index->type,
                        'current' => $index->name,
                        'expected' => $expected,
                    ];
                }
            }

            for ($i = 0; $i < $indexCount; $i++) {
                for ($j = 0; $j < $indexCount; $j++) {
                    if ($i === $j) continue;
                    $idxA = $indexes[$i];
                    $idxB = $indexes[$j];
                    if ($idxA->type === 'primary' || $idxB->type === 'primary') continue;
                    if ($idxB->type !== 'unique' && $this->isLeftPrefix($idxB->columns, $idxA->columns)) {
                        $redundant[] = [
                            'table' => $tableName,
                            'redundant_index' => $idxB->name,
                            'redundant_columns' => $idxB->columns,
                            'covered_by_index' => $idxA->name,
                            'covered_by_columns' => $idxA->columns,
                        ];
                    }
                }
            }
        }

        return compact('deviations', 'redundant');
    }

    /**
     * Audit data drift in configured tables against a target connection.
     * 
     * @param string $targetEnv The database connection name to compare against.
     * @return array<string, array{missing: array, extra: array, mismatches: array}>
     */
    public function dataDrift(string $targetEnv): array
    {
        $tables = \Illuminate\Support\Facades\Config::get('schema-sentinel.data_audit_tables', []);
        $drift = [];

        foreach ($tables as $table) {
            try {
                $localData = DB::table($table)->orderBy('id')->get()->keyBy('id')->toArray();
                $targetData = DB::connection($targetEnv)->table($table)->orderBy('id')->get()->keyBy('id')->toArray();
            } catch (\Exception $e) {
                continue;
            }

            $localKeys = array_keys($localData);
            $targetKeys = array_keys($targetData);

            $missing = array_diff($targetKeys, $localKeys);
            $extra = array_diff($localKeys, $targetKeys);
            $mismatches = [];

            $commonKeys = array_intersect($localKeys, $targetKeys);
            foreach ($commonKeys as $key) {
                $localRow = (array) $localData[$key];
                $targetRow = (array) $targetData[$key];

                foreach ($localRow as $col => $localVal) {
                    $targetVal = $targetRow[$col] ?? null;
                    if ($localVal !== $targetVal) {
                        $mismatches[] = [
                            'id' => $key,
                            'column' => $col,
                            'local' => $localVal,
                            'target' => $targetVal,
                        ];
                    }
                }
            }

            if (!empty($missing) || !empty($extra) || !empty($mismatches)) {
                $drift[$table] = compact('missing', 'extra', 'mismatches');
            }
        }

        return $drift;
    }

    protected function isLeftPrefix(array $colsB, array $colsA): bool
    {
        if (count($colsB) >= count($colsA)) return false;
        for ($i = 0; $i < count($colsB); $i++) {
            if ($colsB[$i] !== $colsA[$i]) return false;
        }
        return true;
    }

    protected function generateLaravelIndexName(string $table, array $columns, string $type): string
    {
        $index = strtolower($table.'_'.implode('_', $columns).'_'.$type);
        $name = str_replace(['-', '.'], '_', $index);
        if (strlen($name) > 64) {
            $hashed = substr(md5($name), 0, 8);
            $prefix = substr($table, 0, 20) . '_' . substr(implode('_', $columns), 0, 20);
            $name = strtolower($prefix . '_' . $hashed . '_' . $type);
        }
        return $name;
    }
}
