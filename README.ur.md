# Laravel Schema Sentinel

[![Latest Stable Version](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![GitHub Stars](https://img.shields.io/github/stars/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![CI Tests](https://img.shields.io/github/actions/workflow/status/ahtesham-clcbws/laravel-schema-sentinel/ci.yml?branch=development&style=flat-square&label=tests)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Read this in:** [English](README.md) | [Roman Hindustani](README.hi-Latn.md) | [हिन्दी](README.hi.md) | **اردو** | [العربية](README.ar.md) | [Español](README.es.md) | [Português (Brasil)](README.pt-BR.md)

> [!NOTE]
> یہ دستاویز اردو بولنے والے ڈیولپرز کی سہولت کے لیے ترجمہ کی گئی ہے۔ بنیادی اور مستند ماخذ [English README](README.md) ہے۔

**Laravel Schema Sentinel** ایک جدید اور طاقتور ڈیٹا بیس اسکیما انٹیگریٹی (integrity) اور گورننس ٹول ہے۔ یہ آپ کی مائیگریشن فائلوں اور حقیقی ڈیٹا بیس کے درمیان پائے جانے والے "Schema Drift" (ڈھانچے کے فرق) کو تلاش کر کے خودکار طور پر حل کرتا ہے۔ مزید برآں، یہ انڈیکس کنفیگریشن کو معیاری بنانے، ڈیٹا کے فرق کا آڈٹ کرنے، مائیگریشن فائلز کی جانچ پڑتال کرنے اور پرانے ڈیٹا بیس کو ریورس انجینئر کرنے کی جامع سہولیات فراہم کرتا ہے۔

---

## 📖 فہرست مضامین (Table of Contents)

- [آغاز (Getting Started)](#-آغاز-getting-started)
  - [تعارف (Introduction)](#تعارف-introduction)
  - [اہم خصوصیات (Key Features)](#اہم-خصوصیات-key-features)
  - [تنصیب (Installation)](#تنصیب-installation)
  - [کنفیگریشن (Configuration)](#کنفیگریشن-configuration)
- [Artisan کمانڈز (Artisan Commands)](#-artisan-کمانڈز-artisan-commands)
  - [اسکیما ڈرفٹ آڈٹ (`schema:drift`)](#اسکیما-ڈرفٹ-آڈٹ-schemadrift)
  - [انڈیکس کا معیار بنانا (`schema:standardize-indexes`)](#انڈیکس-کا-معیار-بنانا-schemastandardize-indexes)
  - [ڈیٹا ڈرفٹ آڈٹ (`schema:data-drift`)](#ڈیٹا-ڈرفٹ-آڈٹ-schemadata-drift)
  - [ڈیٹا بیس ریورس انجینئرنگ (`schema:reverse`)](#ڈیٹا-بیس-ریورس-انجینئرنگ-schemareverse)
  - [مائیگریشن فائل لنٹر (`schema:sentinel-lint`)](#مائیگریشن-فائل-لنٹر-schemasentinel-lint)
  - [ماحول کا معائنہ (`schema:sentinel-doctor`)](#ماحول-کا-معائنہ-schemasentinel-doctor)
- [پروگرامنگ API (Programmatic API)](#-پروگرامنگ-api-programmatic-api)
  - [ڈرفٹ آڈٹ (Drift Auditing)](#ڈرفٹ-آڈٹ)
  - [اسکیما DTOs پارس کرنا](#اسکیما-dtos-پارس-کرنا)
  - [انڈیکس کا تجزیہ](#انڈیکس-کا-تجزیہ)
  - [ڈیٹا ڈرفٹ کا تجزیہ](#ڈیٹا-ڈرفٹ-کا-تجزیہ)
  - [کوڈ کے ذریعے ریورسنگ](#کوڈ-کے-ذریعے-ریورسنگ)
  - [Livewire جزو اور Blade انٹرفیس](#livewire-جزو-اور-blade-انٹرفیس)
- [پرانے ورژنز سے مطابقت (Legacy Compatibility)](#-پرانے-ورژنز-سے-مطابقت-legacy-compatibility)
- [مطابقت اور ٹیسٹ کی صورتحال (Compatibility & Test Status)](#-مطابقت-اور-ٹیسٹ-کی-صورتحال-compatibility--test-status)
- [تبدیلیوں کی تفصیل (Changelogs)](#-تبدیلیوں-کی-تفصیل-changelogs)

---

## 🚀 آغاز (Getting Started)

### تعارف (Introduction)

پروڈکشن ایپلی کیشنز میں لائیو ہاٹ فکسز، براہ راست ڈیٹا بیس کی تبدیلیوں یا نامکمل رول بیکس کی وجہ سے مائیگریشن فائلوں اور لائیو ڈیٹا بیس کے مابین فرق پیدا ہو جاتا ہے۔ Schema Sentinel ایک الگ تھلگ شیڈو ڈیٹا بیس میں تمام مائیگریشنز چلا کر "مثالی" اسکیما بناتا ہے، اس کا موازنہ آپ کے اصل ڈیٹا بیس سے کرتا ہے اور خودکار طور پر اصلاحی مائیگریشن کوڈ تیار کرتا ہے۔

### اہم خصوصیات (Key Features)

*   🛡️ **جامع ڈرفٹ کی شناخت**: ٹیبلز، کالمز، ڈیٹا اقسام، نل ایبلٹی، ڈیفالٹس، انڈیکس اور فارن کیز کا گہرا معائنہ۔
*   ⚡ **پروگرامنگ فساڈ**: کسٹم مانیٹرنگ ڈیش بورڈز تیار کرنے کے لیے مکمل API سپورٹ۔
*   📐 **انڈیکس کا معیار**: Laravel کے اصولوں کے مطابق انڈیکس کے ناموں اور غیر ضروری ڈپلیکیٹس کی نشاندہی۔
*   🐢 **سست مائیگریشنز کا آڈٹ**: سمولیشن کے دوران سست مائیگریشن فائلوں کی نشاندہی۔
*   🔄 **رول بیک کی تصدیق**: شیڈو پر رول بیک ٹیسٹ کر کے محفوظ واپسی کی ضمانت۔
*   🌍 **مختلف ماحول کا موازنہ**: لوکل اسکیما کا موازنہ اسٹیجنگ یا پروڈکشن کنکشن کے ساتھ۔
*   🤖 **CI/CD کے لیے تیار**: پائپ لائنز کے لیے معیاری شیل ایگزٹ کوڈز۔

### تنصیب (Installation)

Composer کے ذریعے پیکج انسٹال کریں:

```bash
composer require clcbws/laravel-schema-sentinel
```

سروس پرووائیڈر اور فساڈ خودکار طور پر رجسٹر ہو جائیں گے۔

### کنفیگریشن (Configuration)

کنفیگریشن فائل پبلش کریں:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

اس سے `config/schema-sentinel.php` فائل بن جائے گی جس میں آپ درج ذیل تبدیل کر سکتے ہیں:
- **`ignore_tables`**: جن ٹیبلز کو آڈٹ میں شامل نہیں کرنا (جیسے `migrations` یا `telescope_entries`)۔
- **`migration_paths`**: مائیگریشن فائلوں کے راستے۔
- **`shadow_connection`**: عارضی شیڈو ڈیٹا بیس کی ترتیبات (پہلے سے ان میموری SQLite)۔
- **`skip_migrations`**: سمولیشن کے دوران چھوڑنے والی مائیگریشنز۔
- **`notifications`**: سلیک اور ڈسکارڈ الرٹس کے ویب ہکس۔

---

## 💻 Artisan کمانڈز (Artisan Commands)

### اسکیما ڈرفٹ آڈٹ (`schema:drift`)

ڈیٹا بیس کی صحت جانچنے کے لیے آڈٹ چلائیں:

```bash
php artisan schema:drift
```

#### اختیارات (Options):

*   **`--fix`**: ڈیٹا بیس کو ہم آہنگ کرنے کے لیے نئی مائیگریشن بنائیں۔
*   **`--interactive`**: ہر تبدیلی کی مرحلہ وار تصدیق کریں۔
*   **`--sql`**: ڈرائی رن موڈ میں SQL کوڈ کا پیش نظارہ دیکھیں۔
*   **`--strict`**: ایسے اضافی ٹیبلز اور کالمز رپورٹ کریں جو مائیگریشن میں موجود نہیں۔
*   **`--rollback`**: شیڈو ڈیٹا بیس پر رول بیک کی صلاحیت کی جانچ کریں۔
*   **`--snapshot[=latest]`**: فوری آڈٹ کے لیے محفوظ شدہ JSON اسنیپ شاٹ استعمال کریں۔
*   **`--tag=name`**: مخصوص ٹیگ والی مائیگریشنز کا آڈٹ کریں۔

---

### انڈیکس کا معیار بنانا (`schema:standardize-indexes`)

غیر معیاری اور اضافی انڈیکس چیک کریں:

```bash
php artisan schema:standardize-indexes
```

#### اختیارات:
*   **`--fix`**: ناموں کو درست کرنے اور اضافی انڈیکس ختم کرنے کے لیے مائیگریشن بنائیں۔

---

### ڈیٹا ڈرفٹ آڈٹ (`schema:data-drift`)

مختلف ماحول کے درمیان ریفرنس ٹیبلز کے ڈیٹا کا موازنہ کریں:

```bash
php artisan schema:data-drift --compare-env=production
```

ٹرمینل پر ٹیبل کی صورت میں غائب، اضافی یا تبدیل شدہ ریکارڈز ظاہر کیے جاتے ہیں۔

---

### ڈیٹا بیس ریورس انجینئرنگ (`schema:reverse`)

پرانے ڈیٹا بیس کو صاف ستھرے Laravel کوڈ میں تبدیل کریں:

```bash
php artisan schema:reverse --path=./exports
```

#### اختیارات:
*   **`--models`**: ماڈل کلاسز تیار کریں (تعلقات اور ڈاک بلاکس کے ساتھ)۔
*   **`--migrations`**: مائیگریشن فائلیں بنائیں۔
*   **`--seeders`**: محفوظ سیڈرز تیار کریں۔
*   **`--enums`**: PHP 8.4 Backed Enums تیار کریں۔

---

### مائیگریشن فائل لنٹر (`schema:sentinel-lint`)

مائیگریشن کوڈ کو ان غلطیوں کے لیے چیک کریں جو خرابی کا سبب بنتی ہیں:

```bash
php artisan schema:sentinel-lint
```

یہ چیک کرتا ہے:
1. خام `DB::statement` کالز۔
2. ہارڈ کوڈ شدہ تاریخ کے ڈیفالٹس (جیسے `'CURRENT_TIMESTAMP'`)۔
3. غیر محفوظ `Schema::drop` کالز جو `IfExists` کے بغیر ہوں۔

---

### ماحول کا معائنہ (`schema:sentinel-doctor`)

اپنے سسٹم کنفیگریشن اور ایکسٹینشنز کی جانچ کریں:

```bash
php artisan schema:sentinel-doctor
```

---

### مدد اور اسپیلنگ چیکر (`schema:help`)

کمانڈز کی تفصیل دیکھیں۔ اگر ٹائپنگ میں غلطی ہو جائے تو خودکار تجاویز حاصل کریں:

```bash
php artisan schema:help drft
```

---

## 🔌 پروگرامنگ API (Programmatic API)

کنٹرولرز، مانیٹرنگ ڈیش بورڈز یا اسکرپٹس میں `Sentinel` فساڈ کا استعمال کریں:

### ڈرفٹ آڈٹ

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // اسکیما میں فرق موجود ہے
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### اسکیما DTOs پارس کرنا

ڈیٹا بیس کی ساخت کو منظم آبجیکٹس میں حاصل کریں:

```php
$tables = Sentinel::parse(); // TableDefinition DTOs کی لسٹ
```

### انڈیکس کا تجزیہ

کوڈ کے ذریعے انڈیکس کا تجزیہ کریں:

```php
$results = Sentinel::standardizeIndexes();
```

### ڈیٹا ڈرفٹ کا تجزیہ

ٹارگٹ کنکشن کے خلاف ڈیٹا کا موازنہ کریں:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### کوڈ کے ذریعے ریورسنگ

کوڈ سے ریورس انجینئرنگ چلائیں:

```php
$results = Sentinel::reverse([
    'path' => base_path('exports'),
    'models' => true,
    'migrations' => true,
    'seeders' => false,
    'enums' => true,
]);
```

### Livewire جزو اور Blade انٹرفیس

اپنے ایڈمن پینل میں ڈیٹا بیس کی صحت کا انٹرفیس دکھائیں:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

یا بلیڈ ٹیمپلیٹس میں پروگرامنگ کے ذریعے جانچیں:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        Warning: Database schema drift detected!
    </div>
@endif
```

---

## ⚠️ پرانے ورژنز سے مطابقت (Legacy Compatibility)

یہ ورژن صرف **Laravel 13.x** اور **PHP 8.4+** کے لیے ہے۔ اگر آپ **Laravel 11.x یا 12.x** استعمال کر رہے ہیں تو مستحکم **v1.6.0** ورژن استعمال کریں:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| Laravel ورژن | معاون PHP ورژنز | پیکج ورژن |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 🛡️ مطابقت اور ٹیسٹ کی صورتحال (Compatibility & Test Status)

- **Laravel مطابقت**: **صرف Laravel 13.x** (Laravel 10/11/12 کے لیے کوئی اضافی شمس موجود نہیں)۔
- **کم از کم PHP ضرورت**: **PHP 8.4+** (PHP 8.4 اور PHP 8.5 پر مکمل ٹیسٹ شدہ)۔
- **تصدیق شدہ ڈیٹا بیس انجن**:
  - **SQLite**: 3.49+ (ان میموری اور فائل بیسڈ)۔
  - **PostgreSQL**: 16+ / 18+ (مخصوص شیڈو کنکشن کے ساتھ ٹیسٹ شدہ)۔
  - **MySQL / MariaDB**: 8.0+ / 13.0+ (CI کنٹینرز میں تصدیق شدہ)۔
- **ٹیسٹ رپورٹس اور شواہد**:
  - [Test Matrix](docs/testing/TEST_MATRIX.md)
  - [Real Application Test Results](docs/testing/TEST_RESULTS.md)
  - [Known Limitations & Dialect Behavior](docs/testing/KNOWN_LIMITATIONS.md)
  - [Performance Benchmarks](docs/testing/PERFORMANCE.md)

---

## 📄 تبدیلیوں کی تفصیل (Changelogs)

### ورژن: v2.1.0 (Release: v2.1.0)

*   **شیڈو کنکشن کا تحفظ**: `ShadowMigrationRunner` کو لائیو ڈیٹا بیس سے مکمل طور پر محفوظ اور الگ تھلگ بنایا گیا۔
*   **پری مائیگریشن گارڈ**: رن ٹائم `exit(1)` کو ختم کر کے محفوظ ایکسیپشن `MigrationBlockedException` میں بدلا گیا۔
*   **ڈرفٹ انجن کا معیار**: ڈیٹا بیس انجنز کے مابین اقسام (`varchar`، `bigint`، `boolean`)، مرکب انڈیکس اور فارن کیز کی غلط فہمیاں دور کی گئیں۔
*   **مائیگریشن جنریٹر**: جنریٹڈ کوڈ میں مکمل پرائمری کیز، انڈیکس اور فارن کیز شامل کی گئیں اور Laravel 13 کے لیے `--realpath` سپورٹ دی گئی۔
*   **ڈیٹا کا تحفظ**: سیڈرز سے لائیو ریکارڈز کا اخراج روکا گیا اور تمام بیرونی ٹیلی میٹری ختم کی گئی۔
*   **Laravel 13 تصدیق**: حقیقی Laravel 13 ایپلی کیشن پر ٹیسٹ اور خودکار GitHub Actions میٹرکس ترتیب دیا گیا۔

ماضی کے ورژنز کی تفصیلات: [changelogs/v2.0.0.md](changelogs/v2.0.0.md)، [changelogs/v1.6.0.md](changelogs/v1.6.0.md) اور [changelogs/v1.0.0.md](changelogs/v1.0.0.md)۔

---

## 🤝 کریڈٹس (Credits)

- **مصنف**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **ادارہ**: [Broadway Web Service](https://www.clcbws.com)
- **لائسنس**: [MIT License](LICENSE)
