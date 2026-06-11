<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\DTOs;

/**
 * Represents a single database column definition.
 */
readonly class ColumnDefinition
{
    public string $name;
    public string $type;
    public bool $nullable;
    public mixed $default;
    public ?int $length;
    public bool $unsigned;
    public ?string $comment;

    /**
     * @param string $name
     * @param string $type
     * @param bool $nullable
     * @param mixed $default
     * @param int|null $length
     * @param bool $unsigned
     * @param string|null $comment
     */
    public function __construct(
        string $name,
        string $type,
        bool $nullable,
        mixed $default,
        ?int $length = null,
        bool $unsigned = false,
        ?string $comment = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
        $this->default = $default;
        $this->length = $length;
        $this->unsigned = $unsigned;
        $this->comment = $comment;
    }

    /**
     * Convert the definition to a plain array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'length' => $this->length,
            'unsigned' => $this->unsigned,
            'comment' => $this->comment,
        ];
    }
}
