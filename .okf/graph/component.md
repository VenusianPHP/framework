---
type: Module
title: Graph
description: Neo4j database driver on DefaultProviders. The builder compiles to Cypher on the label as n. cypher() helpers. via() and stream() work as on SQL connections.
resource: src/Voyager/Graph/GraphServiceProvider.php
tags: [graph, neo4j, cypher, database]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: provider
    resource: src/Voyager/Graph/GraphServiceProvider.php
    title: GraphServiceProvider
  - id: connection
    resource: src/Voyager/Graph/Database/Neo4jConnection.php
    title: Neo4jConnection
  - id: grammar
    resource: src/Voyager/Graph/Database/Query/Grammars/Neo4jGrammar.php
    title: Neo4jGrammar
  - id: connector
    resource: src/Voyager/Graph/Database/Connectors/Neo4jConnector.php
    title: Neo4jConnector
  - id: model
    resource: src/Voyager/Graph/Instrument/Model.php
    title: Graph Model
  - id: helpers
    resource: src/Voyager/Graph/Helpers/functions.php
    title: cypher helpers
---

# Overview

`GraphServiceProvider` is on `DefaultProviders`. It extends `db` with driver `neo4j` whether or not `db` was already resolved. Connection config `database.connections.neo4j`: host, port, database, username, password, scheme (`bolt`, `bolt+s`, `bolt+ssc`, `neo4j`, `neo4j+s`, `neo4j+ssc`), prefix. The client `laudis/neo4j-php-client` is suggested; only opening a neo4j connection needs it.[^provider][^connector]

`database` picks the Neo4j database; `prefix` prefixes labels. Auth: token (bearer), username + password (basic), or neither (disabled).[^connector]

# Raw Cypher

`cypher($query, $bindings, $connection)`, `cypher_one`, `cypher_run`, `neo4j_connection($name)`; autoloaded. Named (`$id`) or positional (`?`) parameters; a `?` inside a string or backticked name stays a character. Nodes come back as their properties, lists and maps as arrays.[^helpers][^connection][^grammar]

# Models and the builder

`Voyager\Graph\Instrument\Model`: table is the label, columns are properties, key string `id`, not incrementing, connection `neo4j`.[^model]

`Neo4jGrammar` compiles the builder to Cypher, matching the label as `n`. A qualified column (`Label.id`) is property `n.id`.[^grammar]

| Builder | Cypher |
|---|---|
| select | `MATCH (n:Label) WHERE … RETURN n ORDER BY … SKIP … LIMIT …` |
| aggregate | `RETURN count(n) AS aggregate`; `DISTINCT` inside |
| exists | `CALL { … } RETURN count(*) > 0 AS exists` |
| insert | `UNWIND [{…}] AS row CREATE (n:Label) SET n = row` |
| update | `MATCH … [WITH n ORDER/SKIP/LIMIT] SET n.a = ? RETURN count(n) AS affected` |
| delete | `MATCH … DETACH DELETE n RETURN count(n) AS affected` |
| upsert | `UNWIND … MERGE (n:Label {key: row.key}) ON CREATE SET n = row ON MATCH SET … RETURN count(n) AS affected` |
| truncate | `MATCH (n:Label) DETACH DELETE n` |

Wheres: basic, like / `whereLike` (Cypher regex over the escaped pattern; `like` ignores case, `caseSensitive: true` keeps it), in, not in, null, between, between columns, value between, column, nested, raw, expression. Update, delete and upsert report nodes touched; other affecting statements report the summary's counters.[^grammar][^connection]

Refused by name with `LogicException`: joins, groupBy, having, unions, locks, eager-load limits, date/JSON/fulltext/exists/bitwise wheres, subquery update values, `insertGetId`. Unknown operators throw `InvalidArgumentException`. Write those in `cypher()`.[^grammar]

`cursor()` yields the select's rows; Bolt hands the whole result at once.[^connection]

# On the loop

`Neo4jConnection` extends `Database\Connection`: `via()`, `via()->transaction()`, `stream()` and the lanes behave as in [Database on the loop](/database/loop.md). Workers need the client installed too.[^connection]

`make:graph-model` generates a model.

[^provider]: GraphServiceProvider
[^connection]: Neo4jConnection
[^grammar]: Neo4jGrammar
[^connector]: Neo4jConnector
[^model]: Graph Model
[^helpers]: cypher helpers
