<?php

namespace Tests\Testing\Fixtures;

use Voyager\Testing\Concerns\TestCaches;
use Voyager\Vessel\Vessel as Container;

/**
 * A bare object composing the TestCaches trait, standing in for the anonymous
 * class TestCachesTest built inline under PHPUnit.
 *
 * The trait's methods are protected, so the test drives them through
 * Reflection rather than calling them directly.
 */
class TestCachesInstance
{
    use TestCaches;

    public $app;

    public function __construct()
    {
        $this->app = Container::getInstance() ?? new Container;
    }
}
