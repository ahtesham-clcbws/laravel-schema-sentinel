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
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
        'foreign_key_constraints' => false,
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
        'strict' => false,
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

];
