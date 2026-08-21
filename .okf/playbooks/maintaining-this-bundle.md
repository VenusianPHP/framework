---
type: Playbook
title: Maintaining this knowledge bundle
description: The repository's own rules for reading, extending, and verifying the .okf bundle, as set out in AGENTS.md.
tags: [okf, documentation, conventions, agents]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T19:00:00Z }
sources:
  - id: agents-md
    resource: ../../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
  - id: gitattributes
    resource: ../../.gitattributes
    title: Repository export-ignore rules
---

# Reading order

`AGENTS.md` requires this before changing framework code or advising on Venusian
architecture for this package:[^agents-md]

1. Read [`index.md`](/index.md) first — progressive disclosure.
2. Open only the linked concepts the task actually needs.
3. Prefer `status: stable` concepts; treat `deprecated` as historical only.

# Where the bundle lives

**One bundle, at the package root.** `AGENTS.md` explicitly forbids creating
`.okf/` folders under `src/Voyager/*` — knowledge for this package lives at the
package root only.[^agents-md] Per-component bundles would fragment exactly the
cross-package view that [dependency direction](/architecture/dependency-direction.md)
depends on.

The bundle is `export-ignore`d in `.gitattributes`, so it travels with the git
repository but not with a Composer dist tarball.[^gitattributes]

# Writing changes back

When you learn something durable about this package:[^agents-md]

1. Update the affected concept(s).
2. Append a dated entry to [`log.md`](/log.md), newest first.
3. Refresh the `index.md` of any directory whose contents changed.
4. Leave new or changed concepts at **`status: draft`** — they stay draft until
   a human verifies them.
5. Set `generated: { by: <your actor>, at: <ISO 8601> }` on anything you touch.

# Verification

`AGENTS.md` states that concepts in this bundle are human-verified `stable`
unless marked `deprecated`.[^agents-md] That is the target state, not the
current one: **every concept here is currently `status: draft` with no
`verified` entry**, because the bundle was agent-generated on 2026-08-19 and
nobody has signed off yet.

To verify a concept, confirm its claims against the tree, then add:

```yaml
status: stable
verified: { by: human:<id>, at: <ISO 8601> }
```

Trust tiers key off the `human:` prefix, so use it whenever a person signs off.
Until then, a consumer following rule 3 above should treat these concepts as
unconfirmed.

# Related

- [Local development](local-development.md) — working on the code itself.
- [Overview](/overview.md) — the entry point `README.md` does not yet provide.

[^agents-md]: Agent guidelines — venusian/framework
[^gitattributes]: Repository export-ignore rules
