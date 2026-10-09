---
type: Module
title: make:node
description: Dev command that writes an empty workflow Node, or an AsyncNode with --async, into App\Workflows.
resource: src/Voyager/Core/Console/NodeMakeCommand.php
tags: [console, computer, workflows, generator]
status: draft
generated: { by: claude-opus-5-5, at: "2026-10-07T00:00:00Z" }
sources:
  - id: command
    resource: src/Voyager/Core/Console/NodeMakeCommand.php
    title: NodeMakeCommand
  - id: stubs
    resource: src/Voyager/Core/Console/stubs/node.stub
    title: node.stub and node.async.stub
  - id: test
    resource: tests/Workflows/MakeNodeTest.php
    title: Pest coverage
---

# Use

```bash
php computer make:node FetchApod                 # App\Workflows\FetchApod extends Node
php computer make:node Apod/Download --async     # App\Workflows\Apod\Download extends AsyncNode
php computer make:node FetchApod --force         # overwrite
```

Registered in `ComputerServiceProvider::$dev_commands`. `GeneratorCommand` subclass: default namespace `{app}\Workflows`, reserved names refused, existing file kept unless `--force`.[^command]

# Body

* `node.stub` — `prep()` / `exec()` / `post()`, all returning null. Runs as written: `run()` → null.[^stubs][^test]
* `node.async.stub` (`--async`, `-a`) — `prepAsync()` / `execAsync()` / `postAsync()`. `runAsync()` on the app loop's runtime → null.[^stubs][^test]

`stub:publish` copies both; a published `stubs/node.stub` / `stubs/node.async.stub` wins.[^command]

[^command]: NodeMakeCommand
[^stubs]: node.stub and node.async.stub
[^test]: Pest coverage
