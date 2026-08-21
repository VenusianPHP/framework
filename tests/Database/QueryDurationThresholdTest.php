<?php

use Carbon\CarbonInterval;
use Voyager\Database\Connection;
use Voyager\Events\Dispatcher;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\DataObjects\Carbon;

afterEach(function () {
    Carbon::setTestNow(null);
});

test('it can handle reaching a duration threshold in the db', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = 0;
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1.1), function () use (&$called) {
        $called++;
    });

    $connection->logQuery('xxxx', [], 1.0);
    $connection->logQuery('xxxx', [], 0.1);
    expect($called)->toBe(0);

    $connection->logQuery('xxxx', [], 0.1);
    expect($called)->toBe(1);
});

test('it is only called once', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = 0;
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1), function () use (&$called) {
        $called++;
    });

    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);

    expect($called)->toBe(1);
});

test('it is only called once when given date time', function () {
    Carbon::setTestNow($now = Carbon::create(2017, 6, 27, 13, 14, 15, 'UTC'));

    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = 0;
    $connection->whenQueryingForLongerThan($now->addMilliseconds(1), function () use (&$called) {
        $called++;
    });

    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);

    expect($called)->toBe(1);
});

test('it can specify multiple handlers with the same intervals', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = [];
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1), function () use (&$called) {
        $called['a'] = true;
    });
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1), function () use (&$called) {
        $called['b'] = true;
    });

    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);

    expect($called)->toBe([
        'a' => true,
        'b' => true,
    ]);
});

test('it can specify multiple handlers with different intervals', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = [];
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1), function () use (&$called) {
        $called['a'] = true;
    });
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(2), function () use (&$called) {
        $called['b'] = true;
    });

    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    expect($called)->toBe([
        'a' => true,
    ]);

    $connection->logQuery('xxxx', [], 1);
    expect($called)->toBe([
        'a' => true,
        'b' => true,
    ]);
});

test('it has access to connection in handler', function () {
    $connection = new Connection(new PDO('sqlite::memory:'), '', '', ['name' => 'expected-name']);
    $connection->setEventDispatcher(new Dispatcher());
    $name = null;
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1), function ($connection) use (&$name) {
        $name = $connection->getName();
    });

    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);

    expect($name)->toBe('expected-name');
});

test('it has specify threshold with float', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = false;
    $connection->whenQueryingForLongerThan(1.1, function () use (&$called) {
        $called = true;
    });

    $connection->logQuery('xxxx', [], 1.1);
    expect($called)->toBeFalse();

    $connection->logQuery('xxxx', [], 0.1);
    expect($called)->toBeTrue();
});

test('it has specify threshold with int', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = false;
    $connection->whenQueryingForLongerThan(2, function () use (&$called) {
        $called = true;
    });

    $connection->logQuery('xxxx', [], 1.1);
    expect($called)->toBeFalse();

    $connection->logQuery('xxxx', [], 1.0);
    expect($called)->toBeTrue();
});

test('it can reset total query duration', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());

    $connection->logQuery('xxxx', [], 1.1);
    expect($connection->totalQueryDuration())->toBe(1.1);
    $connection->logQuery('xxxx', [], 1.1);
    expect($connection->totalQueryDuration())->toBe(2.2);

    $connection->resetTotalQueryDuration();
    expect($connection->totalQueryDuration())->toBe(0.0);
});

test('it can restore already run handlers', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $called = 0;
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(1), function () use (&$called) {
        $called++;
    });

    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    expect($called)->toBe(1);

    $connection->allowQueryDurationHandlersToRunAgain();
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    expect($called)->toBe(2);

    $connection->allowQueryDurationHandlersToRunAgain();
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    $connection->logQuery('xxxx', [], 1);
    expect($called)->toBe(3);
});

test('it can access all queries when query logging is active', function () {
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->setEventDispatcher(new Dispatcher());
    $connection->enableQueryLog();
    $queries = [];
    $connection->whenQueryingForLongerThan(CarbonInterval::milliseconds(2), function ($connection, $event) use (&$queries) {
        $queries = Arr::pluck($connection->getQueryLog(), 'query');
        $queries[] = $event->sql;
    });

    $connection->logQuery('foo', [], 1);
    $connection->logQuery('bar', [], 1);
    $connection->logQuery('baz', [], 1);

    expect($queries)->toBe([
        'foo',
        'bar',
        'baz',
    ]);
});
