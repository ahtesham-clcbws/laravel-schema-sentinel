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

## 2. In-Process Execution Model, Security Boundary & Trusted-Migrations Policy

- **Trusted-Migrations-Only Operational Boundary**:
  - `ShadowMigrationRunner` runs migration simulations inside the host PHP process. **This feature is strictly intended for trusted application migrations.**
  - **What IS Protected**:
    - **Default connection redirection**: Default queries are directed to `sentinel_shadow`.
    - **AST Static Scanner**: Pre-scans migration code for explicit connection invocations (`DB::connection(...)`, `Schema::connection(...)`), model property `$connection` overrides, direct `new \PDO()` instantiations, and external process execution calls (`exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`). These cause an immediate abort (`RuntimeException`).
    - **Runtime Connection Cloaking**: Temporarily re-registers all non-shadow configured connections to an inert locked SQLite driver (`sentinel_locked_*`) whose queries abort immediately if invoked dynamically or via service container resolution.
    - **Connection Identity Collisions**: Strictly detects canonical file paths, symlinks, query flags, and matching host/port/database configurations.
  - **What IS NOT Protected (Host-Process Boundaries)**:
    - **Arbitrary PHP / External Libraries**: If a migration invokes third-party code that connects to external databases via raw sockets, C extensions, non-standard PDO wrappers outside Laravel, or arbitrary file/network calls, the in-process locked driver cannot sandbox them at the OS kernel level.
    - **Untrusted Migrations**: Sentinel does NOT provide OS-level containerization or process virtualization. For untrusted third-party migrations, execution must be performed in dedicated throwaway virtual machines or Docker containers with network access disabled.
- **Connection Purging & Lifecycle Side Effects**:
  - To implement runtime cloaking, `ShadowMigrationRunner` executes `DB::disconnect()` and `DB::purge()` on all active non-shadow connections before running migrations, and restores the original connection configurations in a `finally` block.
  - **Side Effect**: Any previously opened PDO connections or in-progress transactions on live connections in the host process are purged. Long-running worker processes or scripts that hold stateful live database transactions must not invoke `Sentinel::diff()` or `schema:drift` concurrently within the same transaction lifecycle.

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
