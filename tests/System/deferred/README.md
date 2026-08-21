# Deferred `System` tests

Ported from `laravel/framework@v12.67.0:tests/Foundation`, but not yet runnable.
They are excluded in `phpunit.xml`. Restore each one when its dependency lands.

## Needs `Testing` (wave 3)

`Voyager\System\Testing\*` was not ported with the rest of `System`; it belongs
with the `Testing` component.

* `DatabaseTransactionsManagerTest.php` — `Testing\DatabaseTransactionsManager`,
  which arrives with Database in wave 6.
* `DatabaseTruncationTest.php` — `Testing\DatabaseTruncation`.
* `WormholeTest.php` — `Testing\Wormhole` exists now, but the test still fails
  against it; unparking it is its own piece of work.

`FoundationInteractsWithTimeTest` and `CanConfigureMigrationCommandsTest` were
unparked when Testing landed.

## Needs `Auth` (not in the port scope)

`Auth` is not one of the components the plan ports. These stay parked unless that
changes.

* `FoundationAuthenticationTest.php`
* `FoundationAuthorizesRequestsTraitTest.php`

## Needs `Cache` (wave 3)

* `FoundationCacheBasedMaintenanceModeTest.php` — `System\CacheBasedMaintenanceMode`

## Needs `orchestra/testbench`

Testbench boots a full application skeleton. It is not a dev dependency yet, and
several of these also need components that have not landed.

* `FoundationDocsCommandTest.php`
* `FoundationInteractsWithDatabaseTest.php`
* `FoundationViteTest.php` — Vite is an asset bundler; likely stays cut
* `Testing/BootTraitsTest.php`
* `Testing/Concerns/InteractsWithContainerTest.php`
* `Testing/Concerns/InteractsWithViewsTest.php` — also needs a view layer, which
  is deliberately out of scope
* `DatabaseMigrationsTest.php`, `RefreshDatabaseTest.php` — also need `Database`
  (wave 6)
* `Cloud/QueueTest.php` — also needs `Queue` (wave 5) and `Http`

## Cut permanently

Not deferred — removed, because they test HTTP serving that Venusian will never do:
`FoundationFormRequestTest`, `FoundationExceptionsHandlerTest`,
`FoundationHelpersTest`, `LaravelCloudJsonFormatterTest`,
`Configuration/MiddlewareTest`, `Configuration/ExceptionsTest`,
`Testing/Concerns/MakesHttpRequestsTest`, `Http/*` (kernel and middleware),
`Console/RouteListCommandTest`, `Console/ServeCommandLogParserTest`,
`Exceptions/Renderer/*` and `Http/HtmlDumperTest`.
