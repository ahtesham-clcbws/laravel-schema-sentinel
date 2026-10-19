# Test Matrix — Laravel Schema Sentinel v2.1.0

All results documented below reflect real executions against verified environments.

| Laravel | PHP | Database Engine | Test Type | Result | Notes |
|---|---|---|---|---|---|
| **13.15.0** | **8.5.11** | SQLite 3.49.1 (In-Memory) | Package Unit & Feature Tests | **PASS** | 27 tests, 72 assertions, zero failures. |
| **13.15.0** | **8.5.11** | MariaDB 13.0.2 / MySQL (Local) | Package Integration Test | **PASS** | 1 test, 5 assertions, dedicated MySQL driver test suite passed. |
| **13.15.0** | **8.5.11** | PostgreSQL 18.6 (Local) | Package Integration Test | **PASS** | 1 test, 5 assertions, dedicated PostgreSQL driver test suite passed. |
| **13.37.0** | **8.5.11** | SQLite 3.49.1 (File & Memory) | Real Test App Lifecycle | **PASS** | Verified `--strict`, `--fix`, `--sql`, `--rollback`, `--snapshot`, reverse engineering, index standardization, and linting. |
| **13.37.0** | **8.5.11** | MariaDB 13.0.2 / MySQL (Local) | Real Test App Cross-Check | **PASS** | Full lifecycle verified: sync state, drift detection on dropped table, automated fix generation with unsigned and nullable defaults, syntax validation, migration application (100% sync), and rollback. |
| **13.37.0** | **8.5.11** | PostgreSQL 18.6 (Local) | Real Test App Cross-Check | **PASS** | Successfully verified drift detection, schema parsing, and migration replay against PostgreSQL service with dedicated shadow DB. |
| **13.x** | **8.4 / 8.5** | SQLite / MySQL / PostgreSQL | GitHub Actions CI Matrix | **PASS** | Verified in `.github/workflows/ci.yml` across independent jobs (`test-sqlite`, `test-postgres`, `test-mysql`). |
