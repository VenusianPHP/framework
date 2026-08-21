# Deferred upstream tests

`BusBatchTest` drives batches through a real database connection —
`Database\Capsule\Manager`, `Query\Builder`, `PostgresConnection` and an
`Instrument\Model`. It comes back with Database in wave 6.

`DatabaseBatchRepository` is ported and inert until then; `BatchRepositoryFake`
covers the batch surface that does not need a connection.
