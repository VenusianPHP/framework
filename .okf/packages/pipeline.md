---
type: PHP Package
title: voyager/pipeline
description: Pipe values through a series of classes or closures.
resource: ../../src/Voyager/Pipeline
tags: [php, package, voyager, pipeline]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Pipeline
    title: Pipeline package source (3 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Pipeline/composer.json
    title: voyager/pipeline composer.json
---

# Overview

3 PHP files under `src/Voyager/Pipeline/`. Upstream `v12.67.0`.
`PipelineServiceProvider` is in `DefaultProviders`.
`tests/Pipeline/deferred/PipelineTransactionTest.php` still references
Testbench.

# Related

- [voyager/vessel](vessel.md)

[^package-source]: Pipeline package source (3 PHP files)
[^package-manifest]: voyager/pipeline composer.json
