---
type: Architecture Decision
title: Monorepo with composer replace
description: Venusian develops five voyager/* packages in one tree and declares them all in the root manifest's replace block.
tags: [monorepo, composer, packaging]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T19:20:00Z }
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json (version 0.8.0)
    author: human:angel
    last_modified: 2026-08-19
  - id: subpackage-manifests
    resource: ../../src/Voyager/*/composer.json
    title: Per-package composer manifests
  - id: gitattributes
    resource: ../../.gitattributes
    title: Repository export-ignore rules
  - id: agents-md
    resource: ../../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
---

# Decision

Develop every `voyager/*` package inside one repository, and have the root
`venusian/framework` package declare `replace` for all of them.[^root-composer]
An application requiring `venusian/framework` gets the whole surface; an
application requiring only `voyager/collections` can take that package alone
once it is split out.

# Replace block

```
voyager/collections      -> src/Voyager/Collections/
voyager/conditionable    -> src/Voyager/Conditionable/
voyager/contracts        -> (no directory)
voyager/macroable        -> src/Voyager/Macroable/
voyager/reflection       -> src/Voyager/Reflection/
voyager/nuts-and-bolts   -> src/Voyager/NutsAndBolts/
```

All six are pinned to `self.version`, so a `venusian/framework` release version
is simultaneously the version of every constituent package.[^root-composer]

`voyager/contracts` is the one entry with no directory. The four contract
interfaces live inside the packages that use them —
`Voyager\NutsAndBolts\Contracts\{Arrayable,Jsonable}` under `NutsAndBolts/` and
`Voyager\NutsAndBolts\Contracts\{Enumerable,CanBeEscapedWhenCastToString}` under
`Collections/`.

It is declared because it is **planned**: `voyager/contracts` is the
framework-wide interface package for System and the components, playing the role
`illuminate/contracts` plays in Laravel.[^agents-md] The four interfaces above
are the *foundation's* own contracts and deliberately stay where they are — see
[dependency direction](dependency-direction.md).

# System is not a split package

`Voyager\System` is the one component that ships **no** sub-package
`composer.json`, `LICENSE` or `.gitattributes`, and that is correct rather than
an oversight.

It mirrors `Illuminate\Foundation`, which is the single exception upstream: of
laravel/framework's 37 components, 36 carry their own `composer.json` and are
published as read-only split packages, and `Foundation` carries none and is
absent from the framework's `replace` block. Foundation is the application
skeleton that wires the components together, not a component you can consume on
its own, so there is nothing to split out. Upstream autoloads it anyway through
the wholesale `Illuminate\ -> src/Illuminate/` PSR-4 entry.

The porting recipe's "mirror `src/Voyager/Collections/`" instruction therefore
does **not** apply to System. Applying it uniformly is a real trap — the files
look missing when they are deliberately absent.

Open inconsistency: the root `composer.json` still lists
`"voyager/system": "self.version"` in `replace`. Upstream has no
`illuminate/foundation` entry, so this one should go.

# Consequences

**The monorepo hides split-time breakage.** The root autoloader resolves every
class regardless of which directory it lives in, so a wrong dependency
declaration in a sub-package manifest never surfaces during monorepo
development. Each sub-manifest currently has at least one such error — see the
drift table in [known gaps](/known-gaps.md).[^subpackage-manifests]

**Dependency direction is a rule, but nothing enforces it.** `AGENTS.md` sets
out which package may depend on which.[^agents-md] The five packages here form
one family that may inter-depend freely; the rule bites at the boundary with
components and System, neither of which exists yet. Under one autoloader a legal
edge and an illegal one compile identically, so the rule survives on review
alone. The audit lives in [dependency direction](dependency-direction.md).

**The `.okf/` bundle is not shipped.** `.gitattributes` export-ignores
`/tests`, `/phpunit.xml`, `/.github`, `/AGENTS.md`, and `/.okf`, so none of it
lands in a `composer` dist tarball.[^gitattributes]

# Related

- [Namespace and autoloading](namespace-and-autoloading.md) — how the split maps onto PSR-4.
- [Laravel lineage](laravel-lineage.md) — why the package boundaries look the way they do.

[^root-composer]: venusian/framework composer.json (version 0.8.0)
[^subpackage-manifests]: Per-package composer manifests
[^gitattributes]: Repository export-ignore rules
