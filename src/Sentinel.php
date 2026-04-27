<?php

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
        protected DiffEngine $diffEngine
    ) {}

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
}
