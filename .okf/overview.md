---
type: Framework
title: Venusian Framework
description: A Laravel-like PHP framework for windowed applications and hardware ICs. v0.8.0 ports Laravel's generic non-web surface into a Voyager\ monorepo.
resource: https://github.com/VenusianPHP/framework
tags: [php, framework, voyager, venusian, windowed, hardware, monorepo]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: readme
    resource: ../README.md
    title: Venusian Framework README
    author: human:angel
  - id: agents-md
    resource: ../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
  - id: root-composer
    resource: ../composer.json
    title: venusian/framework composer.json (version 0.8.0)
  - id: src-tree
    resource: every PHP file under ../src/Voyager (32 component directories, 1050 PHP files)
    title: Framework source tree at 8a8600fda67358ec3b38b579f9f13e5107bdc758
  - id: tests-yml
    resource: ../.github/workflows/tests.yml
    title: GitHub Actions tests workflow
  - id: pr1-ci
    resource: https://github.com/VenusianPHP/framework/actions/runs/32527823474
    title: PR 1 tests workflow — 6257 passed on PHP 8.4 and 8.5
---

# What Venusian is

Venusian is a **Laravel-like PHP framework for windowed GUIs, human input, and
integrated circuits**.[^readme] It keeps Laravel's expressive collections,
container, console, database, queue, and validation surface, and drops
web-first assumptions (incoming HTTP, Blade mail, channel authorization,
Auth/View packages) that do not belong on a desktop or a GPIO bus.

Treat **Venusian** as the product name and **Voyager** as the code namespace
and `voyager/*` Composer family.[^root-composer]

The git remote is `VenusianPHP/framework`. `README.md` badges still point at
`github.com/Venusian/framework` — same product, different org slug on the
badge URLs.[^readme]

# Current stage: 0.8.x reconstituting

`AGENTS.md` describes the phase as **reconstituting**.[^agents-md] Composer
package `venusian/framework` **0.8.0**, PHP `^8.4|^8.5`, namespace
`Voyager\`.[^root-composer]

This is no longer a Support-only sketch. `src/Voyager/` holds **32**
component directories and **1050** PHP files, including Vessel, System,
Console, Database, Queue, Validation, and Contracts.[^src-tree] The only
provider still commented out in
[`DefaultProviders`](../src/Voyager/System/DefaultProviders.php) is
`Voyager\Sketches\SketchesServiceProvider` (labelled wave 7).

# What is in the tree

| Directory | Publishable as | PHP files |
|-----------|----------------|----------:|
| `Broadcasting/` | `voyager/broadcasting` | 20 |
| `Bus/` | `voyager/bus` | 16 |
| `Cache/` | `voyager/cache` | 53 |
| `Collections/` | `voyager/collections` | 11 |
| `Concurrency/` | `voyager/concurrency` | 6 |
| `Conditionable/` | `voyager/conditionable` | 2 |
| `Config/` | `voyager/config` | 1 |
| `Console/` | `voyager/console` | 78 |
| `Contracts/` | `voyager/contracts` | 114 |
| `Database/` | `voyager/database` | 230 |
| `Encryption/` | `voyager/encryption` | 3 |
| `Events/` | `voyager/events` | 7 |
| `Filesystem/` | `voyager/filesystem` | 8 |
| `Hashing/` | `voyager/hashing` | 6 |
| `Http/` | `voyager/http` | 18 |
| `JsonSchema/` | `voyager/json-schema` | 12 |
| `Log/` | `voyager/log` | 11 |
| `Macroable/` | `voyager/macroable` | 1 |
| `MagicAliases/` | `voyager/magic-aliases` | 1 |
| `Notifications/` | `voyager/notifications` | 21 |
| `NutsAndBolts/` | `voyager/nuts-and-bolts` | 73 |
| `Pagination/` | `voyager/pagination` | 7 |
| `Pipeline/` | `voyager/pipeline` | 3 |
| `Process/` | `voyager/process` | 14 |
| `Queue/` | `voyager/queue` | 95 |
| `Redis/` | `voyager/redis` | 16 |
| `Reflection/` | `voyager/reflection` | 3 |
| `System/` | *(not a split package)* | 112 |
| `Testing/` | `voyager/testing` | 34 |
| `Translation/` | `voyager/translation` | 11 |
| `Validation/` | `voyager/validation` | 45 |
| `Vessel/` | `voyager/vessel` | 18 |

Counts from `find src/Voyager/<Dir> -name '*.php'` at
`8a8600fda67358ec3b38b579f9f13e5107bdc758`.[^src-tree]

The root `replace` block lists the **31** `voyager/*` names above and does
**not** list `voyager/system`.[^root-composer] System is the application
skeleton — see [package split](/architecture/package-split.md).

One concept per publishable package lives under [packages](/packages/).

# Requirements

- PHP `^8.4|^8.5`.[^root-composer]
- CI installs `intl`, `pdo`, `pdo_sqlite`, `pdo_mysql`, and `gmp` (plus
  `dom`, `curl`, `libxml`, `mbstring`, `zip`).[^tests-yml]
- Runtime dependencies are the union of the ported Laravel components
  (Flysystem, Carbon, Symfony Console/Process/Mime, Guzzle, Predis, Monolog,
  `laravel/prompts`, `doctrine/inflector`, …). Read `composer.json` rather
  than a stale short list.[^root-composer]
- Dev: `pestphp/pest ^4`, `mockery/mockery ^1.6`, `fakerphp/faker ^1.24`,
  `opis/json-schema ^2.4.1`.[^root-composer]

# Tests and CI

`.github/workflows/tests.yml` checks out with `actions/checkout@v5` and runs
`vendor/bin/pest` on PHP 8.4 and 8.5.[^tests-yml]

PR 1 (`ci/stable-tests`, merged as `8a8600f`) made that workflow green.
Actions run `32527823474` reported **6257 passed**, 12 skipped, 7 deprecated,
14 notices, 18843 assertions on **both** matrix cells.[^pr1-ci] Do not cite
"184 tests" or "no test suite" as current.

`phpunit.xml` excludes every `tests/**/deferred/` directory. Leftover
PHPUnit `TestCase` classes in the default suite are concentrated in
Database, Queue, Notifications, and Broadcasting; they run under Pest v4.
See [known gaps](/known-gaps.md).

# Where the Laravel code came from

Most components record `extra.venusian.upstream-ref: v12.67.0` on their
sub-package manifest. See [Laravel lineage](/architecture/laravel-lineage.md).

# How to work here

`AGENTS.md` is the contract — read this bundle before changing framework
code, and write durable facts back.[^agents-md] See
[maintaining this knowledge bundle](/playbooks/maintaining-this-bundle.md)
and [local development](/playbooks/local-development.md).

[^readme]: Venusian Framework README
[^agents-md]: Agent guidelines — venusian/framework
[^root-composer]: venusian/framework composer.json (version 0.8.0)
[^src-tree]: Framework source tree at 8a8600f
[^tests-yml]: GitHub Actions tests workflow
[^pr1-ci]: PR 1 tests workflow — 6257 passed on PHP 8.4 and 8.5
