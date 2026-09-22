# Deferred upstream tests

`BusBatchTest` needs `DatabaseBatchRepository` on a real connection (Database
component) and `Bus::fake()` (Testing). Restore when both land.
