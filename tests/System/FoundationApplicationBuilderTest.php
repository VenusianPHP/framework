<?php

use Voyager\System\Application;

afterEach(function () {
    unset($_ENV['APP_BASE_PATH']);

    unset($_ENV['LARAVEL_STORAGE_PATH'], $_SERVER['LARAVEL_STORAGE_PATH']);
});

describe('base path', function () {
    test('an explicit argument wins over the environment', function () {
        $_ENV['APP_BASE_PATH'] = __DIR__.'/as-env';

        $app = Application::configure(__DIR__.'/as-arg')->create();

        expect($app->basePath())->toBe(__DIR__.'/as-arg');
    });

    test('the environment is used when no argument is given', function () {
        $_ENV['APP_BASE_PATH'] = __DIR__.'/as-env';

        $app = Application::configure()->create();

        expect($app->basePath())->toBe(__DIR__.'/as-env');
    });

    test('composer locates the base path as a last resort', function () {
        $app = Application::configure()->create();

        expect($app->basePath())->toBe(dirname(__DIR__, 2));
    });
});

describe('storage path', function () {
    test('a global env variable is used', function () {
        $_ENV['LARAVEL_STORAGE_PATH'] = __DIR__.'/env-storage';

        $app = Application::configure()->create();

        expect($app->storagePath())->toBe(__DIR__.'/env-storage');
    });

    test('a global server variable is used', function () {
        $_SERVER['LARAVEL_STORAGE_PATH'] = __DIR__.'/server-storage';

        $app = Application::configure()->create();

        expect($app->storagePath())->toBe(__DIR__.'/server-storage');
    });

    test('the env variable is preferred over the server one', function () {
        $_ENV['LARAVEL_STORAGE_PATH'] = __DIR__.'/env-storage';
        $_SERVER['LARAVEL_STORAGE_PATH'] = __DIR__.'/server-storage';

        $app = Application::configure()->create();

        expect($app->storagePath())->toBe(__DIR__.'/env-storage');
    });

    test('it falls back to storage under the base path', function () {
        $app = Application::configure()->create();

        expect($app->storagePath())->toBe($app->basePath().DIRECTORY_SEPARATOR.'storage');
    });

    test('useStoragePath overrides everything', function () {
        $_ENV['LARAVEL_STORAGE_PATH'] = __DIR__.'/env-storage';

        $app = Application::configure()->create();
        $app->useStoragePath(__DIR__.'/custom-storage');

        expect($app->storagePath())->toBe(__DIR__.'/custom-storage');
    });
});
