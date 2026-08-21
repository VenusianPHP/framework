# Cut upstream tests

`DatabaseEloquentResourceModelTest` and `DatabaseEloquentResourceCollectionTest`
(and their `Fixtures/Resources` and `UseResource` model fixtures) are not here.

They cover `Model::toResource()`, which turns a model into an
`Http\Resources\Json\JsonResource` — an HTTP response object. `Http` is ported
as the client only, so `Http\Resources` does not exist and the
`Instrument\Concerns\TransformsToResource` trait plus the `UseResource` and
`UseResourceCollection` attributes are cut with it.

This resolves the plan's open question about `TransformsToResourceCollection`,
the empty trait in `voyager/collections`: `Http\Resources` never landed, so
that stub has nothing to become. See `.okf/known-gaps.md`.

`Fixtures/Auth/User` and `Fixtures/Models/User` are local stand-ins for
Laravel's `Foundation\Auth\User`. Auth is out of scope, and the blueprint tests
that use them only need a model with a known primary key so `foreignIdFor()`
produces a predictable column name.
