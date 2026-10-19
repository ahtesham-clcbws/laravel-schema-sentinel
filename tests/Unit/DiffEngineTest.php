<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Unit;

use Sentinel\SchemaSentinel\Core\DiffEngine;
use Sentinel\SchemaSentinel\DTOs\ColumnDefinition;
use Sentinel\SchemaSentinel\DTOs\ForeignKeyDefinition;
use Sentinel\SchemaSentinel\DTOs\IndexDefinition;
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

    public function test_it_normalizes_equivalent_types_to_avoid_false_positives()
    {
        $engine = new DiffEngine();

        $live = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'int', false, null),
                'name' => new ColumnDefinition('name', 'varchar(255)', false, null),
                'is_active' => new ColumnDefinition('is_active', 'tinyint', false, '1'),
            ]),
        ];

        $ideal = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'integer', false, null),
                'name' => new ColumnDefinition('name', 'string', false, null),
                'is_active' => new ColumnDefinition('is_active', 'boolean', false, 1),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertFalse($diff->hasDifferences());
    }

    public function test_it_detects_foreign_key_action_mismatches()
    {
        $engine = new DiffEngine();

        $live = [
            'posts' => new TableDefinition(
                'posts',
                ['id' => new ColumnDefinition('id', 'integer', false, null)],
                [],
                ['posts_user_fk' => new ForeignKeyDefinition('posts_user_fk', ['user_id'], 'users', ['id'], 'no action', 'no action')]
            ),
        ];

        $ideal = [
            'posts' => new TableDefinition(
                'posts',
                ['id' => new ColumnDefinition('id', 'integer', false, null)],
                [],
                ['posts_user_fk' => new ForeignKeyDefinition('posts_user_fk', ['user_id'], 'users', ['id'], 'cascade', 'no action')]
            ),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('posts.posts_user_fk', $diff->missingForeignKeys);
    }

    public function test_it_matches_composite_indexes_regardless_of_generated_index_name()
    {
        $engine = new DiffEngine();

        $live = [
            'orders' => new TableDefinition(
                'orders',
                ['id' => new ColumnDefinition('id', 'integer', false, null)],
                ['orders_company_status_idx' => new IndexDefinition('orders_company_status_idx', 'index', ['company_id', 'status'])]
            ),
        ];

        $ideal = [
            'orders' => new TableDefinition(
                'orders',
                ['id' => new ColumnDefinition('id', 'integer', false, null)],
                ['custom_compound_index' => new IndexDefinition('custom_compound_index', 'index', ['company_id', 'status'])]
            ),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertFalse($diff->hasDifferences());
    }
}
