<?php

use Orchestra\Testbench\TestCase;
use Tests\Pipeline\EnumForPipelineTransactionTest;
use Voyager\Database\Events\TransactionBeginning;
use Voyager\Database\Events\TransactionCommitted;
use Voyager\Database\Events\TransactionRolledBack;
use Voyager\NutsAndBolts\MagicAliases\Event;
use Voyager\NutsAndBolts\MagicAliases\Pipeline;

uses(TestCase::class);

test('a pipeline wrapped in a transaction commits once it completes', function () {
    Event::fake();

    $result = Pipeline::withinTransaction()
        ->send('some string')
        ->through([
            fn ($value, $next) => $next($value),
            fn ($value, $next) => $next($value),
        ])
        ->thenReturn();

    expect($result)->toEqual('some string');

    Event::assertDispatchedTimes(TransactionBeginning::class, 1);
    Event::assertDispatchedTimes(TransactionCommitted::class, 1);
});

test('the transaction runs on the given connection', function ($connection, $connectionName) {
    Event::fake();
    config(['database.connections.testing2' => config('database.connections.testing')]);
    config(['database.default' => 'testing2']);

    $result = Pipeline::withinTransaction($connection)
        ->send('some string')
        ->through([
            function ($value, $next) {
                return $next($value);
            },
        ])
        ->thenReturn();

    expect($result)->toEqual('some string');

    Event::dispatched(TransactionBeginning::class, function (TransactionBeginning $event) use ($connectionName) {
        return $event->connection === $connectionName;
    });
})->with([
    'unit enum' => [EnumForPipelineTransactionTest::DEFAULT, 'testing'],
    'string' => ['testing', 'testing'],
    'null' => [null, 'testing2'],
]);

test('an exception thrown inside the pipeline rolls the transaction back', function () {
    Event::fake();

    $finallyRan = false;

    try {
        Pipeline::withinTransaction()
            ->send('some string')
            ->through([
                function ($value, $next) {
                    throw new Exception('I was thrown');
                },
            ])
            ->finally(function () use (&$finallyRan) {
                $finallyRan = true;
            })
            ->thenReturn();

        $this->fail('No exception was thrown');
    } catch (Exception) {
    }

    expect($finallyRan)->toBeTrue();

    Event::assertDispatched(TransactionBeginning::class);
    Event::assertDispatched(TransactionRolledBack::class);
});
