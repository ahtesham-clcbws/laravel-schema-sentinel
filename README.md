# Laravel Schema Sentinel

[![Latest Version on GitHub](https://img.shields.io/github/v/release/ahtesham-clcbws/laravel-schema-sentinel?include_prereleases&style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Laravel Schema Sentinel** is a premium database integrity tool designed to detect and resolve "Schema Drift"—the discrepancies between your migration files and your actual live database. Fully optimized for **Laravel 13.x**, with legacy support for 12.x and 11.x.

---

## ✨ Key Features

- 🛡️ **Deep Drift Detection**: Audits Tables, Columns, Types, Nullability, Defaults, Indexes, and Foreign Keys.
- 🐢 **Slow Migration Detector**: Identifies migrations that take too long to run, preventing production downtime.
- 📄 **Auto-Documentation**: Generate comprehensive Markdown documentation (`DATABASE.md`) of your entire schema.
- 🔄 **Rollback Audit**: Verify that your migrations can be rolled back safely without breaking the DB schema.
- 🌍 **Cross-Environment Sync**: Compare your local schema against Staging or Production directly.
- 📉 **Squash Advisor**: Detects bloated migration folders and suggests optimizations to speed up your workflow.
- 💯 **Schema Health Score**: Get a quantifiable percentage (0-100%) of your database integrity.
- 🚦 **Pre-Migration Guard**: Automatically block `php artisan migrate` if drift is detected.
- 🏷️ **Tagged Auditing**: Group migrations by tags and audit specific modules using the `--tag` option.
- 🧹 **Migration Linter**: Audit your migration files for anti-patterns that cause drift.
- 🔔 **Drift Notifications**: Automatically send alerts to Slack or Discord when drift is detected.
- 🗺️ **Custom Type Mapping**: Map specialized database types to Blueprint methods.
- 🧙 **Interactive Auto-Fix**: Automatically detects failing migrations and offers to add them to your skip list.
- 📸 **Schema Snapshotting**: Save your ideal schema to JSON to speed up audits.
- 🧪 **Dry-Run Mode**: Use `--sql` to preview the migration code in your terminal.
- 📊 **Visual Dashboard**: A built-in Livewire component for real-time health monitoring (Local only).
- 📐 **Index Normalization**: Automatically matches indexes by column and type.
- 🛡️ **Total Isolation**: Redirection engine that prevents leaking into your live DB.
- 🤖 **CI/CD Ready**: Returns standard exit codes for automated pipeline enforcement.
- 🧩 **UI Friendly**: Programmatic API via the `Sentinel` Facade.

---

## 📦 Installation

You can install the package via composer:

```bash
composer require clcbws/laravel-schema-sentinel
```

The service provider and facade will be automatically registered.

---

## 🚀 Usage

### 1. Terminal Interface (Artisan)

The primary way to use Sentinel is via the Artisan CLI.

#### Check for Drift
To see the gaps between your code and database:
```bash
php artisan schema:drift
```

#### Audit Rollbacks (Reversibility)
Simulate running ALL migrations and then rolling them all back:
```bash
php artisan schema:drift --rollback
```

#### Sync Across Environments
Compare your Local schema against your Staging database:
```bash
php artisan schema:drift --compare-env=staging
```

#### Audit by Tag (Modular Apps)
Only check migrations tagged with `@sentinel-tag billing`:
```bash
php artisan schema:drift --tag=billing
```

#### Audit Migration Files (Linter)
To scan your migrations for anti-patterns that cause drift:
```bash
php artisan schema:sentinel-lint
```

#### Intelligent Config Upgrade
If you are upgrading from an older version, run this to add new config options without losing your existing settings:
```bash
php artisan schema:sentinel-install
```

#### Health Check (Doctor)
To verify your environment is ready for Sentinel:
```bash
php artisan schema:sentinel-doctor
```

#### Create a Schema Snapshot
To freeze your current ideal schema for faster future audits:
```bash
php artisan schema:snapshot
```

#### Use a Snapshot for Drift Check
```bash
php artisan schema:drift --snapshot=latest
```

#### Dry-Run (SQL Preview)
To see the migration code without creating any files:
```bash
php artisan schema:drift --sql
```

#### Fix Drift Interactively
To generate a new migration file while reviewing each change:
```bash
php artisan schema:drift --fix --interactive
```

#### Strict Mode
To identify extra columns or tables in the DB that shouldn't be there:
```bash
php artisan schema:drift --strict
```

---

## ⚙️ Configuration

You can publish the configuration file using:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

The config file allows you to:
- Define **Ignored Tables** (e.g., third-party package tables).
- Add **Custom Migration Paths** for modular apps.
- Configure the **Shadow Connection** settings.
- **Skip Migrations** that are broken or incompatible with SQLite.

---

## 🛠️ Troubleshooting

### Shadow Migration Failures
If `php artisan schema:drift` fails during the simulation step, it usually means your `database/migrations` folder is incomplete or has SQLite incompatibilities.

1. **Missing Tables**: If a migration tries to `Schema::table()` a table that wasn't created in an earlier migration, the simulation will fail.
2. **Interactive Skip**: Sentinel will identify the failing file and ask: `Would you like me to add it for you automatically?`. Typing `y` will add it to your `skip_migrations` config.
3. **MySQL Shadow**: If your migrations use MySQL-specific features (spatial types, complex alters), change the `shadow_connection` driver to `mysql` in `config/schema-sentinel.php` to use a real MySQL database for simulation.

---

### 2. UI Integration (Controllers, Livewire, Blade)

Sentinel provides a powerful programmatic API via the `Sentinel` facade, allowing you to build custom database monitoring dashboards.

#### 🎮 Controller Usage
Perfect for building custom admin APIs or JSON health endpoints.

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

public function checkStatus()
{
    $diff = Sentinel::check(strict: true);

    return response()->json([
        'in_sync' => !$diff->hasDifferences(),
        'drift' => $diff->toArray(), // DTOs are arrayable
    ]);
}
```

#### ⚡ Livewire Integration
Build a real-time "Database Health" indicator for your admin panel.

```php
namespace App\Livewire;

use Livewire\Component;
use Sentinel\SchemaSentinel\Facades\Sentinel;

class DatabaseHealth extends Component
{
    public function render()
    {
        return view('livewire.database-health', [
            'diff' => Sentinel::check(),
        ]);
    }
}
```

#### 🍃 Blade Templates
Quickly show an alert to administrators if the schema is out of sync.

```blade
@if(app()->environment('local') && \Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-warning">
        <strong>🛡️ Sentinel Notice:</strong> Your database schema has drifted from your migrations. 
        Run <code>php artisan schema:drift</code> to review.
    </div>
@endif
```

#### 📊 Visual Dashboard (Livewire)
You can drop the built-in dashboard component into any Blade view (e.g., your admin dashboard). It is automatically hidden in non-local environments for security.

```blade
<livewire:sentinel-database-health />
```

---

## 🏗️ Architecture

Sentinel follows a strictly decoupled architecture:
1. **Shadow Runner**: Builds a "Shadow DB" by running all migrations.
2. **Schema Parser**: Normalizes both Live and Shadow schemas into DTOs.
3. **Diff Engine**: Analyzes the DTOs to find discrepancies.
4. **Migration Generator**: Translates the diff into valid Laravel PHP code.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

## 🤝 Credits

- **Author**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **Company**: [Broadway Web Service](https://www.clcbws.com)

---
