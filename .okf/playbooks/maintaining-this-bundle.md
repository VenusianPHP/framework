---
type: Playbook
title: Maintaining this knowledge bundle
description: The repository's own rules for reading, extending, and verifying the .okf bundle, as set out in AGENTS.md and OKF 0.2.
tags: [okf, documentation, conventions, agents]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: agents-md
    resource: ../../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
  - id: gitattributes
    resource: ../../.gitattributes
    title: Repository export-ignore rules
  - id: okf-02
    resource: https://github.com/googlecloudplatform/knowledge-catalog/blob/main/okf/SPEC.md
    title: Open Knowledge Format 0.2 specification
---

# Reading order

`AGENTS.md` requires this before changing framework code or advising on Venusian
architecture for this package:[^agents-md]

1. Read [`index.md`](/index.md) first — progressive disclosure.
2. Open only the linked concepts the task actually needs.
3. Prefer `status: stable` concepts; treat `deprecated` as historical only.
   Agent-verified concepts stay `draft` until a human signs them (see below).

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
6. If you confirmed the claims against the tree, also write the verification
   metadata below. That logs the audit; it does **not** promote `status` to
   `stable`.

# Verification

OKF 0.2 records confirmation in `verified: { by:, at: }`. Trust tiers are
derived from the actor prefix:[^okf-02]

- no `verified` key → unverified
- only non-`human:` actors → **machine-confirmed**
- any `human:` actor → **human-reviewed**

`AGENTS.md` says concepts stay draft until a **human** verifies them, and that
`status: stable` means a human signed off.[^agents-md] An agent audit therefore
writes `verified` with an `agent:` (or `process:`) actor and **keeps
`status: draft`**. Do not set `status: stable` from an agent pass.

This repo additionally records a citeable audit token, because OKF 0.2 has no
reserved `verification_key` field and Angel asked that each verification pass
leave one in the metadata:

```yaml
status: draft
generated: { by: agent:<id>, at: <ISO 8601> }
verified: { by: agent:<id>, at: <ISO 8601> }
verification_key: '<actor>@<git-sha>'
```

A later human sign-off looks like:

```yaml
status: stable
verified:
  - { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
  - { by: human:angel, at: <ISO 8601> }
```

Use `human:` only for a person. Use `agent:` or `process:` for machine checks.

The 2026-08-21 Framework Auditor pass used:

```
verification_key: agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758
```

That SHA is `0.8.x` HEAD after PR 1 (`VenusianPHP/framework#1`).

# Related

- [Local development](local-development.md) — working on the code itself.
- [Overview](/overview.md) — what ships on 0.8.x today.

[^agents-md]: Agent guidelines — venusian/framework
[^gitattributes]: Repository export-ignore rules
[^okf-02]: Open Knowledge Format 0.2 specification
