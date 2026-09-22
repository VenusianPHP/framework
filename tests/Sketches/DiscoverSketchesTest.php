<?php
declare(strict_types=1);

use Venusian\Tests\Sketches\Fixtures\Sketches\NamedSketch;
use Venusian\Tests\Sketches\Fixtures\Sketches\PingSketch;
use Voyager\Sketches\DiscoverSketches;

test('finds concrete sketches under a path, skips the rest', function () {
    $found = DiscoverSketches::within(
        [__DIR__.'/Fixtures/Sketches'],
        'Venusian\\Tests\\Sketches\\',
        __DIR__,
    );
    sort($found);
    expect($found)->toBe([NamedSketch::class, PingSketch::class]);
});

test('missing path yields nothing', function () {
    expect(DiscoverSketches::within([__DIR__.'/Fixtures/Nope'], 'Venusian\\Tests\\Sketches\\', __DIR__))->toBe([]);
});
