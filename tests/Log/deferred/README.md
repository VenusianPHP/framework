# Deferred upstream tests

Both extend `Orchestra\Testbench\TestCase` — a full application harness — rather
than plain `PHPUnit\Framework\TestCase`.

* **ContextTest.php** also uses `LazilyRefreshDatabase`, so it needs Database
  (wave 6) on top of System (wave 2).
* **LogManagerTest.php** needs a booted application to resolve the `config` and
  `events` bindings the manager reads.

Restore both once System can boot an application. They are the only coverage of
`LogManager`'s channel resolution and of `Context`'s dehydrate/hydrate cycle.
