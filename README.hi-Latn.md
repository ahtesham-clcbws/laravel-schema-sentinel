# Laravel Schema Sentinel

[![Latest Stable Version](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![GitHub Stars](https://img.shields.io/github/stars/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![CI Tests](https://img.shields.io/github/actions/workflow/status/ahtesham-clcbws/laravel-schema-sentinel/ci.yml?branch=development&style=flat-square&label=tests)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Read this in:** [English](README.md) | **Roman Hindustani** | [हिन्दी](README.hi.md) | [اردو](README.ur.md) | [العربية](README.ar.md) | [Español](README.es.md) | [Português (Brasil)](README.pt-BR.md)

> [!NOTE]
> Yeh Roman Hindustani translation developers ke aasaani ke liye banayi gayi hai. Asal canonical source document [English README](README.md) hai.

**Laravel Schema Sentinel** ek powerful database schema integrity aur governance tool hai. Yeh aapki migration files aur asal database ke darmiyan hone wale "Schema Drift" (yani structure ke farq) ko detect aur theek karta hai. Iske alawa yeh indexes ko standardize karne, content sync audit karne, migrations lint karne aur legacy tables ko reverse-engineer karne ke automated tools deta hai.

---

## 📖 Fehris (Table of Contents)

- [Shuruat (Getting Started)](#-shuruat-getting-started)
  - [Taaruf (Introduction)](#taaruf-introduction)
  - [Khaas Khubiyan (Key Features)](#khaas-khubiyan-key-features)
  - [Installation](#installation)
  - [Configuration](#configuration)
- [Artisan Commands](#-artisan-commands)
  - [Schema Drift Audit (`schema:drift`)](#schema-drift-audit-schemadrift)
  - [Index Standardization (`schema:standardize-indexes`)](#index-standardization-schemastandardize-indexes)
  - [Data Drift Audit (`schema:data-drift`)](#data-drift-audit-schemadata-drift)
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
- [Puraane Versions (Legacy Compatibility)](#-puraane-versions-legacy-compatibility)
- [Compatibility aur Test Status](#-compatibility-aur-test-status)
- [Changelogs](#-changelogs)

---

## 🚀 Shuruat (Getting Started)

### Taaruf (Introduction)

Real-world development mein aksar hotfixes, direct database edits ya adhoore rollbacks ki wajah se database schema aur migration files ke darmiyan farq (drift) paida ho jata hai. Schema Sentinel ek isolated shadow database par migrations run karta hai taake "ideal" schema tayyar ho sake, phir usko aapke live database se match karke automatically fixes generate karta hai.

### Khaas Khubiyan (Key Features)

*   🛡️ **Mukammal Drift Detection**: Tables, Columns, Data Types, Nullability, Defaults, Indexes aur Foreign Keys ka audit karta hai.
*   ⚡ **Programmatic Facade**: Custom dashboards banane ke liye poori API support.
*   📐 **Index Naming Standardizer**: Index ke naamon ko Laravel ke standard conventions ke mutabiq check karta hai.
*   🐢 **Slow Migration Audit**: Simulation ke dauran sust migrations ki nishandehi karta hai.
*   🔄 **Rollback Audit**: Rollback test karke yaqeeni banata hai ke schema safely revert ho sake.
*   🌍 **Cross-Environment Sync**: Local schema ko doosre environments (jaise Staging ya Production) se compare karein.
*   🤖 **CI/CD Ready**: CI/CD pipelines mein automated check ke liye standard shell exit codes deta hai.

### Installation

Composer ke zariye package install karein:

```bash
composer require clcbws/laravel-schema-sentinel
```

Service provider aur facade automatically register ho jayenge.

### Configuration

Config file ko publish karein:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

Is se `config/schema-sentinel.php` file banegi jisme aap yeh set kar sakte hain:
- **`ignore_tables`**: Jin tables ko audit se alag rakhna ho (maslan `migrations`, `telescope_entries`).
- **`migration_paths`**: Migration files ke raaste (modular apps ke liye mufeed).
- **`shadow_connection`**: Temporary shadow database ki settings (by default isolated SQLite in-memory).
- **`skip_migrations`**: Simulation ke waqt chhorne wali migrations ki list.
- **`notifications`**: Slack aur Discord webhooks drift alerts ke liye.

---

## 💻 Artisan Commands

### Schema Drift Audit (`schema:drift`)

Database ki sehat check karne ke liye core audit run karein:

```bash
php artisan schema:drift
```

#### Options:

*   **`--fix`**: Database ko sync karne ke liye nayi Laravel migration generate karein.
*   **`--interactive`**: Har column change ko step-by-step confirm karein.
*   **`--sql`**: Dry-run mode mein banne wali SQL statements preview karein.
*   **`--strict`**: Aise extra tables aur columns report karein jo live DB mein hain magar migrations mein nahi.
*   **`--rollback`**: Shadow DB par rollback run karke reversibility test karein.
*   **`--snapshot[=latest]`**: Tezi se audit karne ke liye pehle se save JSON snapshot use karein.
*   **`--tag=name`**: Sirf specific `@sentinel-tag name` wali migrations ka audit karein.

---

### Index Standardization (`schema:standardize-indexes`)

Ghair-mayari index names aur duplicate indexes talash karein:

```bash
php artisan schema:standardize-indexes
```

#### Options:
*   **`--fix`**: Deviating indexes ko rename karne aur duplicate indexes ko drop karne ke liye migration generate karein.

---

### Data Drift Audit (`schema:data-drift`)

Mukhtalif environments ke darmiyan static tables (jaise roles, settings) ka content milayein:

```bash
php artisan schema:data-drift --compare-env=production
```

Terminal par table format mein missing, extra ya badle hue records dikhaye jayenge.

---

### Legacy Database Reversing (`schema:reverse`)

Puraane database ko saaf-suthre Laravel codebase mein tabdeel karein:

```bash
php artisan schema:reverse --path=./exports
```

#### Options:
*   **`--models`**: Eloquent Model classes generate karein (relationships aur docblocks ke sath).
*   **`--migrations`**: Blueprint migration files banayein.
*   **`--seeders`**: Seeders generate karein.
*   **`--enums`**: PHP 8.4 Backed Enums generate karein.

---

### Migration File Linter (`schema:sentinel-lint`)

Migrations ko aisi khamiyon ke liye scan karein jo drift ka sabab banti hain:

```bash
php artisan schema:sentinel-lint
```

Yeh scan karta hai:
1. Raw `DB::statement` calls.
2. Hardcoded date defaults (jaise `'CURRENT_TIMESTAMP'`).
3. Khatarnaak `Schema::drop` calls jo `dropIfExists` ke baghair hon.

---

### Environment Doctor (`schema:sentinel-doctor`)

Apne environment ki health check karein (PHP version, PDO drivers, shadow connection configs):

```bash
php artisan schema:sentinel-doctor
```

---

### Help Guides & Spelling Matcher (`schema:help`)

Detailed command help aur misalein dekhein. Agar command type karte waqt ghalti ho jaye to built-in Levenshtein suggestion engine khud sahi command suggest karega:

```bash
php artisan schema:help drft
```

---

## 🔌 Programmatic API

Controllers, dashboards ya deployment scripts mein `Sentinel` facade ka istemal karein:

### Drift Auditing

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // Schema drift detect hua hai
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### Parsing Schema DTOs

Database ka structured metadata hasil karein:

```php
$tables = Sentinel::parse(); // TableDefinition DTOs ka array return karega
```

### Index Standardization

Indexes ka tajziya programmatically run karein:

```php
$results = Sentinel::standardizeIndexes();
// 'deviations' aur 'redundant' indexes ka array return karega
```

### Data Drift Audits

Kisi connection ke khilaf data match karein:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### Programmatic Reversing

Code ke zariye reverse engineering trigger karein:

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

Admin templates mein database integrity dashboard dikhayein:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

Ya Blade layouts mein programmatically check karein:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        Warning: Database schema drift detected!
    </div>
@endif
```

---

## ⚠️ Puraane Versions (Legacy Compatibility)

Yeh package **Laravel 13.x** aur **PHP 8.4+** ke liye design kiya gaya hai. Agar aap **Laravel 11.x ya 12.x** (PHP 8.2 ya 8.3 ke sath) use kar rahe hain to hamara stable **v1.6.0** release istemal karein:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| Laravel Version | Supported PHP Versions | Package Version |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 🛡️ Compatibility aur Test Status

- **Laravel Compatibility**: **Laravel 13.x ONLY** (Laravel 10/11/12 ke liye backward shims shaamil nahi hain).
- **Minimum PHP Version**: **PHP 8.4+** (PHP 8.4 aur PHP 8.5 par poori tarah tested).
- **Tested Database Engines**:
  - **SQLite**: 3.49+ (In-memory aur file-based).
  - **PostgreSQL**: 16+ / 18+ (Dedicated shadow connection ke sath tested).
  - **MySQL / MariaDB**: 8.0+ / 13.0+ (CI container matrix mein verified).
- **Asal Test Reports aur Evidence**:
  - [Test Matrix](docs/testing/TEST_MATRIX.md)
  - [Real Application Test Results](docs/testing/TEST_RESULTS.md)
  - [Known Limitations & Dialect Behavior](docs/testing/KNOWN_LIMITATIONS.md)
  - [Performance Benchmarks](docs/testing/PERFORMANCE.md)

---

## 📄 Changelogs

### Upcoming Release: v2.1.0 (Under Final Review)

*   **Shadow Migration Isolation**: `ShadowMigrationRunner` ko fully isolate kiya gaya taake global connections par asar na pare.
*   **Pre-Migration Guard**: Runtime `exit(1)` ko `MigrationBlockedException` se replace kiya gaya aur safe bypass mechanism joda gaya.
*   **DiffEngine & Type Normalization**: Mukhtalif database engines ke darmiyan types (`varchar`, `bigint`, `boolean`), compound indexes aur foreign key actions ke false positives theek kiye gaye.
*   **Migration Generator**: Generated table creation mein primary keys, indexes aur foreign keys shaamil kiye gaye, aur Laravel 13 ke liye `--realpath` support add ki gayi.
*   **Data Safety**: Generated seeders se live database records nikaalna band kiya gaya aur external telemetry mukammal khatam ki gayi.
*   **Laravel 13 Ecosystem Validation**: Standalone Laravel 13 app mein test kiya gaya aur GitHub Actions matrix set kiya gaya.

Pichle versions ke changelogs dekhne ke liye: [changelogs/v2.0.0.md](changelogs/v2.0.0.md), [changelogs/v1.6.0.md](changelogs/v1.6.0.md) aur [changelogs/v1.0.0.md](changelogs/v1.0.0.md).

---

## 🤝 Credits

- **Author**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **Company**: [Broadway Web Service](https://www.clcbws.com)
- **License**: [MIT License](LICENSE)
