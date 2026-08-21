# Deferred upstream tests

`BusBatchTest` drives batches through a real database connection —
`Database\Capsule\Manager`, `Query\Builder`, `PostgresConnection` and an
`Instrument\Model`. As of the wave 4 Pest conversion this mostly works
(16 of 19 cases pass under a real SQLite connection); it stays in `deferred/`
and excluded from the default suite only because of two remaining `src/` bugs
— see `.okf/known-gaps.md` for both:

* `DatabaseBatchRepository::find()` throws instead of implicitly returning
  `null` when no row is found (missing `return null;`).
* `System\Bus\PendingChain::__construct()` receives a string where it expects
  an array in the `Bus`/`Queue` MagicAliases chaining test.

`BatchRepositoryFake` covers the batch surface that does not need a
connection.
