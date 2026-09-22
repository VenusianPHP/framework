# Deferred upstream tests

* `DatabaseFailedJobProviderTest`, `DatabaseUuidFailedJobProviderTest` — the two
  database failed-job providers. Their source is present but inert: nothing
  resolves them until a `db` binding exists. `queue.failed.driver` defaults to
  `file`, which works today.
* `QueueDelayTest`, `QueueSizeTest` — need `Queue::fake()`
  (`Testing\Fakes\QueueFake` and the `MagicAliases` base), which 0.9 has not
  ported. Restore with Testing.

The `database` queue driver is not part of 0.9: `DatabaseQueue`, its connector
and job classes were removed on 2026-09-22, along with the `database`
connection in `config/queue.php`. Use `redis`, `sync`, `deferred`, `background`
or `failover`. The three `make:*-table` commands stay unregistered with it —
they need Database's `migration.creator`.
