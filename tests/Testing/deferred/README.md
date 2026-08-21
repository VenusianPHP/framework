# Deferred `Testing` tests

Ported from `laravel/framework@v12.67.0:tests/Testing`, excluded in `phpunit.xml`.

* `ConfigShowCommandTest.php` — needs `orchestra/testbench`.
* `InteractsWithDatabaseTest.php`, `TestDatabasesTest.php` — need `Database`
  (wave 6).

## Cut permanently

Removed rather than deferred, because the subject is out of scope:
`TestResponseTest`, `TestComponentTest`, `TestViewTest`,
`Concerns/TestViewsTest`, `Console/RouteListCommandTest`, and the three
`AssertRedirectTo*Test` files, which assert on HTTP responses, views or routes.
