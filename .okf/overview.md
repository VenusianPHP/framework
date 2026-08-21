---
type: Framework
title: Venusian Framework
description: A Laravel-like PHP framework targeting sketch-based CLI workflows, currently at the foundation stage with Laravel's Support components ported in.
resource: https://github.com/ScrapyardIO/framework
tags: [php, framework, cli, sketches, monorepo, voyager, venusian]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T21:00:00Z }
stale_after: 2026-11-19
sources:
  - id: maintainer
    resource: maintainer statement to claude-code on 2026-08-19 describing intent and scope
    title: Framework intent, stated by the maintainer
    author: human:angel
    last_modified: 2026-08-19
  - id: agents-md
    resource: ../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
  - id: root-composer
    resource: ../composer.json
    title: venusian/framework composer.json (version 0.8.0)
    author: human:angel
    last_modified: 2026-08-19
  - id: src-tree
    resource: every PHP file under ../src/Voyager (26 declarations, 13,157 lines)
    title: Framework source tree
  - id: smoke-test
    resource: runtime reflection and smoke test executed against ../vendor/autoload.php
    title: Autoload and runtime verification run
---

# What Venusian is

Venusian is a **Laravel-like PHP framework for sketch-based workflows in the
CLI**. It borrows Laravel's developer experience — expressive collections,
fluent strings, macroable extension points, global helpers — but its runtime
target is the command line and the sketch, not the HTTP request/response
cycle.[^maintainer]

That inversion is the whole point. Laravel's shape is worth keeping; Laravel's
web-first assumptions are not what this framework is being built around.

# Current stage: the Support foundation

v0.8.0 is the **foundation layer**, not a partial framework. The Support
components have been deliberately ported over from Laravel first, because
everything above them depends on them.[^maintainer] What is in the tree today:
collections, strings, numbers, dates, environment access, macros, and
reflection — 26 declarations across 13,157 lines.[^src-tree]

The CLI and sketch layers are not in this repository yet. Their absence is
sequencing, not omission: the layer under them was the thing to land first.
`AGENTS.md` describes the current phase as **reconstituting**.[^agents-md]

The target shape is already fixed by the dependency rule, which names layers
that have no code yet — a **System** layer aware of everything, and
**components** such as Broadcasting and Filesystem sitting between System and
the foundation.[^agents-md] Read
[dependency direction](/architecture/dependency-direction.md) before adding
anything: it decides where new code is allowed to go.

# What ships today

| Publishable package | Directory | Primary contents |
|---------------------|-----------|------------------|
| [`voyager/collections`](/packages/collections.md) | `src/Voyager/Collections/` | `Collection`, `LazyCollection`, `Arr`, `Enumerable` |
| [`voyager/nuts-and-bolts`](/packages/nuts-and-bolts.md) | `src/Voyager/NutsAndBolts/` | `Str`, `Stringable`, `Number`, `Env`, `Carbon`, `Pluralizer` |
| [`voyager/macroable`](/packages/macroable.md) | `src/Voyager/Macroable/` | `Macroable` trait |
| [`voyager/conditionable`](/packages/conditionable.md) | `src/Voyager/Conditionable/` | `Conditionable` trait, `HigherOrderWhenProxy` |
| [`voyager/reflection`](/packages/reflection.md) | `src/Voyager/Reflection/` | `Reflector`, `ReflectsClosures` |

The composer package is named `venusian/framework`, but every class ships under
the `Voyager\` root namespace and every publishable sub-package is named
`voyager/*`.[^root-composer] Treat **Venusian** as the framework and **Voyager**
as the code namespace for its foundation packages; see
[package split](/architecture/package-split.md).

The root manifest's `replace` block also claims a sixth package,
`voyager/contracts`, which has no corresponding directory yet.[^root-composer] It
is the framework-wide interface package for the layers above — Venusian's
`illuminate/contracts`. The foundation keeps its own interfaces (`Arrayable`,
`Jsonable`, `Enumerable`, `CanBeEscapedWhenCastToString`) inside the family by
design, so it stays standalone; see
[dependency direction](/architecture/dependency-direction.md).

# Requirements

- PHP `^8.4|^8.5`.[^root-composer]
- The `intl` extension for [`Number`](/packages/nuts-and-bolts.md) — its methods
  call `ensureIntlExtensionIsInstalled()` and throw `RuntimeException` without it.[^src-tree]
- Runtime dependencies: `ramsey/uuid`, `symfony/uid`, `nesbot/carbon`,
  `vlucas/phpdotenv`, `league/commonmark`, `symfony/var-dumper`,
  `voku/portable-ascii`, `symfony/polyfill-php86`.[^root-composer]
- Dev: `pestphp/pest ^4` (installed at 4.7.8).[^smoke-test]

`symfony/var-dumper` and `league/commonmark` are already in the runtime
requirements — both point toward terminal-facing output rather than web
rendering.[^root-composer]

# Where the Laravel code came from

The Support layer is a namespace-rename port of `illuminate/support` and
`illuminate/collections`. Taylor Otwell is credited as a co-author in four of
the five sub-package manifests, and everything is MIT on both
sides.[^root-composer] See [Laravel lineage](/architecture/laravel-lineage.md)
for the mapping table and for what the port implies about tracking upstream.

# State of the repository

Under git as of 2026-08-19, with the pre-repair tree as the first commit so the
fixes read as a diff. A Pest suite of 184 tests covers the foundation, and
`.github/workflows/tests.yml` runs it on PHP 8.4 and 8.5. `README.md` and
`AGENTS.md` are both written. `config/` is still an empty directory. See
[local development](/playbooks/local-development.md).

Runtime-verified defects in the ported code are recorded in
[known gaps](/known-gaps.md). Six were found and fixed on 2026-08-19 — a
`Stringable` name-resolution bug that silently broke `implode()`, `groupBy()` and
`where()`; thirteen `Str` signatures that silently coerced `Collection`
arguments; a `LazyCollection::make()` that rejected its own constructor's
`Closure` form; a missing `Str::singular()`; a `now()` helper that fatalled; and
`ReflectsClosures` declared as a class instead of a trait.

Most shared one root cause — type hints added to Laravel code written for
untyped parameters, where PHP coerces rather than rejects. That pattern is worth
knowing before touching more ported code:
[port hazards](/architecture/port-hazards.md).

Still open and worth a decision: the components listed in
[known gaps](/known-gaps.md).

A [0.7.x reference implementation](/reference/upstream-0-7-x.md) of the framework
exists outside this repo under the `Fabricate` namespace; it is the answer key for
"was this deliberate or a port slip?".

`AGENTS.md` is the contract for working in this repository — it requires reading
this bundle before changing framework code, and it governs how the bundle itself
is maintained.[^agents-md] See
[maintaining this knowledge bundle](/playbooks/maintaining-this-bundle.md).

[^maintainer]: Framework intent, stated by the maintainer
[^agents-md]: Agent guidelines — venusian/framework
[^root-composer]: venusian/framework composer.json (version 0.8.0)
[^src-tree]: Framework source tree
[^smoke-test]: Autoload and runtime verification run
