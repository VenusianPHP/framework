<?php

use Voyager\Database\Schema\Blueprint;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

beforeEach(function () {
    $this->app = DatabaseApp::boot();
    $this->table = DatabaseApp::table($this->app, 'sqlite', fn (Blueprint $table) => [$table->id(), $table->string('name')]);
});

afterEach(fn () => DatabaseApp::tearDown($this->app, $this));

it('runs the pipes inside a transaction on the named connection, committing what they wrote', function () {
    $db = $this->app['db']->connection('sqlite');

    $levels = $this->app->make('pipeline')
        ->send([])
        ->through([
            function (array $levels, Closure $next) use ($db) {
                $db->table($this->table)->insert(['name' => 'first']);

                return $next([...$levels, $db->transactionLevel()]);
            },
            fn (array $levels, Closure $next) => $next([...$levels, $db->transactionLevel()]),
        ])
        ->withinTransaction('sqlite')
        ->thenReturn();

    expect($levels)->toBe([1, 1])
        ->and($db->transactionLevel())->toBe(0)
        ->and($db->table($this->table)->pluck('name')->all())->toBe(['first']);
});

it('rolls back what the pipes wrote when one of them throws', function () {
    $db = $this->app['db']->connection('sqlite');
    $this->app['config']->set('database.default', 'sqlite');

    $run = fn () => $this->app->make('pipeline')
        ->send('passable')
        ->through([
            function (string $passable, Closure $next) use ($db) {
                $db->table($this->table)->insert(['name' => 'undone']);

                return $next($passable);
            },
            fn () => throw new DomainException('stop'),
        ])
        ->withinTransaction()
        ->thenReturn();

    expect($run)->toThrow(DomainException::class, 'stop')
        ->and($db->transactionLevel())->toBe(0)
        ->and($db->table($this->table)->count())->toBe(0);
});
