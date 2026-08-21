<?php

use Orchestra\Testbench\Concerns\ApplicationTestingHooks;
use Orchestra\Testbench\Foundation\Application as Testbench;
use Voyager\Contracts\Console\Kernel as ConsoleKernelContract;
use Voyager\System\Console\Kernel as ConsoleKernel;
use Voyager\System\Testing\Concerns\InteractsWithConsole;
use Voyager\System\Testing\DatabaseMigrations;
use Voyager\System\Testing\RefreshDatabaseState;

use function Orchestra\Testbench\package_path;

uses(ApplicationTestingHooks::class, DatabaseMigrations::class, InteractsWithConsole::class);

beforeEach(function () {
    $this->dropViews = false;
    $this->dropTypes = false;

    RefreshDatabaseState::$migrated = false;

    $this->afterApplicationCreated(function () {
        $this->app['config']->set([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);
    });

    $this->app = Testbench::create(
        basePath: package_path('vendor/orchestra/testbench-core/laravel'),
    );

    $this->setUpTheApplicationTestingHooks();
    $this->withoutMockingConsoleOutput();
});

afterEach(function () {
    $this->tearDownTheApplicationTestingHooks();

    RefreshDatabaseState::$migrated = false;
});

test('the migrations run with the default options', function () {
    $this->app->instance(ConsoleKernelContract::class, $kernel = Mockery::spy(ConsoleKernel::class));

    $kernel->shouldReceive('call')
        ->once()
        ->with('migrate:fresh', [
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
        ]);

    $this->runDatabaseMigrations();
});

test('the dropViews option is passed through', function () {
    $this->dropViews = true;

    $this->app->instance(ConsoleKernelContract::class, $kernel = Mockery::spy(ConsoleKernel::class));

    $kernel->shouldReceive('call')
        ->once()
        ->with('migrate:fresh', [
            '--drop-views' => true,
            '--drop-types' => false,
            '--seed' => false,
        ]);

    $this->runDatabaseMigrations();
});

test('the dropTypes option is passed through', function () {
    $this->dropTypes = true;

    $this->app->instance(ConsoleKernelContract::class, $kernel = Mockery::spy(ConsoleKernel::class));

    $kernel->shouldReceive('call')
        ->once()
        ->with('migrate:fresh', [
            '--drop-views' => false,
            '--drop-types' => true,
            '--seed' => false,
        ]);

    $this->runDatabaseMigrations();
});
