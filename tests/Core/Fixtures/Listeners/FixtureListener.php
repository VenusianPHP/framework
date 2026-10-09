<?php

namespace Venusian\Tests\Core\Fixtures\Listeners;

use Venusian\Tests\Core\Fixtures\FixtureSignal;

final class FixtureListener
{
    public function handle(FixtureSignal $signal): void {}
}
