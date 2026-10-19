# Laravel Schema Sentinel

[![Latest Stable Version](https://img.shields.io/packagist/v/clcbws/laravel-schema-sentinel?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![GitHub Stars](https://img.shields.io/github/stars/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/clcbws/laravel-schema-sentinel.svg?style=flat-square)](https://packagist.org/packages/clcbws/laravel-schema-sentinel)
[![CI Tests](https://img.shields.io/github/actions/workflow/status/ahtesham-clcbws/laravel-schema-sentinel/ci.yml?branch=development&style=flat-square&label=tests)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/actions)
[![License](https://img.shields.io/github/license/ahtesham-clcbws/laravel-schema-sentinel?style=flat-square)](https://github.com/ahtesham-clcbws/laravel-schema-sentinel/blob/main/LICENSE)

**Leia isto em:** [English](README.md) | [Roman Hindustani](README.hi-Latn.md) | [हिन्दी](README.hi.md) | [اردو](README.ur.md) | [العربية](README.ar.md) | [Español](README.es.md) | **Português (Brasil)**

> [!NOTE]
> Este documento em inglês ([English README](README.md)) é a fonte canônica de informação para o Laravel Schema Sentinel. Todas as traduções são mantidas em sincronia com este documento.

O **Laravel Schema Sentinel** é um pacote avançado para governança e integridade de esquemas de banco de dados no Laravel. Ele detecta e resolve o "Schema Drift" (divergências entre as migrations e o estado real do banco de dados) e oferece ferramentas automatizadas para padronizar nomes de índices, auditar a sincronização de dados, validar arquivos de migração (linting) e aplicar engenharia reversa em bancos legados.

---

## 📖 Sumário

- [Primeiros Passos](#-primeiros-passos)
  - [Introdução](#introdução)
  - [Recursos Principais](#recursos-principais)
  - [Instalação](#instalação)
  - [Configuração](#configuração)
- [Comandos Artisan](#-comandos-artisan)
  - [Auditoria de Schema Drift (`schema:drift`)](#auditoria-de-schema-drift-schemadrift)
  - [Padronização de Índices (`schema:standardize-indexes`)](#padronização-de-índices-schemastandardize-indexes)
  - [Auditoria Profunda de Dados (`schema:data-drift`)](#auditoria-profunda-de-dados-schemadata-drift)
  - [Engenharia Reversa de Banco Legado (`schema:reverse`)](#engenharia-reversa-de-banco-legado-schemareverse)
  - [Linter de Arquivos de Migração (`schema:sentinel-lint`)](#linter-de-arquivos-de-migração-schemasentinel-lint)
  - [Diagnóstico de Ambiente (`schema:sentinel-doctor`)](#diagnóstico-de-ambiente-schemasentinel-doctor)
  - [Guia de Ajuda e Sugestão de Comandos (`schema:help`)](#guia-de-ajuda-e-sugestão-de-comandos-schemahelp)
- [API Programática](#-api-programática)
  - [Auditoria de Drift](#auditoria-de-drift)
  - [Extração de DTOs do Esquema](#extração-de-dtos-do-esquema)
  - [Padronização de Índices](#padronização-de-índices)
  - [Auditorias de Data Drift](#auditorias-de-data-drift)
  - [Engenharia Reversa Programática](#engenharia-reversa-programática)
  - [Componente Livewire e Telas Blade](#componente-livewire-e-telas-blade)
- [Compatibilidade com Versões Anteriores](#-compatibilidade-com-versões-anteriores)
- [Status de Compatibilidade e Testes](#-status-de-compatibilidade-e-testes)
- [Registro de Alterações](#-registro-de-alterações)
- [Créditos e Licença](#-créditos-e-licença)

---

## 🚀 Primeiros Passos

### Introdução

Em fluxos modernos de implantação, os esquemas de banco de dados podem divergir facilmente das migrations devido a correções emergenciais diretas no banco (hotfixes), importações manuais ou rollbacks incompletos. O Schema Sentinel fornece um mecanismo de simulação em isolamento total que executa as migrations em um banco sombra (shadow database) para determinar o esquema "ideal", compara-o diretamente com a conexão ativa e gera migrations corretivas automaticamente.

### Recursos Principais

*   🛡️ **Detecção Profunda de Drift**: Audita tabelas, colunas, tipos de dados, nulabilidade, valores padrão, índices e chaves estrangeiras.
*   ⚡ **Facade Programática Completa**: Cobertura de API para construção de dashboards e monitoramento em tempo real.
*   📐 **Padronizador de Nomenclatura de Índices**: Valida automaticamente os nomes de índices de acordo com as convenções do Laravel e detecta redundâncias.
*   🐢 **Auditoria de Migrations Lentas**: Identifica arquivos de migração que consomem muito tempo durante a simulação.
*   🔄 **Auditoria de Rollback**: Simula retrocessos de migrations no banco sombra para garantir reversibilidade segura e limpa.
*   🌍 **Sincronização Multi-Ambiente**: Compare seu esquema entre diferentes ambientes (ex: Staging vs. Produção).
*   🤖 **Pronto para CI/CD**: Retorna códigos de saída padrão do terminal para validação automatizada em pipelines.

### Instalação

Instale o pacote via Composer:

```bash
composer require clcbws/laravel-schema-sentinel
```

O Service Provider e a Facade serão registrados automaticamente.

### Configuração

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag="schema-sentinel-config"
```

Isso criará o arquivo `config/schema-sentinel.php`, permitindo configurar:
- **`ignore_tables`**: Lista de tabelas desconsideradas na análise (ex: `migrations`, `telescope_entries`).
- **`migration_paths`**: Caminhos adicionais de migrations (útil em arquiteturas modulares).
- **`shadow_connection`**: Configuração do banco sombra temporário (o padrão é SQLite in-memory isolado).
- **`skip_migrations`**: Lista de arquivos de migração a serem ignorados durante a simulação.
- **`notifications`**: Webhooks para Slack e Discord para envio de alertas automáticos de drift.

---

## 💻 Comandos Artisan

### Auditoria de Schema Drift (`schema:drift`)

Execute a auditoria principal para inspecionar a integridade do seu banco de dados:

```bash
php artisan schema:drift
```

#### Opções:

*   **`--fix`**: Gera automaticamente uma nova migration do Laravel para alinhar o banco de dados.
*   **`--interactive`**: Confirma as alterações de colunas passo a passo.
*   **`--sql`**: Pré-visualiza os comandos SQL gerados no modo de simulação (dry-run).
*   **`--strict`**: Reporta tabelas e colunas adicionais presentes no banco ativo que não foram definidas nas migrations.
*   **`--rollback`**: Executa e reverte as migrations no banco sombra para validar o método `down()`.
*   **`--snapshot[=latest]`**: Utiliza um snapshot JSON congelado para acelerar auditorias subsequentes.
*   **`--tag=name`**: Restringe as auditorias apenas às migrations marcadas com `@sentinel-tag name`.

---

### Padronização de Índices (`schema:standardize-indexes`)

Inspeciona o banco de dados em busca de índices fora do padrão e redundantes (ex: índices de coluna simples já cobertos por índices compostos prévios):

```bash
php artisan schema:standardize-indexes
```

#### Opções:
*   **`--fix`**: Gera uma migration para renomear automaticamente índices fora do padrão e remover os redundantes.

---

### Auditoria Profunda de Dados (`schema:data-drift`)

Audita tabelas de domínio estáticas ou de seed para verificar a consistência de registros entre ambientes:

```bash
php artisan schema:data-drift --compare-env=production
```

Exibe uma tabela de diferenças no terminal destacando registros faltantes, adicionais ou divergentes.

---

### Engenharia Reversa de Banco Legado (`schema:reverse`)

Aplica engenharia reversa em um banco legado existente para criar uma estrutura nativa e limpa no Laravel:

```bash
php artisan schema:reverse --path=./exports
```

#### Opções:
*   **`--models`**: Gera Models Eloquent com relacionamentos tipados (`belongsTo`, `hasMany`) e docblocks.
*   **`--migrations`**: Gera arquivos de migration utilizando o Blueprint do Laravel.
*   **`--seeders`**: Gera seeders preenchidos com amostras de dados reais do banco.
*   **`--enums`**: Gera Backed Enums nativos do PHP 8.4 vinculados aos casts dos models.

---

### Linter de Arquivos de Migração (`schema:sentinel-lint`)

Analisa as migrations do seu projeto em busca de más práticas que causam divergência de esquema:

```bash
php artisan schema:sentinel-lint
```

Detecta:
1. Chamadas diretas de `DB::statement`.
2. Textos de data padrão específicos de banco de dados (como `'CURRENT_TIMESTAMP'`).
3. Chamadas inseguras de exclusão `Schema::drop` sem verificação com `IfExists`.

---

### Diagnóstico de Ambiente (`schema:sentinel-doctor`)

Verifica a saúde das configurações do seu ambiente de desenvolvimento (versão do PHP, drivers PDO e conexão sombra):

```bash
php artisan schema:sentinel-doctor
```

---

### Guia de Ajuda e Sugestão de Comandos (`schema:help`)

Obtenha ajuda detalhada, dicas e opções dos comandos. Se você cometer um erro de digitação, o mecanismo de sugestão baseado em Levenshtein sugerirá o comando correto automaticamente:

```bash
php artisan schema:help drft
```

---

## 🔌 API Programática

Você pode integrar o Sentinel programaticamente em controllers, dashboards ou hooks de deploy utilizando a facade `Sentinel`.

### Auditoria de Drift

```php
use Sentinel\SchemaSentinel\Facades\Sentinel;

$diff = Sentinel::check(strict: true);

if ($diff->hasDifferences()) {
    // O esquema divergiu
    $healthScore = $diff->getHealthScore();
    $missingTables = $diff->missingTables;
}
```

### Extração de DTOs do Esquema

Obtenha metadados estruturados que descrevem a estrutura completa do banco de dados:

```php
$tables = Sentinel::parse(); // Retorna um array de objetos TableDefinition DTO
```

### Padronização de Índices

Execute o analisador de índices programaticamente:

```php
$results = Sentinel::standardizeIndexes();
// Retorna um array contendo os índices divergentes ('deviations') e redundantes ('redundant')
```

### Auditorias de Data Drift

Valide a consistência dos dados em relação a uma conexão específica:

```php
$dataDrift = Sentinel::dataDrift('production');
```

### Engenharia Reversa Programática

Invoque o gerador de engenharia reversa via código:

```php
$results = Sentinel::reverse([
    'path' => base_path('exports'),
    'models' => true,
    'migrations' => true,
    'seeders' => false,
    'enums' => true,
]);
```

### Componente Livewire e Telas Blade

Exiba métricas de integridade do banco de dados diretamente nos seus templates de administração:

```blade
<!-- Livewire Component -->
<livewire:sentinel-database-health />
```

Ou realize verificações programáticas em layouts Blade:

```blade
@if(\Sentinel\SchemaSentinel\Facades\Sentinel::check()->hasDifferences())
    <div class="alert alert-danger">
        Aviso: Divergência de esquema de banco de dados detectada!
    </div>
@endif
```

---

## ⚠️ Compatibilidade com Versões Anteriores

Embora a versão mais recente deste pacote exija o **Laravel 13.x** e **PHP 8.4+**, projetos executando **Laravel 11.x ou 12.x** (com PHP 8.2 ou 8.3) podem utilizar a versão estável **v1.6.0**:

```bash
composer require clcbws/laravel-schema-sentinel:~1.6.0
```

| Versão do Laravel | Versões do PHP Suportadas | Versão do Pacote |
| :--- | :--- | :--- |
| **Laravel 11.x** | PHP `8.2` - `8.4` | `~1.6.0` |
| **Laravel 12.x** | PHP `8.2` - `8.5` | `~1.6.0` |

---

## 🛡️ Status de Compatibilidade e Testes

- **Compatibilidade com Laravel**: **Exclusivamente Laravel 13.x** (sem camadas de compatibilidade para Laravel 10/11/12).
- **Versão Mínima do PHP**: **PHP 8.4+** (totalmente testado e certificado no PHP 8.4 e PHP 8.5).
- **Motores de Banco de Dados Verificados**:
  - **SQLite**: 3.49+ (em memória e baseado em arquivo).
  - **PostgreSQL**: 16+ / 18+ (validado com conexão sombra dedicada).
  - **MySQL / MariaDB**: 8.0+ / 13.0+ (testado em matriz de contêineres CI com múltiplos serviços).
- **Relatórios de Testes e Evidências Reais**:
  - [Matriz de Testes](docs/testing/TEST_MATRIX.md)
  - [Resultados em Aplicação Real](docs/testing/TEST_RESULTS.md)
  - [Limitações Conhecidas e Comportamento por Dialeto](docs/testing/KNOWN_LIMITATIONS.md)
  - [Benchmarks de Desempenho](docs/testing/PERFORMANCE.md)

---

## 📄 Registro de Alterações

### Versão: v2.1.0 (Release: v2.1.0)

*   **Isolamento de Migrations no Banco Sombra**: Blindagem do `ShadowMigrationRunner` para impedir a mutação da conexão global; adição de verificações rigorosas de identidade prevenindo execuções acidentais em conexões ativas.
*   **Guarda Pré-Migração**: Substituição de chamadas `exit(1)` por `MigrationBlockedException` e implementação de bypass interno determinístico para prevenir reentrâncias e falhas em testes.
*   **DiffEngine e Normalização de Tipos**: Eliminação de falsos positivos entre diferentes bancos de dados para tipos (`varchar`, `bigint`, `boolean`), índices compostos e ações em chaves estrangeiras.
*   **Gerador de Migrations**: Correção de definições ausentes de índices e chaves estrangeiras na criação de tabelas, adição de suporte ao `--realpath` no Laravel 13 e proteções de idempotência.
*   **Segurança de Dados**: Remoção do despejo de dados reais do banco em seeders gerados, remoção da fabricação de enums fictícios e eliminação de telemetria externa.
*   **Validação no Ecossistema do Laravel 13**: Validado em uma aplicação Laravel 13 real e limpa, com matriz automatizada no GitHub Actions.

Para consultar versões anteriores, veja [changelogs/v2.0.0.md](changelogs/v2.0.0.md), [changelogs/v1.6.0.md](changelogs/v1.6.0.md) e [changelogs/v1.0.0.md](changelogs/v1.0.0.md).

---

## 🤝 Créditos e Licença

- **Autor**: [Ahtesham](mailto:ahtesham@clcbws.com)
- **Empresa**: [Broadway Web Service](https://www.clcbws.com)
- **Licença**: [MIT License](LICENSE)
