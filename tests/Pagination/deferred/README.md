# Deferred `Pagination` tests

Ported from `laravel/framework@v12.67.0:tests/Pagination`, excluded in
`phpunit.xml`. All four exercise `loadMorph()` / `loadMorphCount()`, which
forward to an Instrument collection, so they need `Database` (wave 6).

* `PaginatorLoadMorphTest.php`
* `PaginatorLoadMorphCountTest.php`
* `CursorPaginatorLoadMorphTest.php`
* `CursorPaginatorLoadMorphCountTest.php`

## Cut permanently

Not deferred — removed, because the subject is out of scope:

* `UrlWindowTest` — `UrlWindow` computes which page numbers a view should
  render, and the blade views went with it.
* `PaginatorResourceTest`, `CursorResourceTest` — both type against
  `Http\Resources\Json\JsonResource`. Only `Http\Client` is being ported.
