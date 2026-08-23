---
okf_version: '0.2'
generated: { by: agent:framework-auditor, at: 2026-08-23T00:03:43Z }
verified: { by: agent:framework-auditor, at: 2026-08-23T00:03:43Z }
verification_key: 'agent:framework-auditor@4e2910dd3783ff661dea9def23d1059bbb91b400'
---

# Venusian Framework

Knowledge bundle for `venusian/framework` **0.8.0** — a Laravel-like PHP
framework for windowed applications and hardware integrated circuits. The
generic (non-web) Laravel surface has been ported into this monorepo under the
`Voyager\` namespace.

* [Overview](overview.md) - product intent, current 0.8.x tree, and what ships today.
* [Known gaps](known-gaps.md) - remaining defects, deliberate cuts, and retired stale claims.

# Architecture

* [Architecture](architecture/) - dependency direction, monorepo packaging, namespace scheme, Laravel lineage, and port hazards.

# Packages

* [Packages](packages/) - one concept per publishable `voyager/*` package (34). `Voyager\System` is the composition root and is not a split package.

# API

* [API surface](api/) - the global and namespaced functions the framework injects.

# Playbooks

* [Playbooks](playbooks/) - operational procedures for working on the framework and this bundle.

# Reference

* [Reference](reference/) - external material this bundle points at, including the 0.7.x Fabricate tree.
