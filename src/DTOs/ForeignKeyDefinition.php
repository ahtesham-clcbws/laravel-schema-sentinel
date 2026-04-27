<?php

namespace Sentinel\SchemaSentinel\DTOs;

/**
 * Represents a foreign key constraint definition.
 */
readonly class ForeignKeyDefinition
{
    /**
     * @param string $name The name of the constraint.
     * @param string[] $columns The local columns participating in the FK.
     * @param string $foreignTable The table being referenced.
     * @param string[] $foreignColumns The columns being referenced on the foreign table.
     * @param string $onDelete The referential action on delete (e.g., 'cascade', 'restrict').
     * @param string $onUpdate The referential action on update.
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $foreignTable,
        public array $foreignColumns,
        public string $onDelete = 'no action',
        public string $onUpdate = 'no action',
    ) {}
}
