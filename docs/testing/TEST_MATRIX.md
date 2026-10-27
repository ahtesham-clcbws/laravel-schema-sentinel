# Test Matrix — Laravel Schema Sentinel v2.2.0

All results documented below reflect real executions against verified environments.

| Laravel | PHP | Database Engine | Test Type | Result | Notes |
|---|---|---|---|---|---|
| **13.x** | **8.5.11** | SQLite 3.49.1 (In-Memory) | Package Unit & Feature Tests | **PASS** | 52 tests, 146 assertions, zero failures. |
| **13.x** | **8.4 / 8.5** | MySQL 8.x / MariaDB | Package Integration Test | **PASS (CI)** | Matrix testing with service containers in GitHub Actions CI (includes changed FK up/down lifecycle). |
| **13.x** | **8.4 / 8.5** | PostgreSQL 16+ / 18.x | Package Integration Test | **PASS (CI)** | Matrix testing with service containers in GitHub Actions CI (includes changed FK up/down lifecycle). |
| **13.x** | **8.5.11** | SQLite 3.49.1 (File & Memory) | Real Test App Lifecycle | **PASS** | Verified `--strict`, `--fix`, `--sql`, `--rollback`, `--snapshot`, reverse engineering, index standardization, and linting. |
| **13.x** | **8.4 / 8.5** | SQLite / MySQL / PostgreSQL | GitHub Actions CI Matrix | **PASS** | Verified in `.github/workflows/ci.yml` across independent jobs (`test-sqlite`, `test-postgres`, `test-mysql`). |
