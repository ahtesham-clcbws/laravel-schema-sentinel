<?php

namespace Sentinel\SchemaSentinel\DTOs;

/**
 * Represents a database index definition.
 */
readonly class IndexDefinition
{
    /**
     * @param string $name The name of the index.
     * @param string $type The type of index (e.g., 'primary', 'unique', 'index', 'fulltext', 'spatial').
     * @param string[] $columns The columns covered by this index.
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $columns,
    ) {}
}
