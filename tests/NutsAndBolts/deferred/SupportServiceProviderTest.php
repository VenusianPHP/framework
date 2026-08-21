<?php

use Illuminate\Foundation\Application;
use Illuminate\Translation\Translator;
use Tests\NutsAndBolts\Fixtures\ServiceProviderForTestingOne;
use Tests\NutsAndBolts\Fixtures\ServiceProviderForTestingTwo;
use Voyager\Config\Repository as Config;
use Voyager\NutsAndBolts\ServiceProvider;

/**
 * Boot both testing providers against a fresh application double and hand the
 * application back. Mirrors the upstream setUp().
 */
function bootedServiceProviderApp()
{
    ServiceProvider::$publishes = [];
    ServiceProvider::$publishGroups = [];

    $app = Mockery::mock(Application::class)->makePartial();
    $config = new Config;

    $app->instance('config', $config);
    $config->set('database.migrations.update_date_on_publish', true);

    (new ServiceProviderForTestingOne($app))->boot();
    (new ServiceProviderForTestingTwo($app))->boot();

    return $app;
}

beforeEach(function () {
    $this->app = bootedServiceProviderApp();
});

test('publishable service providers are listed', function () {
    expect(ServiceProvider::publishableProviders())->toEqual([
        ServiceProviderForTestingOne::class,
        ServiceProviderForTestingTwo::class,
    ]);
});

test('publishable groups are listed', function () {
    expect(ServiceProvider::publishableGroups())->toEqual([
        'some_tag',
        'tag_one',
        'tag_two',
        'tag_three',
        'tag_four',
        'tag_five',
    ]);
});

test('simple assets are published correctly', function () {
    $toPublish = ServiceProvider::pathsToPublish(ServiceProviderForTestingOne::class);

    expect($toPublish)->toHaveKey('source/unmarked/one')
        ->and($toPublish)->toHaveKey('source/tagged/one')
        ->and($toPublish)->toEqual([
            'source/unmarked/one' => 'destination/unmarked/one',
            'source/tagged/one' => 'destination/tagged/one',
            'source/tagged/multiple' => 'destination/tagged/multiple',
            'source/unmarked/two' => 'destination/unmarked/two',
            'source/tagged/three' => 'destination/tagged/three',
            'source/tagged/multiple_two' => 'destination/tagged/multiple_two',
        ]);
});

test('multiple assets are published correctly', function () {
    $toPublish = ServiceProvider::pathsToPublish(ServiceProviderForTestingTwo::class);

    expect($toPublish)->toHaveKey('source/unmarked/two/a')
        ->and($toPublish)->toHaveKey('source/unmarked/two/b')
        ->and($toPublish)->toHaveKey('source/unmarked/two/c')
        ->and($toPublish)->toHaveKey('source/tagged/two/a')
        ->and($toPublish)->toHaveKey('source/tagged/two/b')
        ->and($toPublish)->toEqual([
            'source/unmarked/two/a' => 'destination/unmarked/two/a',
            'source/unmarked/two/b' => 'destination/unmarked/two/b',
            'source/unmarked/two/c' => 'destination/tagged/two/a',
            'source/tagged/two/a' => 'destination/tagged/two/a',
            'source/tagged/two/b' => 'destination/tagged/two/b',
        ]);
});

test('simple tagged assets are published correctly', function () {
    $toPublish = ServiceProvider::pathsToPublish(ServiceProviderForTestingOne::class, 'some_tag');

    expect($toPublish)->not->toHaveKey('source/tagged/two/a')
        ->and($toPublish)->not->toHaveKey('source/tagged/two/b')
        ->and($toPublish)->toHaveKey('source/tagged/one')
        ->and($toPublish)->toEqual(['source/tagged/one' => 'destination/tagged/one']);
});

test('multiple tagged assets are published correctly', function () {
    $toPublish = ServiceProvider::pathsToPublish(ServiceProviderForTestingTwo::class, 'some_tag');

    expect($toPublish)->toHaveKey('source/tagged/two/a')
        ->and($toPublish)->toHaveKey('source/tagged/two/b')
        ->and($toPublish)->not->toHaveKey('source/tagged/one')
        ->and($toPublish)->not->toHaveKey('source/unmarked/two/c')
        ->and($toPublish)->toEqual([
            'source/tagged/two/a' => 'destination/tagged/two/a',
            'source/tagged/two/b' => 'destination/tagged/two/b',
        ]);
});

test('multiple tagged assets are merged correctly', function () {
    $toPublish = ServiceProvider::pathsToPublish(null, 'some_tag');

    expect($toPublish)->toHaveKey('source/tagged/two/a')
        ->and($toPublish)->toHaveKey('source/tagged/two/b')
        ->and($toPublish)->toHaveKey('source/tagged/one')
        ->and($toPublish)->not->toHaveKey('source/unmarked/two/c')
        ->and($toPublish)->toEqual([
            'source/tagged/one' => 'destination/tagged/one',
            'source/tagged/two/a' => 'destination/tagged/two/a',
            'source/tagged/two/b' => 'destination/tagged/two/b',
        ]);
});

test('publishesMigrations only registers while update_date_on_publish is set', function () {
    $serviceProvider = new ServiceProviderForTestingOne($this->app);

    (fn () => $this->publishesMigrations(['source/tagged/four' => 'destination/tagged/four'], 'tag_four'))
        ->call($serviceProvider);

    expect(ServiceProvider::publishableMigrationPaths())->toContain('source/tagged/four');

    foreach ([false, 'migrations', null] as $migrations) {
        $this->app->config->set('database.migrations', $migrations);

        (fn () => $this->publishesMigrations(['source/tagged/five' => 'destination/tagged/five'], 'tag_four'))
            ->call($serviceProvider);

        expect(ServiceProvider::publishableMigrationPaths())->not->toContain('source/tagged/five');
    }
});

test('loadTranslationsFrom without a namespace adds a path', function () {
    $translator = Mockery::mock(Translator::class);
    $translator->shouldReceive('addPath')->once()->with(__DIR__.'/translations');

    $this->app->shouldReceive('afterResolving')->once()->with('translator', Mockery::on(function ($callback) use ($translator) {
        $callback($translator);

        return true;
    }));

    (new ServiceProviderForTestingOne($this->app))->loadTranslationsFrom(__DIR__.'/translations');
});

test('loadTranslationsFrom with a namespace adds a namespace', function () {
    $translator = Mockery::mock(Translator::class);
    $translator->shouldReceive('addNamespace')->once()->with('namespace', __DIR__.'/translations');

    $this->app->shouldReceive('afterResolving')->once()->with('translator', Mockery::on(function ($callback) use ($translator) {
        $callback($translator);

        return true;
    }));

    (new ServiceProviderForTestingOne($this->app))->loadTranslationsFrom(__DIR__.'/translations', 'namespace');
});

test('a provider can be removed from the bootstrap file', function () {
    $tempFile = __DIR__.'/providers.php';

    file_put_contents($tempFile, $contents = <<< PHP
    <?php

    return [
        App\Providers\AppServiceProvider::class,
        App\Providers\TelescopeServiceProvider::class,
    ];
    PHP);

    ServiceProvider::removeProviderFromBootstrapFile('TelescopeServiceProvider', $tempFile, true);

    // Should have deleted nothing
    expect(trim(file_get_contents($tempFile)))->toBe($contents);

    // Should delete the telescope provider
    ServiceProvider::removeProviderFromBootstrapFile('App\Providers\TelescopeServiceProvider', $tempFile, true);

    expect(trim(file_get_contents($tempFile)))->toBe(<<< PHP
    <?php

    return [
        App\Providers\AppServiceProvider::class,
    ];
    PHP);

    // Should fuzzily delete the App\Providers\AppServiceProvider class
    ServiceProvider::removeProviderFromBootstrapFile('AppServiceProvider', $tempFile);

    expect(trim(file_get_contents($tempFile)))->toBe(<<< 'PHP'
    <?php

    return [

    ];
    PHP);
})->after(function () {
    @unlink(__DIR__.'/providers.php');
});
