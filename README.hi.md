# Laravel Schema Sentinel

[![Latest Stable Version](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![GitHub Stars](https://img.shields.io/github/stars/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![CI Tests](https://img.shields.io/github/actions/workflow/status/ahtesham-clcbws/laravel-schema-sentinel/ci.yml?branch=development&style=flat-square&label=tests)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Read this in:** [English](README.md) | [Roman Hindustani](README.hi-Latn.md) | **हिन्दी** | [اردو](README.ur.md) | [العربية](README.ar.md) | [Español](README.es.md) | [Português (Brasil)](README.pt-BR.md)

> [!NOTE]
> यह दस्तावेज़ डेवलपर्स की सुविधा के लिए हिन्दी में अनुवादित है। आधिकारिक और प्राथमिक दस्तावेज़ [English README](README.md) है।

**Laravel Schema Sentinel** एक उन्नत डेटाबेस स्कीमा अखंडता (integrity) और नियंत्रण सुइट है। यह आपकी माइग्रेशन फ़ाइलों और वास्तविक डेटाबेस के बीच होने वाले "Schema Drift" (संरचनात्मक विसंगतियों) का पता लगाकर उन्हें ठीक करता है। इसके अलावा, यह इंडेक्स कॉन्फ़िगरेशन को मानकीकृत करने, डेटा अखंडता का ऑडिट करने, माइग्रेशन लिंट करने और लीगेसी डेटाबेस को रिवर्स-इंजीनियर करने के स्वचालित उपकरण प्रदान करता है।

---

## 📖 विषय-सूची (Table of Contents)

- [शुरुआत (Getting Started)](#-शुरुआत-getting-started)
  - [परिचय (Introduction)](#परिचय-introduction)
  - [प्रमुख विशेषताएँ (Key Features)](#प्रमुख-विशेषताएँ-key-features)
  - [इन्स्टॉलेशन (Installation)](#इन्स्टॉलेशन-installation)
  - [कॉन्फ़िगरेशन (Configuration)](#कॉन्फ़िगरेशन-configuration)
- [Artisan कमांड्स (Artisan Commands)](#-artisan-कमांड्स-artisan-commands)
  - [स्कीमा ड्रिफ्ट ऑडिट (`schema:drift`)](#स्कीमा-ड्रिफ्ट-ऑडिट-schemadrift)
  - [इंडेक्स मानकीकरण (`schema:standardize-indexes`)](#इंडेक्स-मानकीकरण-schemastandardize-indexes)
  - [डेटा ड्रिफ्ट ऑडिट (`schema:data-drift`)](#डेटा-ड्रिफ्ट-ऑडिट-schemadata-drift)
  - [लीगेसी डेटाबेस रिवर्सिंग (`schema:reverse`)](#लीगेसी-डेटाबेस-रिवर्सिंग-schemareverse)
  - [माइग्रेशन फ़ाइल लिंटर (`schema:sentinel-lint`)](#माइग्रेशन-फ़ाइल-लिंटर-schemasentinel-lint)
  - [पर्यावरण विश्लेषक (`schema:sentinel-doctor`)](#पर्यावरण-विश्लेषक-schemasentinel-doctor)
- [प्रोग्रामेटिक API (Programmatic API)](#-प्रोग्रामेटिक-api-programmatic-api)
  - [ड्रिफ्ट ऑडिटिंग](#ड्रिफ्ट-ऑडिटिंग)
  - [स्कीमा DTOs पार्सिंग](#स्कीमा-dtos-पार्सिंग)
  - [इंडेक्स मानकीकरण](#इंडेक्स-मानकीकरण)
  - [डेटा ड्रिफ्ट ऑडिट](#डेटा-ड्रिफ्ट-ऑडिट)
  - [प्रोग्रामेटिक रिवर्सिंग](#प्रोग्रामेटिक-रिवर्सिंग)
  - [Livewire कंपोनेंट और Blade UI](#livewire-कंपोनेंट-और-blade-ui)
- [पुरातन संगतता (Legacy Compatibility)](#-पुरातन-संगतता-legacy-compatibility)
- [संगतता और परीक्षण स्थिति (Compatibility & Test Status)](#-संगतता-और-परीक्षण-स्थिति-compatibility--test-status)
- [परिवर्तन विवरण (Changelogs)](#-परिवर्तन-विवरण-changelogs)

---

## 🚀 शुरुआत (Getting Started)

### परिचय (Introduction)

उत्पादन वातावरण (production) में हॉटफिक्स, सीधे डेटाबेस संपादन या अधूरे रोलबैक के कारण डेटाबेस स्कीमा और माइग्रेशन फाइलों में अंतर आ जाता है। Schema Sentinel एक पृथक शैडो डेटाबेस (shadow database) में माइग्रेशन चलाकर "आदर्श" स्कीमा तैयार करता है, उसका लाइव कनेक्शन से मिलान करता है और स्वचालित समाधान माइग्रेशन उत्पन्न करता है।

### प्रमुख विशेषताएँ (Key Features)

*   🛡️ **गहन ड्रिफ्ट पहचान**: टेबल्स, कॉलम्स, डेटा टाइप्स, नलेबिलिटी, डिफ़ॉल्ट्स, इंडेक्स और फॉरेन कीज़ का पूर्ण विश्लेषण।
*   ⚡ **प्रोग्रामेटिक फ़साड**: कस्टम डैशबोर्ड निर्माण हेतु संपूर्ण API समर्थन।
*   📐 **इंडेक्स मानकीकरण**: Laravel सम्मेलनों के अनुसार इंडेक्स नामों और अनावश्यक डुप्लिकेट्स की पहचान।
*   🐢 **धीमी माइग्रेशन ऑडिट**: सिमुलेशन के दौरान धीमे माइग्रेशन की पहचान।
*   🔄 **रोलबैक सत्यापन**: रोलबैक चलाकर यह सुनिश्चित करना कि स्कीमा सुरक्षित रूप से रीवर्ट हो सकता है।
*   🌍 **क्रॉस-एनवायरनमेंट तुलना**: स्थानीय स्कीमा की तुलना स्टेजिंग या प्रोडक्शन कनेक्शन से करना।
*   🤖 **CI/CD सक्षम**: पाइपलाइन स्वचालित सत्यापन के लिए मानक शेल निकास कोड (exit codes)।

### इन्स्टॉलेशन (Installation)

Composer के माध्यम से पैकेज स्थापित करें:

```bash
composer require clcbws/laravel-schema-sentinel
```

सर्विस प्रोवाइडर और फ़साड स्वचालित रूप से पंजीकृत हो जाएंगे।

### कॉन्फ़िगरेशन (Configuration)

कॉन्फ़िगरेशन फ़ाइल प्रकाशित करें:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

यह `config/schema-sentinel.php` फ़ाइल उत्पन्न करता है जिसमें निम्न शामिल हैं:
- **`ignore_tables`**: विश्लेषण से बाहर रखी जाने वाली टेबल्स (जैसे `migrations`, `telescope_entries`)।
- **`migration_paths`**: माइग्रेशन फ़ाइलों के पथ (मॉड्यूलर ऍप्लिकेशन्स हेतु)।
- **`shadow_connection`**: अस्थायी शैडो डेटाबेस सेटिंग्स (डिफ़ॉल्ट रूप से इन-मेमोरी SQLite)।
- **`skip_migrations`**: सिमुलेशन में छोड़ी जाने वाली माइग्रेशन फ़ाइलें।
- **`notifications`**: ड्रिफ्ट अलर्ट हेतु Slack एवं Discord वेबहुक।

---

## 💻 Artisan कमांड्स (Artisan Commands)

### स्कीमा ड्रिफ्ट ऑडिट (`schema:drift`)

डेटाबेस स्वास्थ्य जांच हेतु मुख्य कमांड चलाएं:

```bash
php artisan schema:drift
```

#### विकल्प (Options):

*   **`--fix`**: डेटाबेस को संरेखित करने के लिए नया माइग्रेशन उत्पन्न करें।
*   **`--interactive`**: प्रत्येक परिवर्तन की चरण-दर-चरण पुष्टि करें।
*   **`--sql`**: ड्राई-रन मोड में SQL स्टेटमेंट देखें।
*   **`--strict`**: लाइव DB में मौजूद अतिरिक्त टेबल्स और कॉलम्स की रिपोर्ट करें।
*   **`--rollback`**: शैडो DB पर रोलबैक का परीक्षण करें।
*   **`--snapshot[=latest]`**: त्वरित ऑडिट हेतु सुरक्षित JSON स्नैपशॉट का उपयोग करें।
*   **`--tag=name`**: केवल `@sentinel-tag name` चिह्नित माइग्रेशन का ऑडिट करें।

---

### इंडेक्स मानकीकरण (`schema:standardize-indexes`)

गैर-मानक और डुप्लिकेट इंडेक्स की जांच करें:

```bash
php artisan schema:standardize-indexes
```

#### विकल्प:
*   **`--fix`**: विचलित इंडेक्स को रीनेम करने और डुप्लिकेट हटाने हेतु माइग्रेशन तैयार करें।

---

### डेटा ड्रिफ्ट ऑडिट (`schema:data-drift`)

विभिन्न वातावरणों के बीच स्थिर संदर्भ डेटा का मिलान करें:

```bash
php artisan schema:data-drift --compare-env=production
```

टर्मिनल तालिका में अनुपस्थित, अतिरिक्त या बेमेल पंक्तियों का विवरण प्रदर्शित होता है।

---

### लीगेसी डेटाबेस रिवर्सिंग (`schema:reverse`)

विद्यमान डेटाबेस को स्वच्छ Laravel कोडबेस में रूपांतरित करें:

```bash
php artisan schema:reverse --path=./exports
```

#### विकल्प:
*   **`--models`**: Eloquent Model क्लास उत्पन्न करें (टाइप्ड संबंधों एवं डॉकब्लॉक सहित)।
*   **`--migrations`**: स्कीमा ब्लूप्रिंट माइग्रेशन बनाएं।
*   **`--seeders`**: सुरक्षित डेटाबेस सीडर फ़ाइलें तैयार करें।
*   **`--enums`**: PHP 8.4 Backed Enums उत्पन्न करें।

---

### माइग्रेशन फ़ाइल लिंटर (`schema:sentinel-lint`)

ड्रिफ्ट उत्पन्न करने वाले कोड पैटर्न्स की जांच करें:

```bash
php artisan schema:sentinel-lint
```

यह जांचता है:
1. सीधे `DB::statement` कॉल।
2. हार्डकोडेड डिफ़ॉल्ट तिथियां (जैसे `'CURRENT_TIMESTAMP'`)।
3. असुरक्षित `Schema::drop` कॉल्स।

---

### पर्यावरण विश्लेषक (`schema:sentinel-doctor`)

पर्यावरण कॉन्फ़िगरेशन की जांच करें:

```bash
php artisan schema:sentinel-doctor
```

---

### सहायता एवं वर्तनी परीक्षक (`schema:help`)

विस्तृत कमांड सहायता देखें। टाइपिंग त्रुटि होने पर अंतर्निहित Levenshtein इंजन सही कमांड सुझाएगा:

```bash
php artisan schema:help drft
```

---

## 🔌 प्रोग्रामेटिक API (Programmatic API)

नियंत्रकों (controllers), डैशबोर्ड या परिनियोजन स्क्रिप्ट में `Sentinel` फ़साड का उपयोग करें:

### ड्रिफ्ट ऑडिटिंग

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // स्कीमा में अंतर पाया गया
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### स्कीमा DTOs पार्सिंग

डेटाबेस संरचना को संरचित मेटाडेटा में प्राप्त करें:

```php
$tables = Sentinel::parse(); // TableDefinition DTOs की सरणी लौटाता है
```

### इंडेक्स मानकीकरण

प्रोग्रामेटिक रूप से इंडेक्स का विश्लेषण करें:

```php
$results = Sentinel::standardizeIndexes();
// 'deviations' और 'redundant' इंडेक्स लौटाता है
```

### डेटा ड्रिफ्ट ऑडिट

लक्षित कनेक्शन के विरुद्ध डेटा का मिलान करें:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### प्रोग्रामेटिक रिवर्सिंग

कोड के माध्यम से रिवर्स इंजीनियरिंग आरंभ करें:

```php
$results = Sentinel::reverse([
    'path' => base_path('exports'),
    'models' => true,
    'migrations' => true,
    'seeders' => false,
    'enums' => true,
]);
```

### Livewire कंपोनेंट और Blade UI

व्यवस्थापक टेम्प्लेट में डेटाबेस स्वास्थ्य डैशबोर्ड प्रदर्शित करें:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

या Blade लेआउट में प्रोग्रामेटिक रूप से जांचें:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        Warning: Database schema drift detected!
    </div>
@endif
```

---

## ⚠️ पुरातन संगतता (Legacy Compatibility)

यह संस्करण **Laravel 13.x** और **PHP 8.4+** हेतु समर्पित है। **Laravel 11.x या 12.x** (PHP 8.2 अथवा 8.3) का उपयोग करने वाले डेवलपर्स हमारे स्थिर **v1.6.0** संस्करण का उपयोग कर सकते हैं:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| Laravel संस्करण | समर्थित PHP संस्करण | पैकेज संस्करण |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 🛡️ संगतता और परीक्षण स्थिति (Compatibility & Test Status)

- **Laravel संगतता**: **केवल Laravel 13.x** (Laravel 10/11/12 के लिए कोई बैकवर्ड शिम्स नहीं)।
- **न्यूनतम PHP आवश्यकता**: **PHP 8.4+** (PHP 8.4 और PHP 8.5 पर पूर्णतः सत्यापित)।
- **सत्यापित डेटाबेस इंजन**:
  - **SQLite**: 3.49+ (इन-मेमोरी एवं फ़ाइल आधारित)।
  - **PostgreSQL**: 16+ / 18+ (समर्पित शैडो कनेक्शन सहित परीक्षित)।
  - **MySQL / MariaDB**: 8.0+ / 13.0+ (CI कंटेनर मैट्रिक्स में सत्यापित)।
- **प्रमाणित परीक्षण रिपोर्ट**:
  - [Test Matrix](docs/testing/TEST_MATRIX.md)
  - [Real Application Test Results](docs/testing/TEST_RESULTS.md)
  - [Known Limitations & Dialect Behavior](docs/testing/KNOWN_LIMITATIONS.md)
  - [Performance Benchmarks](docs/testing/PERFORMANCE.md)

---

## 📄 परिवर्तन विवरण (Changelogs)

### नवीनतम संस्करण: v2.1.0 (Stability Patch)

*   **शैडो माइग्रेशन पृथक्करण**: `ShadowMigrationRunner` को वैश्विक कनेक्शन म्यूटेशन से मुक्त किया गया।
*   **प्री-माइग्रेशन गार्ड**: रनटाइम `exit(1)` को `MigrationBlockedException` से प्रतिस्थापित कर सुरक्षित बाईपास जोड़ा गया।
*   **डिफ़ इंजन सामान्यीकरण**: डेटाबेस इंजन के बीच प्रकारों (`varchar`, `bigint`, `boolean`), समग्र इंडेक्स और फ़ॉरेन की क्रियाओं के मिथ्या सकारात्मक परिणाम ठीक किए गए।
*   **माइग्रेशन जनरेटर**: जनरेटेड टेबल संरचना में प्राइमरी कीज़, इंडेक्स और फ़ॉरेन कीज़ जोड़े गए तथा Laravel 13 के लिए `--realpath` समर्थन शामिल किया गया।
*   **डेटा सुरक्षा**: लाइव डेटाबेस रिकॉर्ड्स का एक्सपोर्ट रोका गया तथा बाह्य टेलीमेट्री को पूर्णतः समाप्त किया गया।
*   **Laravel 13 सत्यापन**: वास्तविक Laravel 13 ऐप्लिकेशन में परीक्षण और GitHub Actions वर्कफ़्लो स्थापित।

विगत संस्करण विवरण: [changelogs/v2.0.0.md](changelogs/v2.0.0.md), [changelogs/v1.6.0.md](changelogs/v1.6.0.md) और [changelogs/v1.0.0.md](changelogs/v1.0.0.md)।

---

## 🤝 आभार (Credits)

- **लेखक**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **कंपनी**: [Broadway Web Service](https://www.clcbws.com)
- **लाइसेंस**: [MIT License](LICENSE)
