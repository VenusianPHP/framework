---
type: Convention
title: Dependency direction
description: The layering rule governing which Venusian package may depend on which, including the contracts split and the measured upward edges into System.
tags: [architecture, layering, dependencies, packaging, contracts, rules]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-23T03:08:15Z }
verified: { by: agent:framework-auditor, at: 2026-08-23T03:08:15Z }
verification_key: 'agent:framework-auditor@3e93855e843921190adc69bcfd272ecb538d94b3'
stale_after: 2026-11-22
sources:
  - id: agents-md
    resource: ../../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json (version 0.8.0)
  - id: default-providers
    resource: ../../src/Voyager/System/DefaultProviders.php
    title: Composition-root provider list
  - id: upward-imports
    resource: use Voyager\System\ under ../../src/Voyager/{Bus,Queue,Broadcasting,Testing}
    title: Measured imports of Voyager\System from below System
---

# The rule

From `AGENTS.md`:[^agents-md]

- **System** may be aware of everything.
- **Nothing below System depends on System.**
- **NutsAndBolts** may depend on its sibling packages (Collections,
  Conditionable, Macroable, Reflection, …) **and `voyager/contracts`** — not
  other components.
- **Other components** may depend on NutsAndBolts. Peer dependencies may depend
  on other peer dependencies when justified (Broadcasting ↔ Filesystem for
  `.env` install writes). Never System.

The NutsAndBolts packages may depend on each other freely; they are one family.

# Two kinds of contracts

**Family contracts that stayed put.** `Voyager\NutsAndBolts\Contracts\Enumerable`
still lives in `src/Voyager/Collections/Contracts/Enumerable.php`.

**Framework-wide contracts.** `src/Voyager/Contracts/` exists (127 PHP files,
namespace `Voyager\Contracts\…`, package `voyager/contracts`). That includes
`Voyager\Contracts\NutsAndBolts\{Arrayable,Jsonable,CanBeEscapedWhenCastToString,…}`
and `Voyager\Contracts\Sketches`.
The old "declared in replace, no directory" picture is false.

`AGENTS.md` still *permits* NutsAndBolts to depend on `voyager/contracts`.
`NutsAndBolts/composer.json` and `Collections/composer.json` both require it.

# The layers (current tree)

```
System                     composition root; DefaultProviders; sketches kernel;
                           no composer.json; not in replace
   ^
Components                 Broadcasting, Bus, Cache, Concurrency, Config,
                           Console, Database, Encryption, Events, Filesystem,
                           Graph, Hashing, Http (client), JsonSchema, Log,
                           Notifications, Pagination, Pipeline, Process,
                           Queue, Redis, Sketches, Testing, Translation,
                           Validation, Vessel, MagicAliases, Workflows
   ^
voyager/contracts          Voyager\Contracts\* (including Contracts/Sketches)
   ^
NutsAndBolts family        NutsAndBolts, Collections, Conditionable,
                           Macroable, Reflection
```

Vessel is the container (`Illuminate\Container`). System's `Application`
extends that world and boots the provider list in
[`DefaultProviders`](../../src/Voyager/System/DefaultProviders.php).[^default-providers]
Pagination and Process have no provider (comments in that file).
`WorkflowsServiceProvider` and `GraphServiceProvider` exist but are **not**
on that list.
`SketchesServiceProvider` is uncommented (wave 7).

# Measured upward edges

These `use Voyager\System\…` imports sit **below** System:[^upward-imports]

| From | To |
|------|----|
| `Bus\Dispatcher` | `System\Bus\PendingChain` |
| `Bus\ChainedBatch` | `System\Bus\Dispatchable` |
| `Queue\CallQueuedClosure` | `System\Bus\Dispatchable` |
| `Broadcasting\AnonymousEvent` | `System\Events\Dispatchable` |
| `Testing\Concerns\RunsInParallel` | `System\Application` |
| `Testing\Concerns\TestDatabases` | `System\Testing` |
| `Testing\Fakes\PendingChainFake` | `System\Bus\PendingChain` |
| `Testing\Fakes\ExceptionHandlerFake` | `System\Testing\Concerns\WithoutExceptionHandlingHandler` |

Laravel keeps `Dispatchable` / `PendingChain` on Foundation, so the port
copied the upward edge. The rule still forbids it. Recorded as layering debt
in [known gaps](/known-gaps.md), not as "no violations exist".

The wave-0 NutsAndBolts-only import table is historical. Do not treat it as
the current graph.

# Pagination and Database

Pagination names `Database\Instrument\Model` via `instanceof`. Database
**constructs** paginators. Both packages are now in the tree; the old "port
Pagination first so wave 6 does not stall" sequencing note is spent.
`tests/Pagination/deferred` is gone (`phpunit.xml` still lists the path).

# Enforcement

The monorepo autoloader will compile an illegal edge. Review is the check.
`AGENTS.md` is the file agents read first; this concept is the longer form.

# Related

- [Package split](package-split.md)
- [Namespace and autoloading](namespace-and-autoloading.md)
- [Overview](/overview.md)

[^agents-md]: Agent guidelines — venusian/framework
[^root-composer]: venusian/framework composer.json (version 0.8.0)
[^default-providers]: Composition-root provider list
[^upward-imports]: Measured imports of Voyager\System from below System
