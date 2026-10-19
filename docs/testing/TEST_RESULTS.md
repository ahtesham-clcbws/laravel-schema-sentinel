# Real Application Test Results — Laravel Schema Sentinel v2.1.0

## Execution Summary

- **Date & Time**: 2026-10-19 15:56 UTC+5:30
- **Target Version**: `2.1.0`
- **Tested Commit SHA**: `dea15e5b8d0ca20f8365ea9ddc6e189dca3e3f43`
- **GitHub Actions Run**: [CI Tests #39237374032](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions/runs/39237374032) (All 6 matrix jobs PASS: SQLite, MySQL, and PostgreSQL on PHP 8.4 & 8.5)
- **Laravel Framework Version**: `13.37.0` (Host App) / `13.15.0` (Package Testbench)
- **PHP Version**: `PHP 8.5.11 (cli)` / `PHP 8.4` (CI Matrix)
- **Host System**: Linux (Arch Linux / CachyOS x86_64)

---

## 1. Automated Package Test Suite (PHPUnit 11)

```text
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.11
Configuration: phpunit.xml

................SS...............                                 33 / 33 (100%)

Time: 00:00.773, Memory: 14.00 MB

OK, but some tests were skipped!
Tests: 33, Assertions: 86, Skipped: 2.
```

| Area | Tests | Passed | Failed | Skipped | Status |
|---|---|---|---|---|---|
| DTOs & Table Definitions | 2 | 2 | 0 | 0 | **PASS** |
| DiffEngine Analysis | 5 | 5 | 0 | 0 | **PASS** |
| ShadowMigrationRunner Isolation, Collisions, Cloaking & Interceptors | 9 | 9 | 0 | 0 | **PASS** |
| MigrationGenerator (Safe Syntax, Escaping, Rollback, Execution) | 4 | 4 | 0 | 0 | **PASS** |
| PreMigrationGuard & Exception Safety | 2 | 2 | 0 | 0 | **PASS** |
| Telemetry Isolation (Zero Network Dispatch) | 1 | 1 | 0 | 0 | **PASS** |
| Schema Documentation Generator | 1 | 1 | 0 | 0 | **PASS** |
| Reverse Engineering (Safe Seeders & Enums) | 1 | 1 | 0 | 0 | **PASS** |
| Index Standardization Command | 2 | 2 | 0 | 0 | **PASS** |
| Data Drift Command | 2 | 2 | 0 | 0 | **PASS** |
| Interactive Help Board | 1 | 1 | 0 | 0 | **PASS** |
| PostgreSQL Integration Test (12-step lifecycle) | 1 | 1* | 0 | 0 | **PASS** (*Verified with pgsql driver: 1 test, 10 assertions) |
| MySQL Integration Test (12-step lifecycle) | 1 | 1* | 0 | 0 | **PASS** (*Verified with mysql driver: 1 test, 10 assertions) |
| **Total** | **33** | **33** | **0** | **0** | **100% PASS** |

---

## 2. Real Laravel 13 Test Application Lifecycle Verification

Executed in sibling test application (`../schema-sentinel-laravel13-testapp`):

1. **Clean Installation**:
   - `composer require clcbws/laravel-schema-sentinel:@dev` loaded via local path symlink repository.
   - Provider auto-discovery succeeded.
2. **Artisan Doctor Command**:
   - `php artisan schema:sentinel-doctor` passed all 5 environment checks.
   - Verified zero network requests made.
3. **Drift Detection (Sync State)**:
   - Initial clean migrations (Users, Cache, Jobs, Profiles, Posts, Categories, Comments, Products, Orders, Payments) executed.
   - `php artisan schema:drift` reported `Health Score: 100%` and zero drift.
4. **Drift Detection (Strict Mode)**:
   - Created unmanaged table `unmanaged_logs` directly in SQLite.
   - `php artisan schema:drift --strict` successfully identified extra table with `Health Score: 98%`.
   - Dropped `unmanaged_logs`.
5. **Missing Table Drift & Fix Generation Lifecycle**:
   - Dropped `comments` table directly in SQLite.
   - `php artisan schema:drift` reported `Missing Tables in Live DB: comments` with `Health Score: 85%` (exit code 1).
   - `php artisan schema:drift --sql` generated syntactically valid dry-run Blueprint migration.
   - `php artisan schema:drift --fix` created timestamped migration `..._fix_schema_drift_....php`.
   - `php artisan migrate` executed generated fix cleanly.
   - Re-running `php artisan schema:drift` confirmed `Health Score: 100%` with zero drift.
6. **Rollback Verification**:
   - `php artisan migrate:rollback --step=1` executed generated down() migration cleanly, restoring previous state.
   - Re-running `php artisan schema:drift` correctly detected missing comments table again.
   - Re-applied migration to verify idempotency.
7. **Snapshot Functionality**:
   - `php artisan schema:snapshot latest` generated `latest.json` with accurate DTO hydration.
   - `php artisan schema:drift --snapshot=latest` verified instant comparison without re-simulating migrations.
8. **Reverse Engineering**:
   - `php artisan schema:reverse --path=database/reverse_test` generated models, migrations, and seeders.
   - Verified that seeders do NOT dump raw database records and contain secure placeholders.
9. **Index Standardization**:
   - `php artisan schema:standardize-indexes` audited existing indexes accurately.
10. **Cross-Driver Verification (PostgreSQL 18.6)**:
    - Tested against dedicated PostgreSQL databases (`sentinel_pg_test` and `sentinel_pg_shadow`).
    - Verified simulation, schema parsing, and 100% sync on identical schemas.
    - Dropped table on PostgreSQL live DB and confirmed accurate drift detection.
11. **Cross-Driver Verification (MariaDB 13.0.2 / MySQL)**:
    - Tested against isolated databases (`sentinel_local_test` and `sentinel_local_shadow`).
    - Confirmed drift detection on dropped table, automated `--fix` migration generation with `unsigned` integer types and nullable timestamps.
    - Verified PHP syntax (`php -l`), executed migration cleanly to restore 100% health sync, and verified rollback (`migrate:rollback`).
    - Both test databases dropped cleanly post-verification.
