---
type: Architecture Decision
title: Monorepo with composer replace
description: Venusian develops 32 voyager/* packages in one tree and lists them in the root replace block. Voyager\System is the skeleton and is not a split package.
tags: [monorepo, composer, packaging]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-22T21:46:31Z }
verified: { by: agent:framework-auditor, at: 2026-08-22T21:46:31Z }
verification_key: 'agent:framework-auditor@e4450c2d96ec2451305ce21fc13030c7a581a000'
stale_after: 2026-11-22
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json (version 0.8.0)
  - id: subpackage-manifests
    resource: ../../src/Voyager/*/composer.json
    title: Per-package composer manifests (32 files)
  - id: gitattributes
    resource: ../../.gitattributes
    title: Repository export-ignore rules
  - id: agents-md
    resource: ../../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
---

# Decision

Develop every `voyager/*` package inside one repository, and have the root
`venusian/framework` package declare `replace` for all of them.[^root-composer]
An application requiring `venusian/framework` gets the whole surface; an
application requiring only `voyager/collections` can take that package alone
once it is split out.

# Replace block

Root `composer.json` lists **32** packages, each `"self.version"`:[^root-composer]

`voyager/broadcasting`, `bus`, `cache`, `collections`, `concurrency`,
`conditionable`, `config`, `console`, `contracts`, `database`, `encryption`,
`events`, `filesystem`, `hashing`, `http`, `json-schema`, `log`, `macroable`,
`magic-aliases`, `notifications`, `nuts-and-bolts`, `pagination`, `pipeline`,
`process`, `queue`, `redis`, `reflection`, `testing`, `translation`,
`validation`, `vessel`, `workflows`.

Each of those 32 directories has a `composer.json`.[^subpackage-manifests]
`src/Voyager/Contracts/` exists (120 PHP files) — it is not an empty
`replace` stub.

# System is not a split package

`Voyager\System` (112 PHP files) ships **no** `composer.json`, `LICENSE`, or
`.gitattributes`, and it is **absent** from `replace`. That matches
`Illuminate\Foundation`: the application skeleton, not a consumable split
package. Autoload still reaches it through the root `"Voyager\\": "src/Voyager"`
prefix and `src/Voyager/System/helpers.php`.

# Consequences

**The monorepo hides split-time breakage.** The root autoloader resolves every
class regardless of directory, so a wrong sub-manifest never fails locally.
Remaining drift is in [known gaps](/known-gaps.md) — a `conditionble` typo, a
`voyager/collection` singular pin, a wider NutsAndBolts PHP constraint, and
ScrapyardIO URLs on Macroable. The old `fabricate/*` requires are gone.

**Dependency direction is a rule, not a compiler.** See
[dependency direction](dependency-direction.md).

**The `.okf/` bundle is not shipped.** `.gitattributes` export-ignores
`/tests`, `/phpunit.xml`, `/.github`, `/AGENTS.md`, `/.okf`, `/bootstrap`,
`/storage`, and `/config-stubs`.[^gitattributes] `/config` is **not**
export-ignored and holds ten published config files.

# Related

- [Namespace and autoloading](namespace-and-autoloading.md)
- [Laravel lineage](laravel-lineage.md)
- [Packages](/packages/)

[^root-composer]: venusian/framework composer.json (version 0.8.0)
[^subpackage-manifests]: Per-package composer manifests
[^gitattributes]: Repository export-ignore rules
