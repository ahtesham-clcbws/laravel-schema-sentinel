# Known Limitations & Engine Constraints — Laravel Schema Sentinel v2.1.0

This document outlines intentional architectural boundaries, driver discrepancies, and unsupported scenarios.

## 1. Cross-Driver Simulation Constraints

- **Cross-Driver Simulation Discrepancies**:
  - Simulating migrations on an in-memory SQLite shadow connection while diffing against a client-server live database (such as PostgreSQL or MySQL) can produce metadata mismatches due to dialect differences (e.g., PostgreSQL sequence defaults `nextval(...)`, UUID native types vs SQLite string representations).
  - **Recommended Practice**: For MySQL or PostgreSQL production applications, configure `schema-sentinel.shadow_connection` to a dedicated empty shadow database running on the same database engine.
- **Safety Interlock**:
  - `ShadowMigrationRunner` strictly forbids setting `shadow_connection` to the same host and database as the live application connection.

## 2. Reverse Engineering Boundaries

- **Enum Reflection**:
  - Pure SQLite does not enforce native ENUM types; column types are stored as `varchar` or `text`. Reverse engineering generates class scaffolds without fabricating imaginary business states.
- **Data Seeder Sanitization**:
  - In v2.1.0, automated live record export in seeders has been removed to prevent sensitive data leakage. Generated seeders contain standard developer placeholder comments.

## 3. Pre-Migration Guard Context

- **Event Exception Behavior**:
  - In previous versions, `PreMigrationGuard` invoked `exit(1)` abruptly. In v2.1.0, it throws `Sentinel\SchemaSentinel\Exceptions\MigrationBlockedException`, allowing host testing runners or CLI handlers to trap and report the event cleanly.
