---
type: Architecture Decision
title: Laravel lineage
description: Venusian's foundation is a deliberate namespace-rename port of illuminate/support and illuminate/collections, taken first because everything above it depends on it.
tags: [lineage, laravel, illuminate, licensing, upstream, port]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T20:00:00Z }
sources:
  - id: maintainer
    resource: maintainer statement to claude-code on 2026-08-19 describing intent and scope
    title: Framework intent, stated by the maintainer
    author: human:angel
    last_modified: 2026-08-19
  - id: subpackage-manifests
    resource: ../../src/Voyager/*/composer.json
    title: Per-package composer manifests
  - id: src-tree
    resource: every PHP file under ../../src/Voyager
    title: Framework source tree
  - id: illuminate-collections
    resource: https://github.com/laravel/framework/tree/master/src/Illuminate/Collections
    title: illuminate/collections upstream
    author: human:taylorotwell
  - id: illuminate-support
    resource: https://github.com/laravel/framework/tree/master/src/Illuminate/Support
    title: illuminate/support upstream
    author: human:taylorotwell
---

# Decision

Port Laravel's Support components into Venusian as renamed first-party source,
and do it **before** any of the CLI or sketch layers.[^maintainer] Venusian aims
at a Laravel-like developer experience for
[sketch-based CLI workflows](/overview.md); the Support layer is the part of
Laravel that is genuinely runtime-agnostic, so it is both the most reusable
piece and the one everything above it needs first.

Nearly all of `src/Voyager` is the result. Taylor Otwell is credited as a second
author in four of the five sub-package manifests; both projects are
MIT.[^subpackage-manifests]

# Why port rather than depend on illuminate/support

The Support packages are the runtime-agnostic slice of Laravel, but depending on
them directly would pull Laravel's release cadence, version constraints, and
container assumptions into a framework whose whole premise is a different
runtime target. Porting under the `Voyager\` namespace keeps the ergonomics and
drops the coupling — at the cost of owning the maintenance, discussed below.

# What maps to what

| Venusian | Laravel upstream |
|----------|------------------|
| `Voyager\NutsAndBolts\Collection`, `LazyCollection`, `Contracts\Enumerable` | `Illuminate\Support\Collection`, `LazyCollection`, `Illuminate\Support\Enumerable`[^illuminate-collections] |
| `Voyager\NutsAndBolts\Concerns\EnumeratesValues` | `Illuminate\Collections\Traits\EnumeratesValues`[^illuminate-collections] |
| `Voyager\NutsAndBolts\DataObjects\Arr` | `Illuminate\Support\Arr`[^illuminate-collections] |
| `Voyager\NutsAndBolts\DataObjects\{Str,Stringable,Number,Env,Pluralizer,Carbon}` | `Illuminate\Support\*`[^illuminate-support] |
| `Voyager\NutsAndBolts\Concerns\{Macroable,Conditionable,Tappable,Dumpable}` | `Illuminate\Support\Traits\*`[^illuminate-support] |
| `Voyager\Reflection\Reflector` | `Illuminate\Support\Reflector`[^illuminate-support] |
| `collect()`, `data_get()`, `value()`, `tap()`, `env()`, … | `Illuminate\Support\helpers.php`[^illuminate-support] |

# Consequences

**Upstream is the reference documentation.** Any Laravel `Collection`, `Str`, or
`Arr` method not shadowed by a local edit behaves as documented at
laravel.com/docs. `Collection` exposes 183 public methods and `Enumerable`
declares 148; `Str` has 114 and `Stringable` 148.[^src-tree] None of that is
documented in-repo, and it does not need to be — but the divergences do.

**Divergences are the thing worth recording.** Three are visible so far:

- `Reflector` sits in its own `Voyager\Reflection` namespace rather than
  alongside the other support classes.
- `ReflectsClosures` is declared a `class` where upstream declares a `trait` —
  see [known gaps](/known-gaps.md).
- `TransformsToResourceCollection` is a Venusian addition with an empty body and
  no Laravel counterpart — a placeholder for a layer not yet written.[^src-tree]
- The port added PHP type hints to signatures Laravel leaves untyped. Where a hint
  is narrower than the body, arguments are silently coerced instead of rejected —
  a systemic hazard with its own concept, [port hazards](port-hazards.md).

**Upstream fixes do not flow automatically.** There is no vendored copy, no
subtree, and no sync script — the port is a one-time rename. Laravel bugfixes
and new methods must be ported by hand, and **there is no record of which
upstream revision the current code corresponds to**. Capturing that revision
remains the single highest-value addition to this bundle.

The [0.7.x reference implementation](/reference/upstream-0-7-x.md) covers the
adjacent question — was a given difference deliberate, or a slip in the
`Fabricate` → `Voyager` rename? It settled `ReflectsClosures` that way. It does
not answer which *Laravel* revision anything came from.

**Attribution must survive a package split.** The Taylor Otwell author entry is
in the sub-package manifests, not the root one.[^subpackage-manifests] Rewriting
those manifests (which [known gaps](/known-gaps.md) recommends for other
reasons) must preserve it.

**Web-shaped upstream code needs review before reuse, not after.** The port took
the runtime-agnostic slice, but Laravel's Support still carries web-era
assumptions in places — HTML escaping on `CanBeEscapedWhenCastToString`, the
resource-collection hook — that a CLI framework may want to drop rather than
carry forward.

# Related

- [Overview](/overview.md) — what Venusian is aiming at.
- [Package split](package-split.md) — how the ported code is packaged.
- [voyager/collections](/packages/collections.md), [voyager/nuts-and-bolts](/packages/nuts-and-bolts.md)

[^maintainer]: Framework intent, stated by the maintainer
[^subpackage-manifests]: Per-package composer manifests
[^src-tree]: Framework source tree
[^illuminate-collections]: illuminate/collections upstream
[^illuminate-support]: illuminate/support upstream
