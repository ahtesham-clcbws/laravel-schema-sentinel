<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Facades;

use Illuminate\Support\Facades\Facade;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;

/**
 * @method static SchemaDiff check(bool $strict = false) Perform a schema drift analysis comparing migrations with the live database.
 * @method static array parse(\Illuminate\Database\Connection $connection = null) Parse the database schema structures into standard TableDefinition DTOs.
 * @method static array standardizeIndexes() Scan all database tables to identify non-standard index names and redundant/duplicate indexes.
 * @method static array dataDrift(string $targetEnv) Audit and report content differences in critical lookup tables against a target connection.
 * @method static array reverse(array $options = []) Reverse-engineer database tables into clean migrations, models, and seeders.
 * 
 * @see \Sentinel\SchemaSentinel\Sentinel
 */
class Sentinel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-schema-sentinel';
    }
}
