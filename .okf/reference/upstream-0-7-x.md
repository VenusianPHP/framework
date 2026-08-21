---
type: Reference
title: The 0.7.x reference implementation
description: A working checkout of the previous Fabricate-namespaced generation of the framework, useful as the answer key for port questions.
resource: /Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework
tags: [reference, upstream, fabricate, history, magicaliases]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T20:00:00Z }
sources:
  - id: maintainer
    resource: maintainer statement to claude-code on 2026-08-19 pointing at the 0.7.x checkout
    title: Location of the 0.7.x implementation, stated by the maintainer
    author: human:angel
    last_modified: 2026-08-19
  - id: ref-tree
    resource: /Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework/src/Fabricate
    title: 0.7.x source tree (Fabricate namespace)
---

# What it is

A local checkout of the **0.7.x generation** of this framework, which is
considerably more built out than the 0.8.x tree in this
repository.[^maintainer] It lives outside this repo and is not a dependency:

```
/Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework
```

Its root namespace is **`Fabricate`**, not `Voyager`.[^ref-tree] 0.8.x is a
renamed, reconstituted successor — see [overview](/overview.md).

# Why it matters

It is the **answer key** for questions this repository cannot answer on its own:

- **Was this a deliberate change or a port slip?** Diff the declaration against
  0.7.x. This settled `ReflectsClosures`, which is a `trait` in 0.7.x and had
  become a `class` here — a regression, not a redesign. See
  [known gaps](/known-gaps.md).
- **What is a forward reference pointing at?** Code in 0.8.x refers to types
  that do not exist here yet but do exist there.
- **What is the intended end shape?** The 0.7.x tree shows which layers the
  framework is being reconstituted toward.

# `Fabricate` explains the stale dependency names

Four sub-package manifests in 0.8.x require `fabricate/macroable` and
`fabricate/collection`.[^ref-tree] Those are **not typos** — they are the real
0.7.x package names, left behind by the rename to `voyager/*`. Recorded in
[known gaps](/known-gaps.md).

# MagicAliases are the facade layer

`src/Fabricate/Core/MagicAliases/` holds 21 aliases — `App`, `Cache`, `Config`,
`DB`, `Date`, `Event`, `Http`, `Log`, `Queue`, `Storage`, `Validator`,
`Workshop`, and others — each extending a `MagicAlias` base in
`src/Fabricate/MagicAliases/`.[^ref-tree] They are Laravel's facades under a
different name: each declares a string accessor and resolves it out of a
container.

```php
class Date extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'date';
    }
}
```

This is why `Voyager\NutsAndBolts\MagicAliases\Date` cannot simply be recreated
in 0.8.x: a facade needs the container binding it fronts, and the container is a
System-layer concern that does not exist yet. See
[dependency direction](/architecture/dependency-direction.md).

The `now()` helper in 0.8.x referred to that `Date` alias and therefore fatalled;
it is now wired directly to `Carbon` as an interim measure, with the seam noted
in the source. See [global helpers](/api/global-helpers.md).

# Caution

This is a **separate working tree, not a dependency**. Nothing in 0.8.x loads it,
its namespace differs, and it may itself be mid-refactor. Use it to answer
questions, not as a source to copy from unread.

[^maintainer]: Location of the 0.7.x implementation, stated by the maintainer
[^ref-tree]: 0.7.x source tree (Fabricate namespace)
