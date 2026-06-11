<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Unit;

use Sentinel\SchemaSentinel\Core\DiffEngine;
use Sentinel\SchemaSentinel\DTOs\ColumnDefinition;
use Sentinel\SchemaSentinel\DTOs\TableDefinition;
use Sentinel\SchemaSentinel\Tests\TestCase;

class DiffEngineTest extends TestCase
{
    public function test_it_detects_missing_tables()
    {
        $engine = new DiffEngine();
        
        $live = [];
        $ideal = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'integer', false, null),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('users', $diff->missingTables);
    }

    public function test_it_detects_missing_columns()
    {
        $engine = new DiffEngine();
        
        $live = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'integer', false, null),
            ]),
        ];
        
        $ideal = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'integer', false, null),
                'email' => new ColumnDefinition('email', 'string', false, null),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('users.email', $diff->missingColumns);
    }
}
