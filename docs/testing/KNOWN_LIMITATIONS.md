# Known Limitations & Engine Constraints — Laravel Schema Sentinel v2.1.0

This document outlines intentional architectural boundaries, driver discrepancies, and unsupported scenarios.

## 1. Cross-Driver Simulation Constraints

- **Cross-Driver Simulation Discrepancies**:
  - Simulating migrations on an in-memory SQLite shadow connection while diffing against a client-server live database (such as PostgreSQL or MySQL) can produce metadata mismatches due to dialect differences (e.g., PostgreSQL sequence defaults `nextval(...)`, UUID native types vs SQLite string representations).
  - **Recommended Practice**: For MySQL or PostgreSQL production applications, configure `schema-sentinel.shadow_connection` to a dedicated empty shadow database running on the same database engine.
- **Safety Interlock & Collision Prevention**:
  - `ShadowMigrationRunner` strictly resolves realpaths and symlinks, rejecting any SQLite database file matching an existing application connection.
  - Client-server shadow connections are inspected against all configured connections (driver, host, port, database, and socket). Any identity match aborts immediately with a `RuntimeException`.
  - Unsafe SQLite URI configurations containing query flags (e.g., `file:... ?mode=...`) are explicitly rejected to prevent URI exploitation.

## 2. Explicit Database Connections in Migrations & Arbitrary PHP Boundary

- **Two-Tier Isolation & Cloaking Policy**:
  - Migrations declaring hardcoded connection calls such as `DB::connection('mysql')`, `Schema::connection('other')`, or model class definitions with `protected $connection = '...'` bypass standard connection redirection.
  - **Tier 1 (AST Static Validation)**: `ShadowMigrationRunner` scans migration files using AST tokens. Any explicit or dynamic connection override call triggers an immediate `RuntimeException`.
  - **Tier 2 (Dynamic Runtime Locked Driver Extension)**: Prior to running migrations, all configured non-shadow database connections in the application are purged and re-registered to an inert in-memory SQLite connection powered by a custom locked driver (`sentinel_locked_*`). If any migration code or third-party package attempts to resolve or execute a query on any non-shadow connection (e.g., dynamically or via reflection), the query listener instantly terminates execution with `CRITICAL DATABASE SAFETY VIOLATION` before any packet reaches a live socket.
- **Enforced Security Boundary for Arbitrary PHP**:
  - Migrations are executable PHP code. While Sentinel enforces connection cloaking and token validation against Laravel database access, Sentinel does NOT attempt to isolate arbitrary OS-level system calls (`exec`, `shell_exec`, `proc_open`), direct raw `new \PDO()` network connections, or external filesystem mutations. Untrusted migration code requiring full OS-level sandboxing must be executed in isolated containers or dedicated virtual machines. Sentinel fails closed whenever safe execution cannot be established.

## 3. Migration Generator & Reverse Repairs

- **Enum Reflection**:
  - Pure SQLite does not enforce native ENUM types; column types are stored as `varchar` or `text`. Reverse engineering generates class scaffolds without fabricating imaginary business states.
- **Data Seeder Sanitization**:
  - In v2.1.0, automated live record export in seeders has been removed to prevent sensitive data leakage. Generated seeders contain standard developer placeholder comments.
- **Unsupported Column Types**:
  - Repair generation (`--fix` / `--sql`) will not silently coerce unrecognized custom database types to `string()`. If an unsupported type is encountered during drift repair generation, a descriptive `RuntimeException` is raised indicating manual repair is required.

## 4. Pre-Migration Guard Context

- **Event Exception Behavior**:
  - In previous versions, `PreMigrationGuard` invoked `exit(1)` abruptly. In v2.1.0, it throws `Sentinel\SchemaSentinel\Exceptions\MigrationBlockedException`, allowing host testing runners or CLI handlers to trap and report the event cleanly.
- **Re-entrancy & Internal Command Bypass**:
  - The guard safely tracks nested bypass states and automatically ignores Sentinel internal artisan calls (`schema:*`, `vendor:publish`), eliminating command deadlocks.
