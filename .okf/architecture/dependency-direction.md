---
type: Convention
title: Dependency direction
description: The layering rule governing which Venusian package may depend on which, including why the NutsAndBolts family self-maintains its contracts while voyager/contracts serves everything above it.
tags: [architecture, layering, dependencies, packaging, contracts, rules]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T19:20:00Z }
sources:
  - id: maintainer
    resource: maintainer statements to claude-code on 2026-08-19 clarifying the contracts split
    title: Contracts and family boundary, stated by the maintainer
    author: human:angel
    last_modified: 2026-08-19
  - id: agents-md
    resource: ../../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
  - id: import-graph
    resource: every `use Voyager\...` statement under ../../src/Voyager
    title: Measured cross-package import graph
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json (version 0.8.0)
    author: human:angel
    last_modified: 2026-08-19
---

# The rule

From `AGENTS.md`:[^agents-md]

- **System** may be aware of everything.
- **Nothing below System depends on System.**
- **NutsAndBolts** may depend on its sibling packages (Collections,
  Conditionable, Macroable, Reflection, …) **and `voyager/contracts`** — not
  other components.
- **Other components** may depend on NutsAndBolts. Peer dependencies may depend
  on other peer dependencies when justified (the cited example is
  Broadcasting ↔ Filesystem for `.env` install writes). Never System.

With one clarification from the maintainer: the NutsAndBolts packages **may
depend on each other freely**, in either direction, because they form a single
cohesive family.[^maintainer]

# Two kinds of contracts

This is the load-bearing distinction.

**The NutsAndBolts family maintains its own contracts.** `Arrayable`, `Jsonable`,
`Enumerable`, and `CanBeEscapedWhenCastToString` belong to the foundation and
stay inside it. They describe how the foundation's own types talk to each other,
so the foundation should not have to reach outside itself to name
them.[^maintainer]

**`voyager/contracts` is for everything else** — the framework-wide interface
package, in the same role `illuminate/contracts` plays for
Laravel.[^maintainer] System and the components above it speak through it. It is
declared in the root manifest's `replace` block but has no directory yet, because
the layers that need it have not been written.[^root-composer]

The payoff is that the foundation stays genuinely standalone: someone taking
`voyager/collections` alone gets its interfaces with it, and never pulls in a
framework-wide contracts package to use a `Collection`.

`AGENTS.md` additionally *permits* NutsAndBolts to depend on
`voyager/contracts`.[^agents-md] That stays available for the case where the
foundation must speak a framework-wide interface — it is an allowance, not a
requirement, and nothing in the tree uses it today.

# The layers

```
System                     aware of everything; nothing depends on it
   ^
Components                 Broadcasting, Filesystem, … — none written yet
   ^                       (depend on NutsAndBolts and on voyager/contracts;
   |                        peer-to-peer when justified; never on System)
   |
voyager/contracts          framework-wide interfaces, like illuminate/contracts
                           declared in `replace`; no directory yet

NutsAndBolts family        NutsAndBolts, Collections, Conditionable,
                           Macroable, Reflection
                           free internal dependency in any direction;
                           carries its own contracts
```

The family boundary is already encoded in the namespace: every declaration in
these five packages sits under `Voyager\NutsAndBolts\` — the directory it lives
in is a packaging detail.[^import-graph] See
[namespace and autoloading](namespace-and-autoloading.md).

`Voyager\Reflection\Reflector` is the sole exception, the one class outside the
family namespace.[^import-graph] Worth a decision: either it moves under
`Voyager\NutsAndBolts\` with its siblings, or its namespace is signalling that
Reflection is intended to be something other than a family member.

# Measured edges

Every cross-package `use Voyager\…` import in the tree:[^import-graph]

| From | To | Verdict |
|------|-----|---------|
| NutsAndBolts | Collections (`Collection`) | Intra-family |
| NutsAndBolts | Conditionable (`Concerns\Conditionable`) | Intra-family |
| NutsAndBolts | Macroable (`Concerns\Macroable`) | Intra-family |
| Collections | Macroable (`Concerns\Macroable`) | Intra-family |
| Collections | NutsAndBolts (`Contracts\Arrayable`, `Contracts\Jsonable`) | Intra-family — foundation contracts, correctly kept in the family |
| Collections | NutsAndBolts (`DataObjects\Carbon`) | Intra-family; also `class_exists`-guarded with a `time()` fallback |
| Collections | NutsAndBolts (`DataObjects\Stringable`) | Intra-family — but the site is a bug for unrelated reasons, see [known gaps](/known-gaps.md) |
| Reflection | Collections (`Collection`) | Intra-family |
| Conditionable | — | No cross-package imports at all |
| Macroable | — | No cross-package imports at all |

**No edge in the current tree violates the rule.** Conditionable and Macroable
are clean leaves; everything else is family-internal. The rule has real teeth
only once components and System exist, which is when the `never depends on
System` and `never skips to another component` constraints start doing work.

> The table above was measured during wave 0, when the tree held only the
> NutsAndBolts family. It does not include Config, Pipeline, Encryption,
> Hashing, JsonSchema, System, MagicAliases, Console, Log, Filesystem, Process,
> Redis, Cache or Pagination. Re-measuring it is worth a pass of its own.

# Direction decides port order

Which way an edge points is what determines when a component can be ported, and
it is easy to read backwards. Pagination is the worked example.

`Voyager\Pagination` names `Database\Instrument\Model` and
`Database\Instrument\Relations\Pivot` — so it looks like it depends on Database,
and Database is wave 6 while Pagination is wave 3. Counting the edges settles
it:

| Direction | References |
|---|---|
| Database → Pagination | 26, across `Query/Builder`, `Instrument/Builder`, `Concerns/BuildsQueries`, `Relations/BelongsToMany`, `Relations/HasOneOrManyThrough` |
| Pagination → Database | 2, both `instanceof` |

Database **constructs** paginators — `BuildsQueries::paginator()` resolves
`LengthAwarePaginator` out of the container. Pagination only asks whether an
item it was handed happens to be a Model or a Pivot, and `instanceof` against a
class that does not exist returns `false` without error. So all three paginators
construct, page and serialize today with Database absent.

Pagination is therefore a **prerequisite** of Database, not a dependent, and
porting it early is what keeps wave 6 from stalling. The cost is that it is
dormant until then: nothing constructs a paginator, so a caller has to build one
by hand, and the four `loadMorph` tests are parked.

The general form: **count the edges in both directions before trusting the
`use` statements in the file you happen to be reading.** A component that is
merely name-dropped by another is not downstream of it.

# Enforcement

Nothing enforces this today. The monorepo autoloader resolves every class
regardless of which directory it lives in, so an illegal edge would compile and
run exactly like a legal one — see [package split](package-split.md). The rule
is maintained by review, and the table above is the current audit.

`AGENTS.md` does not yet state the intra-family clarification or the two-kinds-of
-contracts split; both are recorded here from maintainer statements. Since
`AGENTS.md` is the file agents actually read first, folding them in there would
make the rule enforceable rather than discoverable.

# Related

- [Package split](package-split.md) — how packages are declared and released.
- [Namespace and autoloading](namespace-and-autoloading.md) — the family namespace.
- [Overview](/overview.md) — where the component layers are headed.

[^maintainer]: Contracts and family boundary, stated by the maintainer
[^agents-md]: Agent guidelines — venusian/framework
[^import-graph]: Measured cross-package import graph
[^root-composer]: venusian/framework composer.json (version 0.8.0)
