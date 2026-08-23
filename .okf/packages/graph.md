---
type: PHP Package
title: voyager/graph
description: Optional Neo4j companion to voyager/database — Bolt driver, Instrument graph models, Cypher helpers, and make:graph-model.
resource: ../../src/Voyager/Graph
tags: [php, package, voyager, graph, neo4j, instrument, cypher]
status: draft
generated: { by: agent:cursor-grok-4.6, at: 2026-08-23T02:56:00Z }
stale_after: 2026-11-22
sources:
  - id: package-source
    resource: ../../src/Voyager/Graph
    title: Graph package source (10 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Graph/composer.json
    title: voyager/graph composer.json
  - id: neo4j-config
    resource: ../../src/Voyager/Graph/config/neo4j.php
    title: Package-local neo4j connection config (published as voyager-graph-config)
  - id: tests
    resource: ../../tests/Graph/GraphPackageTest.php
    title: Graph Pest suite (4 tests)
  - id: upstream-0-7
    resource: /Volumes/ProjectSaturnStudios/ScrapyardIOEcosystem/ScrapyardIO/framework/src/Fabricate/Graph
    title: 0.7.x fabricate/graph companion (Fabricate\\Graph, Polisher)
  - id: donor
    resource: https://github.com/projectsaturnstudios/eloquent4j
    title: eloquent4j, the Neo4j driver this companion descends from
---

# Overview

Opt-in Neo4j companion to [voyager/database](database.md). Apps register
`Voyager\Graph\GraphServiceProvider` and add a `database.connections.neo4j`
entry. **`GraphServiceProvider` is not on `DefaultProviders`.**[^package-source]

**10** PHP files under `src/Voyager/Graph/` (including `config/neo4j.php`).
Package name `voyager/graph`. Requires `voyager/database`,
`voyager/contracts`, and `voyager/nuts-and-bolts` `^0.8.0`. Suggests
`laudis/neo4j-php-client` `^3.3` and `voyager/console` for
`make:graph-model`. Root `require-dev` pins
`laudis/neo4j-php-client ^3.3.0`; that is not a production require.

Straight port of 0.7.x `fabricate/graph` with `Fabricate\`→`Voyager\`,
`Polisher`→`Instrument`, `scrapyard_io`→`venusian`.[^upstream-0-7]

# Surface

* `GraphServiceProvider` — binds `db.connector.neo4j`, extends the `db`
  manager with driver `neo4j` (returns `Neo4jConnection`), registers
  `make:graph-model`, publishes `config/neo4j.php` under tag
  `voyager-graph-config`. Uses `$this->app` (0.8 `ServiceProvider`).
* `Database\Neo4jConnection` — extends `Voyager\Database\Connection`;
  ctor passes `null` PDO. Cypher `select` / `statement` /
  `affectingStatement`, transactions, Node/Relationship row flattening.
* `Database\Connectors\Neo4jConnector` — bolt/neo4j URI + auth builder.
* `Database\Query\Neo4jQueryBuilder`, `Grammars\Neo4jGrammar`
  (`?` → `$pN` named params), `Processors\Neo4jProcessor`.
* `Instrument\Model` — extends `Voyager\Database\Instrument\Model`;
  `$connection = 'neo4j'`, string key, non-incrementing;
  `getConnection()` enforces `Neo4jConnection`; `getLabel()`.
* `Console\GraphModelMakeCommand` — `#[AsCommand('make:graph-model')]`;
  stub uses `Voyager\Graph\Instrument\Model`.
* Helpers: `cypher()`, `cypher_one()`, `cypher_run()`,
  `neo4j_connection()`.

# Tests

`tests/Graph/GraphPackageTest.php` is **4** Pest v4 closures (no
`TestCase`): class/subclass/function-exists, provider not in
`DefaultProviders`, extension registration on `Voyager\System\Application`,
connector URI. Run `./vendor/bin/pest tests/Graph`.

# Related

- [voyager/database](database.md)
- [Monorepo with composer replace](../architecture/package-split.md)
- [The 0.7.x reference implementation](../reference/upstream-0-7-x.md)

[^package-source]: Graph package source (10 PHP files)
[^upstream-0-7]: 0.7.x fabricate/graph companion
