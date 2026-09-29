---
type: Module
title: Pipeline
description: Laravel pipeline behind app('pipeline'), fresh per resolve; Hub singleton; withinTransaction() runs the pipes in a database transaction.
resource: src/Voyager/Pipeline/Pipeline.php
tags: [pipeline, database]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: pipeline
    resource: src/Voyager/Pipeline/Pipeline.php
    title: Pipeline
  - id: provider
    resource: src/Voyager/Pipeline/PipelineServiceProvider.php
    title: PipelineServiceProvider
---

# Overview

`app('pipeline')` builds a new `Pipeline` each time; `Hub` is a singleton. `send()`, `through()`, `pipe()`, `via()`, `then()`, `thenReturn()`, `finally()`. Pipes: closures, class strings, `Class:args`, objects.[^pipeline][^provider]

`withinTransaction($connection)` wraps the run in `app('db')->connection($connection)->transaction()`: a throwing pipe rolls back what earlier pipes wrote. Null means the default connection; `false` turns it off.[^pipeline]

Blocking; no async surface.

[^pipeline]: Pipeline
[^provider]: PipelineServiceProvider
