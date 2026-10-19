# Laravel Schema Sentinel

[![Latest Stable Version](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![GitHub Stars](https://img.shields.io/github/stars/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![CI Tests](https://img.shields.io/github/actions/workflow/status/ahtesham-clcbws/laravel-schema-sentinel/ci.yml?branch=development&style=flat-square&label=tests)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**اقرأ هذا بـ:** [English](README.md) | [Roman Hindustani](README.hi-Latn.md) | [हिन्दी](README.hi.md) | [اردو](README.ur.md) | **العربية** | [Español](README.es.md) | [Português (Brasil)](README.pt-BR.md)

> [!NOTE]
> النسخة الإنجليزية ([English README](README.md)) هي المصدر المرجعي والمعتمد لحزمة Laravel Schema Sentinel. يتم الحفاظ على تزامن جميع الترجمات مع الوثيقة الإنجليزية.

**Laravel Schema Sentinel** هي حزمة متقدمة لمراقبة سلامة بنية قواعد البيانات وحوكمتها في إطار عمل Laravel. تقوم باكتشاف ومعالجة "انحراف المخطط" (Schema Drift - التباينات بين ملفات ترحيل المخططات وقاعدة البيانات الفعلية)، وتوفر أدوات مؤتمتة لتوحيد تسمية الفهارس، وتدقيق تطابق البيانات، وفحص جودة ملفات الترحيل (Linting)، والهندسة العكسية للجداول القديمة.

---

## 📖 جدول المحتويات

- [البداية السريعة](#-البداية-السريعة)
  - [مقدمة](#مقدمة)
  - [الميزات الرئيسية](#الميزات-الرئيسية)
  - [التثبيت](#التثبيت)
  - [الإعدادات والتكوين](#الإعدادات-والتكوين)
- [أوامر أرتيزان (Artisan Commands)](#-أوامر-أرتيزان-artisan-commands)
  - [تدقيق انحراف المخطط (`schema:drift`)](#تدقيق-انحراف-المخطط-schemadrift)
  - [توحيد تسمية الفهارس (`schema:standardize-indexes`)](#توحيد-تسمية-الفهارس-schemastandardize-indexes)
  - [تدقيق انحراف البيانات (`schema:data-drift`)](#تدقيق-انحراف-البيانات-schemadata-drift)
  - [الهندسة العكسية لقواعد البيانات القديمة (`schema:reverse`)](#الهندسة-العكسية-لقواعد-البيانات-القديمة-schemareverse)
  - [فاحص جودة ملفات الترحيل (`schema:sentinel-lint`)](#فاحص-جودة-ملفات-الترحيل-schemasentinel-lint)
  - [طبيب بيئة التشغيل (`schema:sentinel-doctor`)](#طبيب-بيئة-التشغيل-schemasentinel-doctor)
  - [دليل المساعدة واقتراح الأوامر (`schema:help`)](#دليل-المساعدة-واقتراح-الأوامر-schemahelp)
- [واجهة البرمجة التطبيقية (Programmatic API)](#-واجهة-البرمجة-التطبيقية-programmatic-api)
  - [تدقيق الانحراف برمجياً](#تدقيق-الانحراف-برمجياً)
  - [استخراج كائنات المخطط (Schema DTOs)](#استخراج-كائنات-المخطط-schema-dtos)
  - [توحيد الفهارس برمجياً](#توحيد-الفهارس-برمجياً)
  - [تدقيق انحراف البيانات](#تدقيق-انحراف-البيانات)
  - [الهندسة العكسية البرمجية](#الهندسة-العكسية-البرمجية)
  - [مكون Livewire وواجهة Blade](#مكون-livewire-وواجهة-blade)
- [التوافق مع الإصدارات السابقة](#-التوافق-مع-الإصدارات-السابقة)
- [حالة التوافق والاختبارات](#-حالة-التوافق-والاختبارات)
- [سجل التغييرات](#-سجل-التغييرات)
- [فريق العمل والترخيص](#-فريق-العمل-والترخيص)

---

## 🚀 البداية السريعة

### مقدمة

في النشر الحديث للتطبيقات، غالباً ما تنحرف قواعد البيانات عن ملفات الترحيل بسبب التعديلات الطارئة المباشرة، أو استيراد بيانات قديمة، أو عمليات التراجع الفاشلة. تقدم Schema Sentinel محرك محاكاة معزول بالكامل يقوم بتشغيل الترحيلات على قاعدة بيانات خفية (Shadow Database) لبناء المخطط "المثالي"، ومطابقته بدقة مع قاعدة البيانات الحية، وتوليد ترحيلات الإصلاح تلقائياً.

### الميزات الرئيسية

*   🛡️ **اكتشاف عميق للانحراف**: فحص الجداول، الأعمدة، الأنواع، قابلية القيم الفارغة (Nullability)، القيم الافتراضية، الفهارس، والمفاتيح الأجنبية.
*   ⚡ **واجهة برمجية عبر الفاساد (Facade)**: تغطية برمجية شاملة لبناء لوحات تحكم وأنظمة مراقبة مخصصة.
*   📐 **توحيد معايير تسمية الفهارس**: التحقق التلقائي من توافق أسماء الفهارس مع اصطلاحات Laravel القياسية وإزالة المكرر منها.
*   🐢 **تدقيق الترحيلات البطيئة**: تحديد ملفات الترحيل التي تستهلك وقتاً طويلاً أثناء المحاكاة.
*   🔄 **تدقيق التراجع (Rollback Audit)**: محاكاة التراجع عن الترحيلات للتأكد من إمكانية الرجوع النظيف للمخطط دون أخطاء.
*   🌍 **المزامنة عبر البيئات المختلفة**: مقارنة المخططات بين بيئات العمل المختلفة (Staging / Production).
*   🤖 **جاهز لخطوط أنابيب CI/CD**: إرجاع رموز خروج معيارية (Exit codes) لأتمتة الاختبارات والتكامل المستمر.

### التثبيت

ثبّت الحزمة عبر Composer:

```bash
composer require clcbws/laravel-schema-sentinel
```

سيتم تسجيل موفر الخدمة (Service Provider) والواجهة (Facade) تلقائياً.

### الإعدادات والتكوين

انشر ملف الإعدادات:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

سينشئ هذا الملف `config/schema-sentinel.php` والذي يتيح لك ضبط:
- **`ignore_tables`**: مصفوفة بأسماء الجداول المستثناة من الفحص (مثل `migrations` أو `telescope_entries`).
- **`migration_paths`**: مسارات ملفات الترحيل الإضافية (مفيد للتطبيقات متعددة الوحدات/Modular).
- **`shadow_connection`**: إعدادات قاعدة البيانات الخفية المؤقتة (الافتراضي SQLite in-memory معزولة).
- **`skip_migrations`**: قائمة بملفات ترحيل معينة يتم تجاهلها أثناء المحاكاة.
- **`notifications`**: روابط Webhooks لـ Slack و Discord لتلقي تنبيهات الانحراف.

---

## 💻 أوامر أرتيزان (Artisan Commands)

### تدقيق انحراف المخطط (`schema:drift`)

شغّل تدقيق المخطط الأساسي لفحص صحة بنية قاعدة البيانات:

```bash
php artisan schema:drift
```

#### الخيارات:

*   **`--fix`**: توليد ملف ترحيل Laravel جديد لإصلاح الفروقات تلقائياً.
*   **`--interactive`**: مراجعة التغييرات والموافقة على تعديل كل عمود خطوة بخطوة.
*   **`--sql`**: معاينة استعلامات SQL الناتجة دون تطبيقها (وضع التجربة الجافة).
*   **`--strict`**: الإبلاغ عن الجداول والأعمدة الزائدة الموجودة في قاعدة البيانات الحية وغير المعرفة في ملفات الترحيل.
*   **`--rollback`**: تشغيل الترحيلات ثم التراجع عنها في قاعدة البيانات الخفية لاختبار دقة دالة `down()`.
*   **`--snapshot[=latest]`**: استخدام لقطة JSON سريعة لتسريع الفحص دون محاكاة كاملة في كل مرة.
*   **`--tag=name`**: حصر التدقيق في ملفات الترحيل التي تحمل الوسم `@sentinel-tag name`.

---

### توحيد تسمية الفهارس (`schema:standardize-indexes`)

فحص قاعدة البيانات للكشف عن الفهارس المخالفة للتسميات القياسية والفهارس المكررة الفائضة:

```bash
php artisan schema:standardize-indexes
```

#### الخيارات:
*   **`--fix`**: توليد ملف ترحيل لإعادة تسمية الفهارس المخالفة وحذف الفهارس المكررة تلقائياً.

---

### تدقيق انحراف البيانات (`schema:data-drift`)

تدقيق جداول البحث الثابتة (Lookup Tables) أو بيانات البذر للتحقق من تطابق السجلات بين البيئات:

```bash
php artisan schema:data-drift --compare-env=production
```

يعرض جدول مقارنة داخل الطرفية يوضح الصفوف المفقودة، أو الزائدة، أو غير المتطابقة.

---

### الهندسة العكسية لقواعد البيانات القديمة (`schema:reverse`)

تحويل قاعدة بيانات قديمة وموجودة إلى بنية Laravel احترافية ونظيفة:

```bash
php artisan schema:reverse --path=./exports
```

#### الخيارات:
*   **`--models`**: توليد نماذج Eloquent مع العلاقات المحددة بالأنواع (`belongsTo`, `hasMany`) والتوثيق البرمجي.
*   **`--migrations`**: توليد ملفات ترحيل المخططات (Blueprint).
*   **`--seeders`**: توليد ملفات بذر (Seeders) معبأة بعينات من بيانات الجداول الحقيقية.
*   **`--enums`**: توليد تعدادات PHP 8.4 Backed Enums وربطها بنماذج البيانات.

---

### فاحص جودة ملفات الترحيل (`schema:sentinel-lint`)

فحص ملفات الترحيل في مشروعك بحثاً عن الأنماط الخاطئة التي تؤدي إلى انحراف المخطط:

```bash
php artisan schema:sentinel-lint
```

يفحص الأخطاء التالية:
1. استدعاءات `DB::statement` المباشرة دون حماية.
2. استخدام نصوص التوقيت الافتراضية الخاصة بمحرك معين (مثل `'CURRENT_TIMESTAMP'`).
3. استخدام دوال الحذف غير الآمنة `Schema::drop` دون التأكد من الوجود عبر `IfExists`.

---

### طبيب بيئة التشغيل (`schema:sentinel-doctor`)

التحقق من صحة إعدادات بيئة التشغيل لديك (إصدار PHP، محركات PDO، وإعدادات الاتصال الخفي):

```bash
php artisan schema:sentinel-doctor
```

---

### دليل المساعدة واقتراح الأوامر (`schema:help`)

الحصول على دليل مفصل للأوامر وخياراتها. إذا أخطأت في كتابة الأمر، سيقوم محرك الاقتراحات المدمج باقتراح الأمر الصحيح تلقائياً:

```bash
php artisan schema:help drft
```

---

## 🔌 واجهة البرمجة التطبيقية (Programmatic API)

يمكنك دمج الحزمة برمجياً في المتحكمات (Controllers) أو لوحات التحكم أو خطافات النشر باستخدام الفاساد `Sentinel`.

### تدقيق الانحراف برمجياً

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // تم اكتشاف انحراف في المخطط
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### استخراج كائنات المخطط (Schema DTOs)

استخراج بيانات هيكلية منظمة تمثل تخطيط قاعدة البيانات:

```php
$tables = Sentinel::parse(); // يُرجع مصفوفة من كائنات TableDefinition DTOs
```

### توحيد الفهارس برمجياً

تشغيل محلل الفهارس برمجياً:

```php
$results = Sentinel::standardizeIndexes();
// يُرجع مصفوفة تحتوي على الفهارس المنحرفة 'deviations' والمكررة 'redundant'
```

### تدقيق انحراف البيانات

تدقيق تطابق البيانات مع اتصال محدد:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### الهندسة العكسية البرمجية

تشغيل الهندسة العكسية من خلال الكود:

```php
$results = Sentinel::reverse([
    'path' => base_path('exports'),
    'models' => true,
    'migrations' => true,
    'seeders' => false,
    'enums' => true,
]);
```

### مكون Livewire وواجهة Blade

عرض مؤشرات سلامة قاعدة البيانات داخل قوالب لوحة التحكم:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

أو التحقق برمجياً في قوالب Blade:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        تحذير: تم اكتشاف انحراف في بنية قاعدة البيانات!
    </div>
@endif
```

---

## ⚠️ التوافق مع الإصدارات السابقة

بينما يتطلب الإصدار الحالي **Laravel 13.x** و **PHP 8.4+**، يمكن للمطورين الذين يستخدمون **Laravel 11.x أو 12.x** (مع PHP 8.2 أو 8.3) استخدام الإصدار المستقر **v1.6.0**:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| إصدار Laravel | إصدارات PHP المدعومة | إصدار الحزمة |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 🛡️ حالة التوافق والاختبارات

- **التوافق مع Laravel**: **Laravel 13.x فقط** (بدون طبقات توافق قديمة لـ Laravel 10/11/12).
- **الحد الأدنى لإصدار PHP**: **PHP 8.4+** (مختبر بالكامل على PHP 8.4 و PHP 8.5).
- **محركات قواعد البيانات المؤكدة**:
  - **SQLite**: 3.49+ (في الذاكرة وعلى القرص).
  - **PostgreSQL**: 16+ / 18+ (مختبر باستخدام اتصال خفي مخصص).
  - **MySQL / MariaDB**: 8.0+ / 13.0+ (مختبر عبر مصفوفة حاويات CI متعددة الخدمات).
- **تقارير وأدلة الاختبارات الفعلية**:
  - [مصفوفة الاختبارات (Test Matrix)](docs/testing/TEST_MATRIX.md)
  - [نتائج اختبار التطبيقات الحقيقية (Real Application Test Results)](docs/testing/TEST_RESULTS.md)
  - [القيود وسلوكيات المحركات المختلفة (Known Limitations)](docs/testing/KNOWN_LIMITATIONS.md)
  - [اختبارات الأداء القياسية (Performance Benchmarks)](docs/testing/PERFORMANCE.md)

---

## 📄 سجل التغييرات

### الإصدار: v2.1.0 (Release: v2.1.0)

*   **عزل ترحيلات قاعدة البيانات الخفية**: تحصين `ShadowMigrationRunner` لمنع تعديل إعدادات الاتصال العام، مع إضافة فحوصات تطابق صارمة لمنع التشغيل الخاطئ على قواعد البيانات الحية.
*   **حارس ما قبل الترحيل (Pre-Migration Guard)**: استبدال استدعاء `exit(1)` المباشر برمي استثناء `MigrationBlockedException`، وتوفير تجاوز داخلي حتمي لتفادي التكرار وانهيار بيئة الاختبارات.
*   **محرك الفروقات وتوحيد الأنواع**: القضاء على النتائج الإيجابية الخاطئة عبر محركات قواعد البيانات المختلفة للأنواع (`varchar`, `bigint`, `boolean`)، والفهارس المركبة، وإجراءات المفاتيح الأجنبية.
*   **مولد ملفات الترحيل**: إصلاح نقص تعريفات الفهارس والمفاتيح الأجنبية أثناء إنشاء الجداول، ودعم خيار `--realpath` لـ Laravel 13، وإضافة حماية لضمان استقرار العمليات المتكررة (Idempotency).
*   **أمان البيانات**: إزالة تصدير بيانات الجداول الحية إلى ملفات البذر، وإزالة توليد التعدادات الوهمية، وإزالة كود إرسال القياسات الخارجية عبر الشبكة.
*   **التحقق في بيئة Laravel 13**: اختبار الحزمة في تطبيق Laravel 13 فعلي جديد وإعداد مصفوفة اختبارات مؤتمتة عبر GitHub Actions.

للاطلاع على الإصدارات السابقة، راجع [changelogs/v2.0.0.md](changelogs/v2.0.0.md)، [changelogs/v1.6.0.md](changelogs/v1.6.0.md)، و [changelogs/v1.0.0.md](changelogs/v1.0.0.md).

---

## 🤝 فريق العمل والترخيص

- **المؤلف**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **الشركة**: [Broadway Web Service](https://www.clcbws.com)
- **الترخيص**: [MIT License](LICENSE)
