# Laravel Schema Sentinel

[![Latest Version on GitHub](https://img.shields.io/github/v/release/ahtesham-clcbws/laravel-schema-sentinel?include_prereleases&style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Laravel Schema Sentinel** is a premium database schema integrity and governance suite. It detects and resolves "Schema Drift" (discrepancies between migration blueprints and the actual database state) and provides automated tools to standardize index configurations, audit content syncs, lint migration files, and reverse-engineer legacy tables.

---

## 📖 Table of Contents

- [Getting Started](#-getting-started)
  - [Introduction](#introduction)
  - [Key Features](#key-features)
  - [Installation](#installation)
  - [Configuration](#configuration)
- [Artisan Commands](#-artisan-commands)
  - [Auditing Schema Drift (`schema:drift`)](#auditing-schema-drift-schemadrift)
  - [Index Standardization (`schema:standardize-indexes`)](#index-standardization-schemastandardize-indexes)
  - [Data Drift Deep Dive (`schema:data-drift`)](#data-drift-deep-dive-schemadata-drift)
  - [Legacy Database Reversing (`schema:reverse`)](#legacy-database-reversing-schemareverse)
  - [Migration File Linter (`schema:sentinel-lint`)](#migration-file-linter-schemasentinel-lint)
  - [Environment Doctor (`schema:sentinel-doctor`)](#environment-doctor-schemasentinel-doctor)
- [Programmatic API](#-programmatic-api)
  - [Drift Auditing](#drift-auditing)
  - [Parsing Schema DTOs](#parsing-schema-dtos)
  - [Index Standardization](#index-standardization)
  - [Data Drift Audits](#data-drift-audits)
  - [Programmatic Reversing](#programmatic-reversing)
  - [Livewire Component & Blade UI](#livewire-component--blade-ui)
- [Legacy Compatibility](#-legacy-compatibility)
- [Changelogs](#-changelogs)

---

## 🚀 Getting Started

### Introduction

In modern application deployment, database schemas can easily drift from migration files due to hotfixes, legacy imports, direct database edits, or faulty rollbacks. Schema Sentinel provides a total isolation simulation engine that runs migrations on a shadow database to establish the "ideal" schema, matches it against your live connection, and generates fixes automatically.

### Key Features

*   🛡️ **Deep Drift Detection**: Audits Tables, Columns, Types, Nullability, Defaults, Indexes, and Foreign Keys.
*   ⚡ **Programmatic Facade**: Full API coverage for building custom monitoring dashboards.
*   📐 **Index Naming Standardizer**: Automatically validates index names against Laravel conventions.
*   🐢 **Slow Migration Audit**: Pinpoints slow migration files during simulation.
*   🔄 **Rollback Audit**: Simulates rollbacks to ensure your schema can be cleanly rolled back.
*   🌍 **Cross-Environment Sync**: Compare your schema against other environments (Staging/Production).
*   🤖 **CI/CD Ready**: Returns standard shell exit codes for pipeline validation.

### Installation

Install the package via Composer:

```bash
composer require clcbws/laravel-schema-sentinel
```

The service provider and facade will be registered automatically.

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

This creates `config/schema-sentinel.php` which allows you to define:
- **`ignore_tables`**: Array of table names to exclude from analysis (e.g., `migrations`, `telescope_entries`).
- **`migration_paths`**: Paths to your migration files (useful for modular applications).
- **`shadow_connection`**: Temporary database settings (defaults to isolated SQLite in-memory).
- **`skip_migrations`**: List of migration files to skip during shadow simulation.
- **`notifications`**: Slack and Discord webhooks for drift alerts.

---

## 💻 Artisan Commands

### Auditing Schema Drift (`schema:drift`)

Run the core schema audit to inspect the health of your database:

```bash
php artisan schema:drift
```

#### Options:

*   **`--fix`**: Generate a new Laravel migration to align your database.
*   **`--interactive`**: Confirm column changes step-by-step.
*   **`--sql`**: Preview generated SQL code in dry-run mode.
*   **`--strict`**: Report extra tables and columns in the live DB that are not defined in migrations.
*   **`--rollback`**: Run migrations and roll them back on the shadow DB to verify reversibility.
*   **`--snapshot[=latest]`**: Use a frozen JSON snapshot for audit speed.
*   **`--tag=name`**: Limit audits to migrations marked with `@sentinel-tag name`.

---

### Index Standardization (`schema:standardize-indexes`)

Inspect your database for non-standard index names and redundant indexes (e.g., column indexes already covered by composite index prefixes):

```bash
php artisan schema:standardize-indexes
```

#### Options:
*   **`--fix`**: Generate a migration file to automatically rename deviating indexes and drop duplicate ones.

---

### Data Drift Deep Dive (`schema:data-drift`)

Audit static lookup or seed tables to verify content consistency between environments:

```bash
php artisan schema:data-drift --compare-env=production
```

Outputs a terminal-based diff table indicating missing, extra, or mismatched rows.

---

### Legacy Database Reversing (`schema:reverse`)

Reverse-engineer a legacy database into a clean Laravel setup:

```bash
php artisan schema:reverse --path=./exports
```

#### Options:
*   **`--models`**: Generate Eloquent Model classes with typed relationships (`belongsTo`, `hasMany`) and docblocks.
*   **`--migrations`**: Generate Blueprint migration files.
*   **`--seeders`**: Generate seeders filled with sample database records.
*   **`--enums`**: Generate native PHP 8.4 Backed Enums linked to model casts.

---

### Migration File Linter (`schema:sentinel-lint`)

Audit your codebase migrations for anti-patterns that can cause drift:

```bash
php artisan schema:sentinel-lint
```

Scans for:
1. Raw `DB::statement` calls.
2. Hardcoded platform-specific string default dates (like `'CURRENT_TIMESTAMP'`).
3. Unsafe `Schema::drop` calls lacking `IfExists`.

---

### Environment Doctor (`schema:sentinel-doctor`)

Run the health advisor to verify your environment configurations (PHP version, PDO drivers, shadow connection configs):

```bash
php artisan schema:sentinel-doctor
```

---

### Help Guides & Spelling Matcher (`schema:help`)

Get detailed command help, configuration tips, and options. If you make a typo, the built-in Levenshtein suggestion engine will automatically suggest the correct command:

```bash
php artisan schema:help drft
```

---

## 🔌 Programmatic API

You can integrate Sentinel programmatically inside controllers, dashboards, or deployment hooks using the `Sentinel` facade.

### Drift Auditing

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // Schema has drifted
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### Parsing Schema DTOs

Extract structured metadata representing your database layout:

```php
$tables = Sentinel::parse(); // Returns array of TableDefinition DTOs
```

### Index Standardization

Run the index analyzer programmatically:

```php
$results = Sentinel::standardizeIndexes();
// Returns array with 'deviations' and 'redundant' indexes
```

### Data Drift Audits

Audit data consistency against a target connection:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### Programmatic Reversing

Trigger the reverse engineering generator from code:

```php
$results = Sentinel::reverse([
    'path' => base_path('exports'),
    'models' => true,
    'migrations' => true,
    'seeders' => false,
    'enums' => true,
]);
```

### Livewire Component & Blade UI

Display database integrity metrics inside your admin templates:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

Or check programmatically in Blade layouts:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        Warning: Database schema drift detected!
    </div>
@endif
```

---

## ⚠️ Legacy Compatibility

While the latest version of this package requires **Laravel 13.x** and **PHP 8.4+**, developers running **Laravel 11.x or 12.x** (with PHP 8.2 or 8.3) can use the stable **v1.6.0** release:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| Laravel Version | Supported PHP Versions | Package Version |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 📄 Changelogs

### Latest Release: v2.0.0

*   **Index Standardizer & Optimizer**: Detects duplicate indexes and enforces Laravel naming conventions.
*   **Data Drift Engine**: Audits static data tables across different database connections.
*   **Legacy Bridge Generator**: Reverse-engineers databases into modern migrations, models with relationships, seeders, and PHP 8.4 Backed Enums.
*   **PHP 8.4 / Laravel 13 Alignment**: Full type-safety compliance, strict typing enabled file-wide, and console command attributes.
*   **CLI Bug Fixes**: Corrected infinite loop retry states, docs property errors, and bigInt mappings.

For past version releases, see [changelogs/v1.0.0.md](changelogs/v1.0.0.md) and [changelogs/v1.6.0.md](changelogs/v1.6.0.md).

---

## 🤝 Credits

- **Author**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **Company**: [Broadway Web Service](https://www.clcbws.com)
- **License**: [MIT License](LICENSE)
