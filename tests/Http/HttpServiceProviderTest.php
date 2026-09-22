<?php

use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;
use Voyager\Http\Client\Factory;
use Voyager\Http\HttpServiceProvider;

beforeEach(function () {
    $this->app = RenderedInstance::setInstance(new RenderedInstance(sys_get_temp_dir()));
    $this->app->registerInstance('config', new Repository([]));
});

afterEach(function () {
    RenderedInstance::setInstance(null);
});

test('the provider binds http and merges config', function () {
    $app = app();
    (new HttpServiceProvider($app))->register();

    expect($app['http'])->toBeInstanceOf(Factory::class)
        ->and($app['config']->get('http.async.default'))->toBe('curl');
});
