---
type: Module
title: Graph
description: Opt-in Neo4j provider over laudis. cypher helpers autoload. via() is inherited from Connection.
resource: src/Voyager/Graph/GraphServiceProvider.php
tags: [graph, neo4j, cypher]
status: draft
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

Helpers `cypher`, `cypher_one`, `cypher_run`, and `neo4j_connection` autoload. `cypher()` exists without the provider. `neo4j_connection()` throws until the provider is registered and the connection is configured.[^helpers]

`make:graph-model` scaffolds a graph instrument model.[^command]

`Connection::via()` is inherited. See [Database on the loop](../database/loop.md). The worker needs this provider booted. The suite does not need a Neo4j client.

[^provider]: GraphServiceProvider
[^helpers]: cypher helpers
[^command]: make:graph-model
