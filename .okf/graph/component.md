---
type: Module
title: Graph
description: Opt-in Neo4j provider over laudis. cypher helpers autoload. via() is inherited from Connection.
resource: src/Voyager/Graph/GraphServiceProvider.php
tags: [graph, neo4j, cypher]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: grok-4.7/cursor, at: 2026-09-22T22:10:00Z }
sources:
  - id: provider
    resource: src/Voyager/Graph/GraphServiceProvider.php
    title: GraphServiceProvider
  - id: helpers
    resource: src/Voyager/Graph/Helpers/functions.php
    title: cypher helpers
  - id: command
    resource: src/Voyager/Graph/Console/GraphModelMakeCommand.php
    title: make:graph-model
---

# Overview

Opt-in. `GraphServiceProvider` is not on `DefaultProviders`. Connection key is `database.connections.neo4j`. `Neo4jConnection` extends `Connection` over `laudis/neo4j-php-client` (suggest).[^provider]

Helpers `cypher`, `cypher_one`, `cypher_run`, and `neo4j_connection` autoload. `cypher()` exists without the provider. `neo4j_connection()` throws `RuntimeException` when the resolved connection is not a `Neo4jConnection`. A missing `neo4j` connection key fails earlier inside `DatabaseManager`.[^helpers]

`make:graph-model` scaffolds a graph instrument model.[^command]

`Connection::via()` is inherited. See [Database on the loop](../database/loop.md). The worker needs this provider booted. The suite does not open a Neo4j client.

[^provider]: GraphServiceProvider
[^helpers]: cypher helpers
[^command]: make:graph-model
