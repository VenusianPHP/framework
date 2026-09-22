<?php
declare(strict_types=1);

use Voyager\Config\Repository;
use Voyager\Contracts\Sketches\SketchRegistry as RegistryContract;
use Voyager\Core\DefaultProviders;
use Voyager\Core\RenderedInstance;
use Voyager\IOPools\EventLoop;
use Voyager\Sketches\SketchRegistry;
use Voyager\Sketches\SketchRunner;
use Voyager\Sketches\SketchesServiceProvider;

test('provider is on the boot list', function () {
    expect((new DefaultProviders)->toArray())->toContain(SketchesServiceProvider::class);
});

test('provider binds registry and runner', function () {
    $app = new RenderedInstance(dirname(__DIR__, 2));
    $app->registerInstance('config', new Repository(['sketches' => ['refresh_rate' => 60, 'load' => []]]));
    $app->registerInstance('event-loop', new EventLoop);
    $app->register(new SketchesServiceProvider($app));

    expect($app->make('sketches.registry'))->toBeInstanceOf(SketchRegistry::class)
        ->and($app->make(RegistryContract::class))->toBe($app->make('sketches.registry'))
        ->and($app->make('sketches.runner'))->toBeInstanceOf(SketchRunner::class);
});
