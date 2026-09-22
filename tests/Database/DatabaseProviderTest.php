<?php

use Voyager\Core\DefaultProviders;
use Voyager\Core\RenderedInstance;
use Voyager\Database\DatabaseManager;
use Voyager\Database\DatabaseServiceProvider;
use Voyager\Database\MigrationServiceProvider;

test('database providers are on the boot list', function () {
    $list = (new DefaultProviders)->toArray();
    expect($list)->toContain(DatabaseServiceProvider::class)
        ->toContain(MigrationServiceProvider::class);
});

test('db is a lazy singleton over an in-memory sqlite connection', function () {
    $app = new RenderedInstance(dirname(__DIR__, 2));
    $app->registerInstance('config', new \Voyager\Config\Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
    ]));
    $app->register(new DatabaseServiceProvider($app));

    $db = $app->make('db');
    expect($db)->toBeInstanceOf(DatabaseManager::class)
        ->and($app->make('db'))->toBe($db)
        ->and($db->connection()->select('select 1 as one')[0]->one)->toBe(1);
});
