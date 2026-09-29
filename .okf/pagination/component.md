---
type: Module
title: Pagination
description: Paginator, LengthAwarePaginator, and CursorPaginator. Resolved through the container. No HTTP request.
resource: src/Voyager/Database/Concerns/BuildsQueries.php
tags: [pagination, paginator]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: queries
    resource: src/Voyager/Database/Concerns/BuildsQueries.php
    title: BuildsQueries
  - id: paginator
    resource: src/Voyager/Pagination/AbstractPaginator.php
    title: AbstractPaginator
  - id: length
    resource: src/Voyager/Pagination/LengthAwarePaginator.php
    title: LengthAwarePaginator
  - id: cursor
    resource: src/Voyager/Pagination/CursorPaginator.php
    title: CursorPaginator
---

# Overview

Three paginators: `Paginator`, `LengthAwarePaginator`, `CursorPaginator`.[^length][^cursor]

`BuildsQueries` resolves them through `ControlPanel::getInstance()->make(Class, params)`. The parameter array is compact constructor args, not positional.[^queries]

`Paginator::currentPageResolver`, `currentPathResolver`, and `queryStringResolver` are static hooks. `CursorPaginator` uses a separate `currentCursorResolver`. No HTTP request behind them; set the resolvers.[^paginator]

Not on the loop.

[^queries]: BuildsQueries
[^paginator]: AbstractPaginator
[^length]: LengthAwarePaginator
[^cursor]: CursorPaginator
