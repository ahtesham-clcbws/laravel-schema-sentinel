# Laravel Schema Sentinel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![License](https://img.shields.io/packagist/l/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)

**Laravel Schema Sentinel** is a premium database integrity tool designed to detect and resolve "Schema Drift"—the discrepancies between your migration files and your actual live database. Fully optimized for **Laravel 13.x**, with legacy support for 12.x and 11.x.

---

## ✨ Key Features

- 🛡️ **Deep Drift Detection**: Audits Tables, Columns, Types, Nullability, Defaults, Indexes, and Foreign Keys.
- 🚀 **Virtual Migration Engine**: Safely simulates your entire migration history in a shadow SQLite database.
- 🛠️ **Automated Fixer**: Generates bi-directional migrations (`up()` and `down()`) to bridge gaps while maintaining rollbacks.
- 🔍 **Strict Mode**: Identifies "unauthorized" DB changes or legacy artifacts not tracked in your code.
- 🧙 **Interactive Wizard**: Prompts you for confirmation before including specific fixes in generated migrations.
- 🤖 **CI/CD Ready**: Returns standard exit codes (0 for sync, 1 for drift) for automated pipeline enforcement.
- 🧩 **UI Friendly**: Programmatic API via the `Sentinel` Facade for integration with custom admin panels or Livewire.

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

#### Health Check (Doctor)
To verify your environment is ready for Sentinel:
```bash
php artisan schema:sentinel-doctor
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

### GitHub Metadata Suggestions

**Description**: 🛡️ Detect and fix database schema drift in Laravel by safely simulating migrations in-memory and comparing them to the live state.

**Tags**: `laravel`, `database`, `schema-drift`, `migrations`, `dev-tools`, `automated-fixing`, `php`, `database-integrity`
