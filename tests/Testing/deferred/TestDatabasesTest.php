<?php

use Voyager\Config\Repository as Config;
use Voyager\NutsAndBolts\MagicAliases\DB;
use Voyager\Testing\Concerns\TestDatabases;
use Voyager\Vessel\Vessel as Container;
use Mockery as m;

$switchToDatabase = function ($database) {
    $instance = new class
    {
        use TestDatabases;
    };

    (new ReflectionMethod($instance, 'switchToDatabase'))->invoke($instance, $database);
};

beforeEach(function () {
    Container::setInstance($container = new Container);

    $container->singleton('config', function () {
        return m::mock(Config::class)
            ->shouldReceive('get')
            ->once()
            ->with('database.default', null)
            ->andReturn('mysql')
            ->getMock();
    });

    $_SERVER['LARAVEL_PARALLEL_TESTING'] = 1;
});

afterEach(function () {
    Container::setInstance(null);
    DB::clearResolvedInstance();
    DB::setMagicAliasApplication(null);

    unset($_SERVER['LARAVEL_PARALLEL_TESTING']);
});

test('switch to database without url', function () use ($switchToDatabase) {
    DB::shouldReceive('purge')->once();

    config()->shouldReceive('get')
        ->once()
        ->with('database.connections.mysql.url', false)
        ->andReturn(false);

    config()->shouldReceive('set')
        ->once()
        ->with('database.connections.mysql.database', 'my_database_test_1');

    $switchToDatabase('my_database_test_1');
});

test('switch to database with url', function ($testDatabase, $url, $testUrl) use ($switchToDatabase) {
    DB::shouldReceive('purge')->once();

    config()->shouldReceive('get')
        ->once()
        ->with('database.connections.mysql.url', false)
        ->andReturn($url);

    config()->shouldReceive('set')
        ->once()
        ->with('database.connections.mysql.url', $testUrl);

    $switchToDatabase($testDatabase);
})->with([
    [
        'my_database_test_1',
        'mysql://root:@127.0.0.1/my_database?charset=utf8mb4',
        'mysql://root:@127.0.0.1/my_database_test_1?charset=utf8mb4',
    ],
    [
        'my_database_test_1',
        'mysql://my-user:@localhost/my_database',
        'mysql://my-user:@localhost/my_database_test_1',
    ],
    [
        'my-database_test_1',
        'postgresql://my_database_user:@127.0.0.1/my-database?charset=utf8',
        'postgresql://my_database_user:@127.0.0.1/my-database_test_1?charset=utf8',
    ],
]);
