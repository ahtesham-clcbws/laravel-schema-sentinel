<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shadow Database Connection
    |--------------------------------------------------------------------------
    |
    | This connection is used to simulate your migrations in-memory.
    | By default, it uses SQLite in-memory for speed and isolation.
    |
    */
    'shadow_connection' => [
        'driver'   => env('SENTINEL_SHADOW_DRIVER', 'sqlite'),
        'host'     => env('SENTINEL_SHADOW_HOST', env('DB_HOST', '127.0.0.1')),
        'port'     => env('SENTINEL_SHADOW_PORT', env('DB_PORT', '3306')),
        'database' => env('SENTINEL_SHADOW_DB', ':memory:'),
        'username' => env('SENTINEL_SHADOW_USER', env('DB_USERNAME', 'forge')),
        'password' => env('SENTINEL_SHADOW_PASS', env('DB_PASSWORD', '')),
        'charset'  => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'   => '',
        'foreign_key_constraints' => env('SENTINEL_SHADOW_FK', false),
        /*
        | Note: If you change the driver to 'mysql' or 'pgsql', ensure the 'database' 
        | points to a DEDICATED EMPTY DATABASE. Sentinel will run migrations 
        | here to simulate your schema. Using your live database will cause errors.
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Tables
    |--------------------------------------------------------------------------
    |
    | These tables will be skipped during the drift analysis. This is useful
    | for ignoring internal Laravel tables or third-party package tables.
    |
    */
    'ignore_tables' => [
        'migrations',
        'failed_jobs',
        'jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'sessions',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Paths
    |--------------------------------------------------------------------------
    |
    | Sentinel will search for migrations in these directories.
    | You can add custom paths for modular applications.
    |
    */
    'migration_paths' => [
        'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Configure the default behavior of the schema:drift command.
    |
    */
    'defaults' => [
        'strict' => env('SENTINEL_STRICT_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Skip Migrations
    |--------------------------------------------------------------------------
    |
    | If some of your older migrations are broken or incompatible with SQLite,
    | you can list their filenames here to skip them during the simulation.
    | Use the full filename (e.g., '2023_01_01_000000_create_users_table.php').
    |
    */
    'skip_migrations' => [
        // '2023_04_21_113217_update_test_table_column.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Consistency Audit
    |--------------------------------------------------------------------------
    |
    | List tables whose data should also be audited for consistency.
    | Useful for reference tables like roles, permissions, or settings.
    |
    */
    'data_audit_tables' => [
        // 'roles',
        // 'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Type Mapping
    |--------------------------------------------------------------------------
    |
    | Map specialized DB types to Laravel Blueprint methods.
    | Example: 'citext' => 'string', 'geography' => 'geography'
    |
    */
    'custom_types' => [
        // 'citext' => 'string',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Get alerted when drift is detected. Perfect for CI/CD or production monitoring.
    |
    */
    'notifications' => [
        'enabled' => env('SENTINEL_NOTIFICATIONS_ENABLED', false),
        'slack_webhook' => env('SENTINEL_SLACK_WEBHOOK'),
        'discord_webhook' => env('SENTINEL_DISCORD_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-Migration Guard
    |--------------------------------------------------------------------------
    |
    | Sentinel can hook into "php artisan migrate" and prevent it from running
    | if schema drift is detected. This prevents "Partial Migration" disasters.
    |
    */
    'guard' => [
        'enabled' => env('SENTINEL_GUARD_ENABLED', false),
    ],

];
