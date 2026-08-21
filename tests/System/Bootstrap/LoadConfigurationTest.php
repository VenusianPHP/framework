<?php

use Voyager\Filesystem\Filesystem;
use Voyager\System\Application;
use Voyager\System\Bootstrap\LoadConfiguration;

/** Read a private property off the application. */
function applicationProperty(Application $app, string $property): mixed
{
    return (new ReflectionClass($app))->getProperty($property)->getValue($app);
}

test('the framework configuration is loaded', function () {
    $app = new Application;

    (new LoadConfiguration)->bootstrap($app);

    expect($app['config']['app.name'])->toBe('Venusian');
});

test('an environment resolver is installed on the application', function () {
    $app = new Application;

    expect(applicationProperty($app, 'environmentResolver'))->toBeNull();

    (new LoadConfiguration)->bootstrap($app);

    expect(applicationProperty($app, 'environmentResolver'))->toBeInstanceOf(Closure::class);
});

test('the framework configuration can be opted out of', function () {
    $app = new Application;
    $app->dontMergeFrameworkConfiguration();

    (new LoadConfiguration)->bootstrap($app);

    expect($app['config']['app.name'])->toBeNull();
});

test('a custom config path is loaded in isolation', function () {
    $app = new Application(__DIR__.'/../fixtures');
    $app->useConfigPath(__DIR__.'/../fixtures/config');

    (new LoadConfiguration)->bootstrap($app);

    expect($app['config']['bar.foo'])->toBeNull()
        ->and($app['config']['custom.foo'])->toBe('bar');
});

test('the configuration keys match the loaded filenames', function () {
    $baseConfigPath = __DIR__.'/../../../config';
    $customConfigPath = __DIR__.'/../fixtures/config';

    $app = new Application;
    $app->useConfigPath($customConfigPath);

    (new LoadConfiguration)->bootstrap($app);

    $this->assertEqualsCanonicalizing(
        array_keys($app['config']->all()),
        collect((new Filesystem)->files([
            $baseConfigPath,
            $customConfigPath,
        ]))->map(fn ($file) => $file->getBaseName('.php'))->unique()->values()->toArray()
    );
});
