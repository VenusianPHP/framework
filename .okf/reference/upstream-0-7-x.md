---
type: Reference
title: The 0.7.x reference implementation
description: A working checkout of the previous Fabricate-namespaced generation of the framework, useful as the answer key for rename questions.
resource: /Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework
tags: [reference, upstream, fabricate, history, magicaliases]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: maintainer
    resource: maintainer statement to claude-code on 2026-08-19 pointing at the 0.7.x checkout
    title: Location of the 0.7.x implementation, stated by the maintainer
    author: human:angel
    last_modified: 2026-08-19
  - id: ref-tree
    resource: /Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework/src/Fabricate
    title: 0.7.x source tree (Fabricate namespace)
  - id: subpackage-manifests
    resource: ../../src/Voyager/*/composer.json
    title: 0.8.x sub-package manifests at 8a8600f
---

# What it is

A local checkout of the **0.7.x generation** of this framework. It lives
outside this repo and is not a dependency:[^maintainer]

```
/Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework
```

Its root namespace is **`Fabricate`**, not `Voyager`.[^ref-tree] 0.8.x is a
renamed, reconstituted successor under `VenusianPHP/framework`.

This auditor pass did **not** re-open that checkout. Path and namespace claims
below are historical, from the 2026-08-19 recording.

# Why it matters

It is the **answer key** for questions this repository cannot answer on its own:

- **Was this a deliberate change or a port slip?** Diff against 0.7.x. That
  settled `ReflectsClosures` as a trait (it is a trait again in 0.8.x).
- **What is a forward reference pointing at?** Types that existed there before
  they existed here.
- **What is the intended end shape?** 0.7.x showed layers 0.8.x was
  reconstituting toward. Many of those layers (Vessel, System, Database,
  Contracts) are now in this tree — see [overview](/overview.md).

# `fabricate/*` names are history

0.7.x packages were named `fabricate/*`. Early 0.8.x sub-manifests still
required those names. **At 8a8600f, no `src/Voyager/*/composer.json` requires
`fabricate/*`.**[^subpackage-manifests] One comment in
`MagicAlias.php` still mentions `fabricate/magic-aliases`. Remaining manifest
drift is the `conditionble` typo, `voyager/collection` singular, and
ScrapyardIO URLs — [known gaps](/known-gaps.md).

# MagicAliases are the facade layer

0.7.x held aliases under `Fabricate\Core\MagicAliases\`. 0.8.x now has:

- `Voyager\MagicAliases\MagicAlias` — the base class (`voyager/magic-aliases`)
- Concrete aliases under `src/Voyager/NutsAndBolts/MagicAliases/`
  (`App`, `Broadcast`, `Bus`, `Cache`, `Computer`, `Concurrency`, `Config`,
  `Context`, `Crypt`, `Date`, `DB`, `Event`, `File`, `Hash`, `Http`, `Lang`,
  `Log`, `Notification`, `ParallelTesting`, `Pipeline`, `Process`, `Queue`,
  `Redis`, `Schema`, `Storage`, `Validator`)

`Date` exists. Global `now()` still uses Carbon directly; namespaced and
System `now()` call `Date::now()`.

# Caution

This is a **separate working tree, not a dependency**. Nothing in 0.8.x loads
it. Use it to answer questions, not as a source to copy from unread.

[^maintainer]: Location of the 0.7.x implementation, stated by the maintainer
[^ref-tree]: 0.7.x source tree (Fabricate namespace)
[^subpackage-manifests]: 0.8.x sub-package manifests at 8a8600f
