---
type: Architecture Decision
title: Laravel lineage
description: Venusian ports Laravel's generic non-web surface under Voyager\, from laravel/framework@v12.67.0, because the product is windowed apps and hardware ICs — not a second Laravel HTTP stack.
tags: [lineage, laravel, illuminate, licensing, upstream, port]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-22T21:46:31Z }
verified: { by: agent:framework-auditor, at: 2026-08-22T21:46:31Z }
verification_key: 'agent:framework-auditor@e4450c2d96ec2451305ce21fc13030c7a581a000'
stale_after: 2026-11-22
sources:
  - id: readme
    resource: ../../README.md
    title: Venusian Framework README
    author: human:angel
  - id: subpackage-manifests
    resource: ../../src/Voyager/*/composer.json extra.venusian
    title: Per-package upstream-ref metadata
  - id: src-tree
    resource: every PHP file under ../../src/Voyager
    title: Framework source tree
---

# Decision

Port Laravel's **generic, non-web** components into Venusian as renamed
first-party source.[^readme] Venusian wants Laravel's developer experience for
windowed applications and hardware ICs. Incoming HTTP, Blade mail, Auth, and
View stay out.

Taylor Otwell is a second author on the sub-package manifests that carry
Laravel code; both projects are MIT.[^subpackage-manifests]

# Why port rather than depend on illuminate/*

Depending on `illuminate/*` would import Laravel's release cadence, container
assumptions, and web-shaped extras. Porting under `Voyager\` keeps the
ergonomics and drops the coupling.

# Upstream revision

Most split manifests record:[^subpackage-manifests]

```yaml
extra.venusian:
  upstream: laravel/framework
  upstream-ref: v12.67.0
  upstream-path: src/Illuminate/<Component>
  ported-at: 2026-08-19 | 2026-08-20
```

Root `composer.json` itself does **not** repeat that ref. When a file has no
`extra.venusian` (Contracts, Conditionable, Macroable, MagicAliases,
Reflection, Collections), do not invent a date — only cite `v12.67.0` where
the manifest writes it.

# What maps to what (foundation)

| Venusian | Laravel upstream |
|----------|------------------|
| `Voyager\NutsAndBolts\Collection`, `LazyCollection` | `Illuminate\Support\Collection` / Collections package |
| `Voyager\NutsAndBolts\DataObjects\{Arr,Str,Stringable,Number,Env,Pluralizer,Carbon}` | `Illuminate\Support\*` |
| `Voyager\NutsAndBolts\Concerns\{Macroable,Conditionable,…}` | `Illuminate\Support\Traits\*` |
| `Voyager\Reflection\Reflector` | `Illuminate\Support\Reflector` |
| `Voyager\Vessel\Vessel` | `Illuminate\Container\Container` |
| `Voyager\Database\Instrument\Model` | `Illuminate\Database\Eloquent\Model` |
| `Voyager\System\Application` | `Illuminate\Foundation\Application` |
| `Voyager\MagicAliases\MagicAlias` | `Illuminate\Support\Facades\Facade` |

Helpers, config, and tests follow the same rename. Eloquent is **Instrument**.
Artisan-the-binary is **computer**. Facades are **magic aliases**.

# Consequences

**Upstream is the reference documentation** for methods this tree has not
shadowed. Divergences and cuts belong in [known gaps](/known-gaps.md).

**Upstream fixes do not flow automatically.** There is no subtree sync. The
`extra.venusian.upstream-ref` field is how to tell which Laravel bugfixes are
already in.

**Attribution must survive a package split.** Keep the Taylor Otwell author
entry when rewriting manifests.

**Web-shaped upstream code is cut, not deferred**, when it is incoming-HTTP,
Blade mail, or Auth. Channel `auth()`, `ValidationException` redirects, and
the Notifications mail channel are the worked examples.

# Related

- [Overview](/overview.md)
- [Package split](package-split.md)
- [Port hazards](port-hazards.md)

[^readme]: Venusian Framework README
[^subpackage-manifests]: Per-package upstream-ref metadata
[^src-tree]: Framework source tree
