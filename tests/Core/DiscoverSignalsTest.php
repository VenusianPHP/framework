<?php

use Venusian\Tests\Core\Fixtures\FixtureSignal;
use Venusian\Tests\Core\Fixtures\Listeners\FixtureListener;
use Voyager\Core\Signals\DiscoverSignals;

afterEach(fn () => DiscoverSignals::$guess_class_names_using_callback = null);

// within() hands getListenerEvents() the Finder itself, not an array of its files.
it('discovers handle methods from a listener directory', function () {
    DiscoverSignals::guessClassNamesUsing(
        fn (SplFileInfo $file): string => 'Venusian\\Tests\\Core\\Fixtures\\Listeners\\'.$file->getBasename('.php')
    );

    expect(DiscoverSignals::within([__DIR__.'/Fixtures/Listeners'], dirname(__DIR__, 2)))
        ->toBe([FixtureSignal::class => [FixtureListener::class.'@handle']]);
});
