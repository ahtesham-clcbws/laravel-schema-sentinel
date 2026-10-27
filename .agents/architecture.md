# Architecture - Laravel Schema Sentinel

## Stack & Target Environment
- PHP 8.2+ / 8.4+ / 8.5+
- Laravel 11 / 12 / 13 Blueprint & Schema systems
- Supported Databases: SQLite, MySQL (8+), MariaDB, PostgreSQL (16+)
- Test runner: Pest / PHPUnit / Orchestra Testbench

## Core Modules
- `src/Core/DiffEngine.php`: Schema comparison engine comparing tables, columns, indexes, foreign keys.
- `src/Core/SchemaParser.php`: Driver-specific and information schema inspection / normalization into DTOs.
- `src/Core/MigrationGenerator.php`: Generates Laravel migration code (`Blueprint` up & down) from detected diffs.
- `src/Core/ShadowMigrationRunner.php`: Sandboxed isolated migration runner verifying schema drift.
- `src/Support/PreMigrationGuard.php`: Middleware/listener halting migrations on detected drifts.
- `src/DTOs/`: ColumnDefinition, IndexDefinition, ForeignKeyDefinition, TableDiff, SchemaDiff.
