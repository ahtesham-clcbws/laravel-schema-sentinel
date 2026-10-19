# Real Application Test Results — Laravel Schema Sentinel v2.0.1

## Execution Summary

- **Date & Time**: 2026-10-19 13:38 UTC+5:30
- **Target Version**: `2.0.1`
- **Laravel Framework Version**: `13.37.0` (Host App) / `13.15.0` (Package Testbench)
- **PHP Version**: `PHP 8.5.11 (cli)`
- **Host System**: Linux (Arch Linux / CachyOS x86_64)

---

## 1. Automated Package Test Suite (PHPUnit 11)

```text
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.11
Configuration: phpunit.xml

...................                                               19 / 19 (100%)

Time: 00:00.942, Memory: 14.00 MB

OK (19 tests, 61 assertions)
```

| Area | Tests | Passed | Failed | Skipped | Status |
|---|---|---|---|---|---|
| DTOs & Table Definitions | 2 | 2 | 0 | 0 | **PASS** |
| DiffEngine Analysis | 5 | 5 | 0 | 0 | **PASS** |
| ShadowMigrationRunner Isolation | 2 | 2 | 0 | 0 | **PASS** |
| PreMigrationGuard & Exception Safety | 2 | 2 | 0 | 0 | **PASS** |
| Telemetry Isolation (Zero Network Dispatch) | 1 | 1 | 0 | 0 | **PASS** |
| Schema Documentation Generator | 1 | 1 | 0 | 0 | **PASS** |
| Reverse Engineering (Safe Seeders & Enums) | 1 | 1 | 0 | 0 | **PASS** |
| Index Standardization Command | 2 | 2 | 0 | 0 | **PASS** |
| Data Drift Command | 2 | 2 | 0 | 0 | **PASS** |
| Interactive Help Board | 1 | 1 | 0 | 0 | **PASS** |
| **Total** | **19** | **19** | **0** | **0** | **100% PASS** |

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
