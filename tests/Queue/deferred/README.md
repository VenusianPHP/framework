# Deferred upstream tests

These cover the `database` queue driver and the two database failed-job
providers. Both need the Database component, which arrives in wave 6; the plan
backfills the `database` driver itself in wave 7.

The driver's source is ported and present — `DatabaseQueue`, `DatabaseConnector`,
`Jobs\DatabaseJob`, `Jobs\DatabaseJobRecord`, `Failed\DatabaseFailedJobProvider`
and `Failed\DatabaseUuidFailedJobProvider` — and `QueueServiceProvider` still
registers the connector. They are inert rather than absent: nothing resolves
them until a `db` binding exists, so they light up when Database lands.

Four after-commit tests were also cut from `QueueSyncQueueTest`, in place, for
the same reason — see the note where they were.
