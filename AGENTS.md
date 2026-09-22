# Agent guidelines — venusian/framework

## Knowledge bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/)
(excluded from the Composer dist via `.gitattributes` `export-ignore`).
Before changing code or advising on this package: read
[`.okf/index.md`](.okf/index.md) first, then open only the concepts the
task needs.

Weigh what you read. `status: draft` or `deprecated`, a `stale_after`
already past, or no `verified` entry means check the cited source before
relying on it. The agent that wrote a concept does not set `status:
stable`. A different actor does.

When you learn something durable, update the affected concept(s) and
append [`.okf/log.md`](.okf/log.md).

Do **not** create `.okf` folders under `src/Voyager/*`. Knowledge for
this package lives at the package root only.
