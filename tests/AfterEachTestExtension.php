<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Registers a subscriber that closes Mockery after every test method.
 *
 * Without this, Mockery's demeter/fluent mock cache is keyed by
 * `spl_object_id()`, which PHP recycles once an object is garbage
 * collected. Two unrelated tests whose mocks happen to land on the same
 * recycled object id can then share a stale demeter mock, so a method
 * stubbed only in the earlier test appears "not to exist" in the later
 * one. `Mockery::close()` clears that cache between tests. Mirrors
 * Laravel's own `tests/AfterEachTestExtension.php`.
 */
final class AfterEachTestExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new AfterEachTestSubscriber);
    }
}
