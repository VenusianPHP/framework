---
type: PHP Package
title: voyager/magic-aliases
description: MagicAlias base class (Laravel facades). Concrete aliases live under NutsAndBolts.
resource: ../../src/Voyager/MagicAliases
tags: [php, package, voyager, facades, aliases]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/MagicAliases
    title: MagicAliases package source (1 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/MagicAliases/composer.json
    title: voyager/magic-aliases composer.json
---

# Overview

The publishable package is one class:
`src/Voyager/MagicAliases/MagicAlias.php`. Concrete aliases sit in
`src/Voyager/NutsAndBolts/MagicAliases/` (`App`, `Broadcast`, `Bus`,
`Cache`, `Computer`, `Concurrency`, `Config`, `Context`, `Crypt`, `Date`,
`DB`, `Event`, `File`, `Hash`, `Http`, `Lang`, `Log`, `Notification`,
`ParallelTesting`, `Pipeline`, `Process`, `Queue`, `Redis`, `Schema`,
`Storage`, `Validator`).

`shouldReceive()` and `expects()` return `Mockery\ExpectationInterface`
(PR 1). That closed the Mockery 1.6.15 `CompositeExpectation` TypeError.

# Related

- [voyager/nuts-and-bolts](nuts-and-bolts.md)
- [The 0.7.x reference](/reference/upstream-0-7-x.md)

[^package-source]: MagicAliases package source (1 PHP files)
[^package-manifest]: voyager/magic-aliases composer.json
