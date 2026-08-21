# Deferred upstream tests

Ported from `laravel/framework@v12.67.0:tests/Container` but not yet runnable,
because they exercise components that have not been ported. Excluded from the
suite via `phpunit.xml`.

## ContextualAttributeBindingTest.php

Needs `Cache`, `Filesystem`, `Log` (wave 3) and `Database` (wave 6). Restore it
as those land — it is the only coverage of the contextual-attribute resolution
path in `Vessel::resolve()`.

Its `Auth`, `Authenticated`, `CurrentUser` and `RouteParameter` cases are
**permanently out of scope** — auth and HTTP-request attributes are not ported.
Drop those cases rather than restoring them.
