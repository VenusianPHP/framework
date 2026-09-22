# Cut upstream tests

Cuts that are not a whole file stay noted here. Whole files that cannot run
on 0.9 live beside this README.

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

# Facade-bound tests

`DatabaseQueryExceptionTest`, `DatabaseSchemaBuilderIntegrationTest`,
`DatabaseInstrumentIntegrationTest`, `DatabaseSQLiteBuilderTest`,
`DatabaseMigratorIntegrationTest` and the `migrations/` fixtures they load
drive the toolkit through `DB::` / `Schema::` (`MagicAliases`), which 0.9
does not have. Their subjects are covered by the unit tests here; restore
them with Testing.

## `dirty on casted uri`

Cut from `DatabaseInstrumentModelTest`. `AsUri` casts through
`Voyager\NutsAndBolts\Uri`, which needs `league/uri`. That package is not a
0.9 dependency, so the cast cannot run here.
