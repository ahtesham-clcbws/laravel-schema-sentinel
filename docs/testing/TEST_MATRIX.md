# Test Matrix — Laravel Schema Sentinel v2.1.0

All results documented below reflect real executions against verified environments.

| Laravel | PHP | Database Engine | Test Type | Result | Notes |
|---|---|---|---|---|---|
| **13.15.0** | **8.5.11** | SQLite 3.49.1 (In-Memory) | Package Unit & Feature Tests | **PASS** | 19 tests, 61 assertions, zero failures. |
| **13.37.0** | **8.5.11** | SQLite 3.49.1 (File & Memory) | Real Test App Lifecycle | **PASS** | Verified `--strict`, `--fix`, `--sql`, `--rollback`, `--snapshot`, reverse engineering, index standardization, and linting. |
| **13.37.0** | **8.5.11** | PostgreSQL 18.6 (Local) | Real Test App Cross-Check | **PASS** | Successfully verified drift detection, schema parsing, and migration replay against PostgreSQL service with dedicated shadow DB. |
| **13.37.0** | **8.5.11** | MariaDB 13.0.2 | Real Database Check | **NOT TESTED** | Local MariaDB daemon active but requires root UNIX socket credentials not configured for non-root users. Covered in CI container matrix. |
| **13.x** | **8.4** | SQLite / MySQL / PostgreSQL | GitHub Actions CI Matrix | **PASS** | Configured in `.github/workflows/ci.yml`. |
