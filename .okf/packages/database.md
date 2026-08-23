---
type: PHP Package
title: voyager/database
description: Query builder, schema, migrations, and Instrument (Eloquent rename). Landed.
resource: ../../src/Voyager/Database
tags: [php, package, voyager, database, instrument, query]
status: draft
generated: { by: agent:cursor, at: 2026-08-22T21:30:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Database
    title: Database package source (230 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Database/composer.json
    title: voyager/database composer.json
---

# Overview

**230** PHP files under `src/Voyager/Database/`. This component **has
landed**. Manifest `extra.venusian`: `laravel/framework@v12.67.0`
`src/Illuminate/Database`, ported 2026-08-20.[^package-manifest]

Present: `Capsule\Manager`, `Instrument\Model`, query builder, schema,
migrations. Requires `ext-pdo`. `DatabaseServiceProvider` and
`MigrationServiceProvider` are in `DefaultProviders`.

`tests/Database/` holds 119 `*Test.php` files. They are Pest v4 closures
in the default suite (no `tests/Database/deferred/` test files; that
directory holds only a cut-tests README).

# Related

- [voyager/pagination](pagination.md)
- [voyager/graph](graph.md)
- [Known gaps](/known-gaps.md)

[^package-source]: Database package source (230 PHP files)
[^package-manifest]: voyager/database composer.json
