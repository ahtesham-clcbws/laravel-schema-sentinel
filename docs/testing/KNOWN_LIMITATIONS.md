# Known Limitations & Engine Constraints — Laravel Schema Sentinel v2.2.0

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
- **Active Transaction Interlock & Connection Purging Safety**:
  - To prevent state disruption in the host process, `ShadowMigrationRunner` inspects all initialized database connections in `DatabaseManager` before performing any connection modifications.
  - **Transaction Protection**: If any connection has an active transaction (`transactionLevel() > 0` or PDO `inTransaction() === true`), Sentinel immediately aborts with `CRITICAL DATABASE SAFETY ERROR: Active transaction detected on connection [...]`. This prevents Sentinel from purging connections mid-transaction, ensuring all pending transaction states in the host application remain 100% intact.
  - **Post-Simulation Cleanup**: When no active transaction is present, connections are purged, cloaked during simulation, and restored cleanly in a `finally` block.

## 3. Migration Generator & Reverse Repairs

- **Enum Reflection**:
  - Pure SQLite does not enforce native ENUM types; column types are stored as `varchar` or `text`. Reverse engineering generates class scaffolds without fabricating imaginary business states.
- **Data Seeder Sanitization**:
  - Live record export in seeders is removed to prevent sensitive data leakage. Generated seeders contain standard developer placeholder comments.
- **Foreign Key Re-linking**:
  - In v2.2.0, changed foreign keys are dropped and recreated rather than treated as missing, avoiding duplicate constraint creation errors. Rollback blocks safely restore original constraint definitions.
- **Raw SQL Default Expressions**:
  - In v2.2.0, standard timestamp defaults (`CURRENT_TIMESTAMP`, `now()`, `localtimestamp`) map directly to Laravel's native `->useCurrent()`.
  - Non-deterministic or engine-specific raw SQL expressions (such as `gen_random_uuid()` or sequence expressions) are intentionally not converted to arbitrary string literals; Sentinel raises a descriptive `RuntimeException` during migration generation to prevent generating corrupted or invalid schema blueprints.

## 4. Pre-Migration Guard Context

- **Event Exception Behavior**:
  - `PreMigrationGuard` throws `Sentinel\SchemaSentinel\Exceptions\MigrationBlockedException`, allowing host testing runners or CLI handlers to trap and report the event cleanly.
- **Re-entrancy & Internal Command Bypass**:
  - The guard safely tracks nested bypass states and automatically ignores Sentinel internal artisan calls (`schema:*`, `vendor:publish`), eliminating command deadlocks.
