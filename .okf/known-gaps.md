---
type: Known Issues
title: Known gaps in Venusian v0.8.0
description: Remaining defects, deliberate port cuts, and claims retired against 0.8.x HEAD after PR 1 (8a8600f).
tags: [defects, technical-debt, php, porting]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-23T00:03:43Z }
verified: { by: agent:framework-auditor, at: 2026-08-23T00:03:43Z }
verification_key: 'agent:framework-auditor@4e2910dd3783ff661dea9def23d1059bbb91b400'
stale_after: 2026-11-22
sources:
  - id: src-tree
    resource: every PHP file under ../src/Voyager
    title: Framework source tree at 4e2910d
  - id: tests-tree
    resource: ../tests and ../phpunit.xml
    title: Test tree, Pest.php, and deferred exclusions
  - id: tests-yml
    resource: ../.github/workflows/tests.yml
    title: GitHub Actions tests workflow
  - id: pr1-ci
    resource: https://github.com/VenusianPHP/framework/actions/runs/32527823474
    title: PR 1 tests workflow — 6257 passed
  - id: magic-alias
    resource: ../src/Voyager/MagicAliases/MagicAlias.php
    title: MagicAlias::shouldReceive return type
  - id: subpackage-manifests
    resource: ../src/Voyager/*/composer.json
    title: Per-package composer manifests (34 files)
  - id: default-providers
    resource: ../src/Voyager/System/DefaultProviders.php
    title: Default service providers
  - id: agents-md
    resource: ../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
---

# Overview

Claims below were checked against `src/`, `tests/`, `composer.json`, and
`.github/workflows/tests.yml` at `4e2910dd3783ff661dea9def23d1059bbb91b400`
(0.8.x HEAD: Sketches) plus the housekeeping commits on this pass, not
against older bundle text.[^src-tree]

The 2026-08-19 Support-foundation defects (`Stringable` resolution, `Str`
iterable hints, `LazyCollection::make(Closure)`, `Str::singular()`, `now()`,
`ReflectsClosures` as a class, `Arr::first()`/`chunk()`, stub `Pluralizer`)
are **fixed in source**. They stay in [log.md](log.md) as history. This file
keeps only what is still true, plus a retired-claims list so those sentences
are not copied forward.

# Retired — do not repeat

| Old claim | Tree fact |
|-----------|-----------|
| `src/Voyager/Contracts/` does not exist | **127** PHP files (7 under `Contracts/Sketches`, 6 under `Contracts/Workflows`); `voyager/contracts` has its own `composer.json`[^src-tree] |
| Database / wave 6 has not landed | **230** PHP files; `Instrument\Model` and `Capsule\Manager` exist; `DatabaseServiceProvider` is in [`DefaultProviders`](../src/Voyager/System/DefaultProviders.php)[^default-providers] |
| `config/` is empty | **11** files: `app`, `broadcasting`, `cache`, `concurrency`, `database`, `filesystems`, `hashing`, `logging`, `queue`, `sketches`, `workflows` |
| No test suite / 184 Pest tests | PR 1 CI: **6257 passed**, 12 skipped, 18843 assertions on PHP 8.4 and 8.5[^pr1-ci] |
| CI uses `checkout@v4` and only `intl` | `actions/checkout@v5`; extensions include `intl`, `pdo`, `pdo_sqlite`, `pdo_mysql`, `gmp`[^tests-yml] |
| Waves 5–6 tests are still PHPUnit | Default suite has **0** `extends PHPUnit\Framework\TestCase` classes, including Workflows. Broadcasting, Notifications, Queue, Database, and Workflows are Pest v4. The only leftover TestCase that is a test is deferred Testbench, below. |
| `MagicAlias::shouldReceive()` is incompatible with Mockery 1.6.15 (`Expectation` vs `CompositeExpectation`) | Return type is `Mockery\ExpectationInterface` (PR 1). `CompositeExpectation` implements that interface.[^magic-alias] |
| Sub-package manifests still require `fabricate/*` | **No** `fabricate/*` `require` in any of the 34 manifests. One comment in `MagicAlias.php` still mentions `fabricate/magic-aliases`.[^subpackage-manifests] |
| Root `replace` lists `voyager/system` | It does not. 34 `voyager/*` entries including `voyager/graph`, `voyager/sketches`, and `voyager/workflows`; System has no `composer.json`. |
| No record of the upstream Laravel revision | Most manifests set `extra.venusian.upstream-ref` to **`v12.67.0`**. |
| `DefaultProviders` comments Sketches as wave 7; no `src/Voyager/Sketches/` | `voyager/sketches` landed. `SketchesServiceProvider` is uncommented. `SketchRunner` is a direct Arduino loop — no `Flow/` copy. |
| CLI / sketch-only Support foundation; 26 declarations; 5 publishable packages | See [overview](/overview.md). 35 directories, 34 publishable packages including `voyager/graph` and `voyager/sketches`. |
| `now()` fatals / Date alias does not exist | Global `now()` in `NutsAndBolts/Helpers/time.php` returns `Carbon::now()`. Namespaced `Voyager\NutsAndBolts\now()` and `System/helpers.php` `now()` call `Date::now()`. `NutsAndBolts/MagicAliases/Date.php` exists. |
| `voyager/contracts` planned-but-unbuilt | Built. Foundation `Arrayable` / `Jsonable` live under `Voyager\Contracts\NutsAndBolts`. `Enumerable` remains `Voyager\NutsAndBolts\Contracts\Enumerable` in Collections. |

# Open — deliberate cuts

Recorded so nobody "restores" them from upstream by mistake.

## Queue — Beanstalkd, SQS, DynamoDB

`QueueServiceProvider::registerConnectors()` registers Null, Sync, Deferred,
Background, Failover, Database, Redis only. Comment in that method: Beanstalkd
and SQS dropped; AWS barred except S3. No DynamoDB failed-job provider.
`config/queue.php` defaults to `sync` / failed-job `file`.

The database driver **source is present** (`DatabaseQueue`,
`DatabaseConnector`, `Failed\DatabaseFailedJobProvider`,
`Failed\DatabaseUuidFailedJobProvider`). Tests for it stay in
`tests/Queue/deferred/` — see process debt below. The comment at
`config/queue.php:15-16` still says Database "is not built yet"; that line is
stale.

## Broadcasting — Ably and incoming channel auth

No Ably broadcaster. `Contracts\Broadcasting\Broadcaster` keeps `broadcast()`
only. `BroadcastController` and `BroadcastManager::{routes,userRoutes,channelRoutes,socket}`
are absent. Four upstream test files are cut, not deferred — see
`tests/Broadcasting/README.md`. `Broadcaster::channel()` can still *register*
authenticators.

## Notifications — mail

No `MailChannel`, `MailMessage`, `SimpleMessage`, or Blade email view.
`ChannelManager::$defaultChannel` is `'database'`. Two upstream mail test
files are cut — see `tests/Notifications/README.md`.

## Validation — Auth, HTTP response, precognition

| Cut | Why (still true) |
|-----|------------------|
| `Rules\Can` / `Rule::can()` | Body was `Gate::allows()`. No Auth package. |
| `ValidationException` HTTP surface (`$response`, `$status`, `$redirectTo`, …) | Incoming-HTTP. `errors()` / `errorBag()` / `withMessages()` remain. |
| Precognition branch of `ValidatesWhenResolvedTrait` | Comment only; `isPrecognitive()` came from a request trait Http already cut. |

`exists` / `unique` are **not** cut. `Instrument\Model` now exists.
`ValidationServiceProvider` still wires the presence verifier only when `db`
is bound. The three database-backed test files remain in
`tests/Validation/deferred/` (README still says "waiting on wave 6").

## Http is the client only

`src/Voyager/Http` is `Http/Client/` (18 PHP files). No
`Http\UploadedFile` or `Http\Testing\FileFactory`. `file` / `image` / `mimes`
/ `dimensions` rules work against Symfony `File`/`UploadedFile`. Three
upstream tests stay in `tests/Validation/deferred/` until Venusian defines a
file-validation subject.

# Open — defects and debt

## `DatabaseBatchRepository::find()` has no `return null`

```77:87:src/Voyager/Bus/DatabaseBatchRepository.php
    public function find(string $batchId): ?Batch
    {
        // ...
        if ($batch) {
            return $this->toBatch($batch);
        }
    }
```

A typed `?Batch` cannot fall off the end. `tests/Bus/deferred/BusBatchTest.php`
still fails `batch can be deleted` and `options serialization on postgres` on
this. One-line fix, still unapplied.

## `System\Bus\PendingChain::__construct(mixed $job, array $chain)`

`$chain` is `array`. The deferred Bus/Queue chaining case still passes a
string (`BusBatchTest`: "chained closure after multiple batches is properly
dispatched").

## `TransformsToResourceCollection` is an empty trait

`src/Voyager/Collections/Concerns/TransformsToResourceCollection.php` is still
an empty body, `use`d by `Collection` and the paginators.

## `Str::is()` rejects `null` `$value`

`$value` is `string`, so `Str::is('foo*', null)` is a `TypeError`. Laravel
coerces null to `''`. Divergence, not a crash in the default suite.

## `foreach ($pattern as $pattern)` in `Str::is()` / `Str::isMatch()`

Not a bug — PHP iterates a by-value copy. Left as Laravel verbatim.

## Sub-package manifests still drift (no `fabricate/*`)

| Manifest | Current leftover |
|----------|------------------|
| `Collections/composer.json` | requires `voyager/conditionble ^0.8.0` (typo; package is `conditionable`) |
| `Reflection/composer.json` | requires `voyager/collection ^0.8` (singular; package is `collections`) |
| `NutsAndBolts/composer.json` | PHP `^8.4\|^8.5\|^8.6` while root is `^8.4\|^8.5` |
| `Macroable/composer.json` | `homepage` / `support` still `ScrapyardIO/framework` |

Invisible under the monorepo autoloader; bites on a standalone split.

## Deferred tests whose original blocker has moved

`phpunit.xml` excludes **14** `deferred/` paths. Measured deferred PHP
files: **51** (41 `*Test.php`, plus fixtures). `tests/Pagination/deferred`
is still listed in `phpunit.xml` but the directory is gone.
`tests/Database/deferred` holds only a README and is not excluded (no
`*Test.php` there). Still real:

- **Orchestra\Testbench** — 14 deferred PHP files plus
  `tests/System/Stubs/{CloudQueueCase,TestCaseWithTrait}.php`. Root
  `composer.json` does not require `orchestra/testbench`. Human decision:
  rewrite against a Voyager harness or drop. Canonical:
  `tests/Testing/deferred/ConfigShowCommandTest.php`.
- **File-upload value object** — three Validation deferred files, above.
- **BusBatchTest** — two `src/` bugs, above.
- **Log `ContextTest` Suit lengths** — serialized enum literals still use
  upstream byte lengths (`E:31:` / `E:43:`) for `Tests\Log\Fixtures\Suit:Clubs`
  (29 / 41 bytes). Harmless while the file is excluded.
- **Parked after Database landed** — `tests/Validation/deferred` presence
  tests, `tests/Queue/deferred` database-driver tests, and
  `tests/Testing/deferred/{InteractsWithDatabaseTest,TestDatabasesTest}.php`
  still sit in `deferred/`. Their READMEs still say "wave 6". Database is in
  the tree; unparking is remaining work, not a missing component. Do not
  claim they pass — they are not in the default suite.

## PHPUnit leftover style debt

Measured at `4e2910d` plus this housekeeping pass:

- **Default suite: 0** leftover `extends TestCase` / `extends PHPUnit\Framework\TestCase` classes. No `MockeryPHPUnitIntegration`. Workflows tests (`tests/Workflows/*.php`, 5 files) and Sketches tests (`tests/Sketches/*.php`, 3 files) plus `tests/System/SketchMakeCommandTest.php` are Pest closures.
- **Still PHPUnit TestCase (deferred):** `tests/Testing/deferred/ConfigShowCommandTest.php` (`Orchestra\Testbench\TestCase`). Left alone — Testbench is not a dependency.
- **Fixtures, not tests:** `tests/System/Stubs/{CloudQueueCase,TestCaseWithTrait}.php`.
- `tests/Console/deferred/ConsoleApplicationTest.php` and
  `tests/System/deferred/FoundationInteractsWithDatabaseTest.php` mention
  TestCase in strings / anonymous classes; they are already Pest.

## Workflows gaps (verified against source)

Still true at `4e2910d`:

- No sync `BatchNode` / `BatchFlow`; only `AsyncBatchNode`,
  `AsyncParallelBatchNode`, `AsyncBatchFlow`, `AsyncParallelBatchFlow`.
- `WorkflowsServiceProvider` is not in `DefaultProviders`.
- `AsyncRunnable` is an empty marker (`extends RuntimeAware`) and cannot
  declare `_runAsync(SharedBag $shared)` because `SharedBag` lives in
  `Voyager\Workflows`, not Contracts.

Fixed on this pass, not a remaining gap:
`FiberRuntime::loop()` used to throw deadlock after a top-level
`await(delay())` fulfilled the last timer. It now re-checks settlement
before treating an empty schedule as deadlock. PHP 8.5
`SplObjectStorage::{attach,contains,detach}` deprecations in this class
were replaced with array access.

## Upward imports into `Voyager\System`

`AGENTS.md`: nothing below System depends on System.[^agents-md] Current
exceptions, Laravel-shaped:

| From | Import |
|------|--------|
| `Bus\Dispatcher`, `Bus\ChainedBatch` | `System\Bus\PendingChain` / `Dispatchable` |
| `Queue\CallQueuedClosure` | `System\Bus\Dispatchable` |
| `Broadcasting\AnonymousEvent` | `System\Events\Dispatchable` |
| `Testing\*` | `System\Application`, `System\Testing`, `System\Bus\PendingChain` |

## Unused Auth / View contract imports

`src/Voyager/System/helpers.php` aliases `Voyager\Contracts\Auth\Factory` and
`Voyager\Contracts\View\{Factory,View}`. Those directories do not exist.
PHP `use` is lazy, and no helper in that file references the aliases.

# Related

- [Overview](/overview.md) — current tree shape.
- [Port hazards](/architecture/port-hazards.md) — why typed ports fail quietly.
- [Local development](/playbooks/local-development.md) — how to run Pest.

[^src-tree]: Framework source tree at 4e2910d
[^tests-yml]: GitHub Actions tests workflow
[^pr1-ci]: PR 1 tests workflow — 6257 passed
[^magic-alias]: MagicAlias::shouldReceive return type
[^subpackage-manifests]: Per-package composer manifests
[^default-providers]: Default service providers
[^agents-md]: Agent guidelines — venusian/framework
