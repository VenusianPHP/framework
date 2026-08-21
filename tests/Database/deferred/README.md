# Cut upstream tests

Nothing lives in this directory as a standalone file yet — the one cut so far
was small enough to remove in place, the same way `QueueSyncQueueTest`'s
after-commit tests were handled in `tests/Queue/deferred/README.md`. This
README exists so the reason is recorded somewhere obvious, and so this
directory is ready if a future wave needs to move a whole file here.

## `testRouteKeyIsPrimaryKey` / `testRouteNameIsPrimaryKeyName`

Cut from `DatabaseInstrumentModelTest` (in place; see the note left where they
were). They cover `Model::getRouteKey()` and `Model::getRouteKeyName()`,
which power route-model binding.

`Voyager\Database\Instrument\Model` deliberately drops the whole
route-model-binding block — `getRouteKey()`, `resolveRouteBinding()`, and
their child/soft-delete variants — along with the `UrlRoutable` contract it
implemented for it. See the comment above `getForeignKey()` in
`src/Voyager/Database/Instrument/Model.php`: routing is receiving-HTTP, and
HTTP request/response handling is out of scope for this port (same boundary
as Auth, Mail, and `Http\Resources`).
