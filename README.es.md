# Laravel Schema Sentinel

[![Latest Stable Version](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![GitHub Stars](https://img.shields.io/github/stars/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![CI Tests](https://img.shields.io/github/actions/workflow/status/ahtesham-clcbws/laravel-schema-sentinel/ci.yml?branch=development&style=flat-square&label=tests)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Leer en:** [English](README.md) | [Roman Hindustani](README.hi-Latn.md) | [हिन्दी](README.hi.md) | [اردو](README.ur.md) | [العربية](README.ar.md) | **Español** | [Português (Brasil)](README.pt-BR.md)

> [!NOTE]
> Este documento en inglés ([English README](README.md)) es la fuente canónica de información para Laravel Schema Sentinel. Todas las traducciones se mantienen sincronizadas con dicho documento.

**Laravel Schema Sentinel** es una suite avanzada para la integridad y gobernanza del esquema de bases de datos en Laravel. Detecta y resuelve el desfase de esquema ("Schema Drift" — discrepancias entre las migraciones y el estado real de la base de datos) y proporciona herramientas automatizadas para estandarizar la nomenclatura de índices, auditar la sincronización de datos, analizar archivos de migración (linting) y aplicar ingeniería inversa a bases de datos heredadas.

---

## 📖 Tabla de Contenidos

- [Primeros Pasos](#-primeros-pasos)
  - [Introducción](#introducción)
  - [Características Principales](#características-principales)
  - [Instalación](#instalación)
  - [Configuración](#configuración)
- [Comandos de Artisan](#-comandos-de-artisan)
  - [Auditoría de Desfase de Esquema (`schema:drift`)](#auditoría-de-desfase-de-esquema-schemadrift)
  - [Estandarización de Índices (`schema:standardize-indexes`)](#estandarización-de-índices-schemastandardize-indexes)
  - [Auditoría Profunda de Datos (`schema:data-drift`)](#auditoría-profunda-de-datos-schemadata-drift)
  - [Ingeniería Inversa de Bases de Datos Heredadas (`schema:reverse`)](#ingeniería-inversa-de-bases-de-datos-heredadas-schemareverse)
  - [Linter de Archivos de Migración (`schema:sentinel-lint`)](#linter-de-archivos-de-migración-schemasentinel-lint)
  - [Diagnóstico del Entorno (`schema:sentinel-doctor`)](#diagnóstico-del-entorno-schemasentinel-doctor)
  - [Guías de Ayuda y Corrección Tipográfica (`schema:help`)](#guías-de-ayuda-y-corrección-tipográfica-schemahelp)
- [API Programática](#-api-programática)
  - [Auditoría de Desfase](#auditoría-de-desfase)
  - [Extracción de DTOs del Esquema](#extracción-de-dtos-del-esquema)
  - [Estandarización de Índices](#estandarización-de-índices)
  - [Auditorías de Desfase de Datos](#auditorías-de-desfase-de-datos)
  - [Ingeniería Inversa Programática](#ingeniería-inversa-programática)
  - [Componente Livewire y Vistas Blade](#componente-livewire-y-vistas-blade)
- [Compatibilidad con Versiones Anteriores](#-compatibilidad-con-versiones-anteriores)
- [Estado de Compatibilidad y Pruebas](#-estado-de-compatibilidad-y-pruebas)
- [Registro de Cambios](#-registro-de-cambios)
- [Créditos y Licencia](#-créditos-y-licencia)

---

## 🚀 Primeros Pasos

### Introducción

En el despliegue moderno de aplicaciones, el esquema de la base de datos puede desfasarse fácilmente respecto a las migraciones debido a hotfixes directos, importaciones manuales o rollbacks fallidos. Schema Sentinel proporciona un motor de simulación completamente aislado que ejecuta las migraciones en una base de datos espejo (shadow database) para determinar el esquema "ideal", compararlo contra tu base de datos activa y generar las migraciones correctivas de forma automática.

### Características Principales

*   🛡️ **Detección Profunda de Desfases**: Audita tablas, columnas, tipos de datos, nulabilidad, valores por defecto, índices y claves foráneas.
*   ⚡ **Facade Programática Completa**: API integral para construir paneles de control y monitoreo personalizados.
*   📐 **Estandarizador de Nombres de Índices**: Valida automáticamente los nombres de índices contra las convenciones de Laravel y detecta duplicados redundantes.
*   🐢 **Auditoría de Migraciones Lentas**: Identifica migraciones pesadas durante la simulación.
*   🔄 **Auditoría de Rollback**: Simula retrocesos de migración para verificar que el esquema sea reversible limpiamente.
*   🌍 **Sincronización Multi-Entorno**: Compara esquemas entre distintos entornos (Staging vs. Producción).
*   🤖 **Listo para CI/CD**: Devuelve códigos de salida estándar de la terminal para integración en pipelines automatizados.

### Instalación

Instala el paquete mediante Composer:

```bash
composer require clcbws/laravel-schema-sentinel
```

El Service Provider y la Facade se registrarán automáticamente.

### Configuración

Publica el archivo de configuración:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

Esto generará el archivo `config/schema-sentinel.php`, donde podrás configurar:
- **`ignore_tables`**: Lista de tablas excluidas del análisis (por ejemplo, `migrations`, `telescope_entries`).
- **`migration_paths`**: Rutas adicionales de migraciones (ideal para arquitecturas modulares).
- **`shadow_connection`**: Configuración de la conexión sombra temporal (por defecto SQLite en memoria aislada).
- **`skip_migrations`**: Lista de migraciones específicas que deben omitirse durante la simulación.
- **`notifications`**: Webhooks de Slack o Discord para recibir alertas automáticas.

---

## 💻 Comandos de Artisan

### Auditoría de Desfase de Esquema (`schema:drift`)

Ejecuta la auditoría principal para inspeccionar el estado del esquema de tu base de datos:

```bash
php artisan schema:drift
```

#### Opciones:

*   **`--fix`**: Genera automáticamente una nueva migración de Laravel para corregir las diferencias detectadas.
*   **`--interactive`**: Confirma y aprueba cada cambio de columna paso a paso.
*   **`--sql`**: Previsualiza las consultas SQL generadas sin ejecutarlas (modo dry-run).
*   **`--strict`**: Reporta tablas y columnas adicionales presentes en la base de datos viva que no estén declaradas en las migraciones.
*   **`--rollback`**: Ejecuta las migraciones y simula su retroceso en la base de datos sombra para validar la integridad del método `down()`.
*   **`--snapshot[=latest]`**: Emplea una instantánea JSON congelada para acelerar las auditorías recurrentes.
*   **`--tag=name`**: Limita la auditoría únicamente a las migraciones marcadas con el comentario `@sentinel-tag name`.

---

### Estandarización de Índices (`schema:standardize-indexes`)

Inspecciona la base de datos en busca de nombres de índice que no sigan las convenciones o índices duplicados redundantes (por ejemplo, índices simples cubiertos por un índice compuesto previo):

```bash
php artisan schema:standardize-indexes
```

#### Opciones:
*   **`--fix`**: Genera un archivo de migración para renombrar índices no estándar y eliminar duplicados automáticamente.

---

### Auditoría Profunda de Datos (`schema:data-drift`)

Audita tablas estáticas, catálogos o seeders para comprobar la consistencia de registros entre diferentes entornos:

```bash
php artisan schema:data-drift --compare-env=production
```

Muestra una tabla de diferencias en la terminal señalando filas faltantes, adicionales o con valores divergentes.

---

### Ingeniería Inversa de Bases de Datos Heredadas (`schema:reverse`)

Aplica ingeniería inversa a una base de datos existente para convertirla en código nativo y ordenado de Laravel:

```bash
php artisan schema:reverse --path=./exports
```

#### Opciones:
*   **`--models`**: Genera modelos Eloquent con relaciones fuertemente tipadas (`belongsTo`, `hasMany`) y bloques de documentación (docblocks).
*   **`--migrations`**: Genera archivos de migración estructurados con Blueprint.
*   **`--seeders`**: Genera seeders poblados con registros reales de la base de datos.
*   **`--enums`**: Genera Backed Enums nativos de PHP 8.4 vinculados a los casts del modelo.

---

### Linter de Archivos de Migración (`schema:sentinel-lint`)

Inspecciona las migraciones de tu proyecto en busca de malas prácticas y antipatrones que puedan provocar desfases:

```bash
php artisan schema:sentinel-lint
```

Detecta:
1. Llamadas directas y sin protección a `DB::statement`.
2. Textos de fecha por defecto específicos de un motor (como `'CURRENT_TIMESTAMP'`).
3. Sentencias destructivas inseguras como `Schema::drop` sin utilizar `IfExists`.

---

### Diagnóstico del Entorno (`schema:sentinel-doctor`)

Verifica la configuración de tu entorno de ejecución (versión de PHP, extensiones PDO y credenciales de la conexión sombra):

```bash
php artisan schema:sentinel-doctor
```

---

### Guías de Ayuda y Corrección Tipográfica (`schema:help`)

Consulta la ayuda detallada y opciones de cada comando. Si cometes un error tipográfico, el motor de sugerencias Levenshtein te sugerirá el comando correcto:

```bash
php artisan schema:help drft
```

---

## 🔌 API Programática

Puedes integrar Sentinel en tus controladores, paneles administrativos o hooks de despliegue mediante la facade `Sentinel`.

### Auditoría de Desfase

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // El esquema presenta desfase
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### Extracción de DTOs del Esquema

Obtén metadatos estructurados que representan la definición completa de tu base de datos:

```php
$tables = Sentinel::parse(); // Devuelve un array de objetos TableDefinition DTO
```

### Estandarización de Índices

Ejecuta el analizador de índices de forma programática:

```php
$results = Sentinel::standardizeIndexes();
// Devuelve un array con índices desviados ('deviations') y redundantes ('redundant')
```

### Auditorías de Desfase de Datos

Verifica la coherencia de datos frente a una conexión específica:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### Ingeniería Inversa Programática

Ejecuta la ingeniería inversa mediante código:

```php
$results = Sentinel::reverse([
    'path' => base_path('exports'),
    'models' => true,
    'migrations' => true,
    'seeders' => false,
    'enums' => true,
]);
```

### Componente Livewire y Vistas Blade

Muestra métricas de salud e integridad de la base de datos dentro de tu panel de administración:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

O realiza comprobaciones directas en plantillas Blade:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        ¡Atención: Se ha detectado desfase en el esquema de la base de datos!
    </div>
@endif
```

---

## ⚠️ Compatibilidad con Versiones Anteriores

Mientras que la versión actual requiere **Laravel 13.x** y **PHP 8.4+**, los desarrolladores en **Laravel 11.x o 12.x** (con PHP 8.2 u 8.3) pueden utilizar la versión estable **v1.6.0**:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| Versión de Laravel | Versiones de PHP Compatibles | Versión del Paquete |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 🛡️ Estado de Compatibilidad y Pruebas

- **Compatibilidad con Laravel**: **Únicamente Laravel 13.x** (sin adaptadores hacia Laravel 10/11/12).
- **Versión Mínima de PHP**: **PHP 8.4+** (probado y certificado en PHP 8.4 y PHP 8.5).
- **Motores de Base de Datos Verificados**:
  - **SQLite**: 3.49+ (en memoria y almacenamiento en disco).
  - **PostgreSQL**: 16+ / 18+ (validado con conexión sombra dedicada).
  - **MySQL / MariaDB**: 8.0+ / 13.0+ (probado en matriz de contenedores CI multi-servicio).
- **Informes y Pruebas Reales**:
  - [Matriz de Pruebas](docs/testing/TEST_MATRIX.md)
  - [Resultados en Aplicaciones Reales](docs/testing/TEST_RESULTS.md)
  - [Limitaciones Conocidas y Comportamiento por Dialecto](docs/testing/KNOWN_LIMITATIONS.md)
  - [Pruebas de Rendimiento (Benchmarks)](docs/testing/PERFORMANCE.md)

---

## 📄 Registro de Cambios

### Versión: v2.1.0 (Release: v2.1.0)

*   **Aislamiento de la Conexión Sombra**: Blindaje de `ShadowMigrationRunner` para evitar mutaciones de la conexión global, con verificación estricta de identidad para prevenir ejecuciones en bases vivas.
*   **Protección Previa a Migraciones**: Reemplazo de la salida forzada `exit(1)` por `MigrationBlockedException`, incorporando un mecanismo determinista de omisión interna contra bloqueos en suites de prueba.
*   **Motor de Diferencias y Normalización de Tipos**: Corrección de falsos positivos entre motores para tipos (`varchar`, `bigint`, `boolean`), índices compuestos y acciones en claves foráneas.
*   **Generador de Migraciones**: Corrección en definiciones de índices y claves foráneas en tablas nuevas, soporte para `--realpath` en Laravel 13 y protecciones de idempotencia.
*   **Seguridad de Datos**: Eliminación del volcado de registros reales en seeders generados, eliminación de enums ficticios y supresión de código de telemetría externa.
*   **Validación en el Ecosistema de Laravel 13**: Verificado en una aplicación real de Laravel 13 e integrado en el pipeline automatizado de GitHub Actions.

Para consultar versiones anteriores, revisa [changelogs/v2.0.0.md](changelogs/v2.0.0.md), [changelogs/v1.6.0.md](changelogs/v1.6.0.md) y [changelogs/v1.0.0.md](changelogs/v1.0.0.md).

---

## 🤝 Créditos y Licencia

- **Autor**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **Empresa**: [Broadway Web Service](https://www.clcbws.com)
- **Licencia**: [MIT License](LICENSE)
