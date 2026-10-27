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
                'is_active' => new ColumnDefinition('is_active', 'tinyint(1)', false, '1'),
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
        $this->assertArrayHasKey('posts.posts_user_fk', $diff->changedForeignKeys);
        $this->assertSame('cascade', $diff->changedForeignKeys['posts.posts_user_fk']['ideal']->onDelete);
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

    public function test_it_detects_varchar_length_differences()
    {
        $engine = new DiffEngine();

        $live = [
            'users' => new TableDefinition('users', [
                'name' => new ColumnDefinition('name', 'varchar(100)', false, null, 100),
            ]),
        ];

        $ideal = [
            'users' => new TableDefinition('users', [
                'name' => new ColumnDefinition('name', 'varchar(255)', false, null, 255),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('users.name', $diff->mismatchedColumns);
        $this->assertSame(100, $diff->mismatchedColumns['users.name']['diffs']['length']['live']);
        $this->assertSame(255, $diff->mismatchedColumns['users.name']['diffs']['length']['ideal']);
    }

    public function test_it_detects_decimal_precision_and_scale_differences()
    {
        $engine = new DiffEngine();

        $live = [
            'products' => new TableDefinition('products', [
                'price' => new ColumnDefinition('price', 'decimal(8,2)', false, null, null, false, null, 8, 2),
            ]),
        ];

        $ideal = [
            'products' => new TableDefinition('products', [
                'price' => new ColumnDefinition('price', 'decimal(10,4)', false, null, null, false, null, 10, 4),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('products.price', $diff->mismatchedColumns);
        $this->assertSame(8, $diff->mismatchedColumns['products.price']['diffs']['precision']['live']);
        $this->assertSame(10, $diff->mismatchedColumns['products.price']['diffs']['precision']['ideal']);
        $this->assertSame(2, $diff->mismatchedColumns['products.price']['diffs']['scale']['live']);
        $this->assertSame(4, $diff->mismatchedColumns['products.price']['diffs']['scale']['ideal']);
    }

    public function test_it_distinguishes_numeric_tinyint_from_boolean()
    {
        $engine = new DiffEngine();

        $live = [
            'settings' => new TableDefinition('settings', [
                'status' => new ColumnDefinition('status', 'tinyint', false, 0),
            ]),
        ];

        $ideal = [
            'settings' => new TableDefinition('settings', [
                'status' => new ColumnDefinition('status', 'boolean', false, false),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('settings.status', $diff->mismatchedColumns);
    }

    public function test_it_detects_signed_vs_unsigned_differences()
    {
        $engine = new DiffEngine();

        $live = [
            'accounts' => new TableDefinition('accounts', [
                'balance_cents' => new ColumnDefinition('balance_cents', 'integer', false, 0, null, false),
            ]),
        ];

        $ideal = [
            'accounts' => new TableDefinition('accounts', [
                'balance_cents' => new ColumnDefinition('balance_cents', 'integer', false, 0, null, true),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('accounts.balance_cents', $diff->mismatchedColumns);
        $this->assertFalse($diff->mismatchedColumns['accounts.balance_cents']['diffs']['unsigned']['live']);
        $this->assertTrue($diff->mismatchedColumns['accounts.balance_cents']['diffs']['unsigned']['ideal']);
    }

    public function test_it_preserves_composite_index_column_order()
    {
        $engine = new DiffEngine();

        $live = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'integer', false, null),
            ], [
                'idx_first_last' => new IndexDefinition('idx_first_last', 'index', ['first_name', 'last_name']),
            ]),
        ];

        $ideal = [
            'users' => new TableDefinition('users', [
                'id' => new ColumnDefinition('id', 'integer', false, null),
            ], [
                'idx_last_first' => new IndexDefinition('idx_last_first', 'index', ['last_name', 'first_name']),
            ]),
        ];

        $diff = $engine->compare($live, $ideal);

        $this->assertTrue($diff->hasDifferences());
        $this->assertArrayHasKey('users.idx_last_first', $diff->missingIndexes);
    }

    public function test_it_normalizes_postgres_and_expression_defaults()
    {
        $engine = new DiffEngine();

        $this->assertSame('draft', $engine->normalizeDefault("'draft'::character varying"));
        $this->assertSame('1', $engine->normalizeDefault("true::boolean"));
        $this->assertSame('0', $engine->normalizeDefault("false"));
        $this->assertSame('0', $engine->normalizeDefault("(0)"));
        $this->assertSame('CURRENT_TIMESTAMP', $engine->normalizeDefault("now()"));
        $this->assertSame('CURRENT_TIMESTAMP', $engine->normalizeDefault("current_timestamp"));
    }
}
