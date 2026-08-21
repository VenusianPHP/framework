<?php

use Tests\System\Stubs\TestingDatabaseTransactionsManagerTestObject;
use Voyager\System\Testing\DatabaseTransactionsManager;

test('a callback runs immediately when there is only one transaction', function () {
    $testObject = new TestingDatabaseTransactionsManagerTestObject;
    $manager = new DatabaseTransactionsManager([null]);

    $manager->begin('foo', 1);

    $manager->addCallback(fn () => $testObject->handle());

    expect($testObject->ran)->toBeTrue()
        ->and($testObject->runs)->toEqual(1);
});

test('the base transaction is ignored for callback applicable transactions', function () {
    $manager = new DatabaseTransactionsManager([null]);

    $manager->begin('foo', 1);
    $manager->begin('foo', 2);

    expect($manager->callbackApplicableTransactions())->toHaveCount(1)
        ->and($manager->callbackApplicableTransactions()[0]->level)->toEqual(2);
});

test('committing does not remove the base pending transaction', function () {
    $manager = new DatabaseTransactionsManager([null]);

    $manager->begin('foo', 1);

    $manager->begin('foo', 2);
    $manager->commit('foo', 2, 1);

    expect($manager->callbackApplicableTransactions())->toHaveCount(0);

    $manager->begin('foo', 2);

    expect($manager->callbackApplicableTransactions())->toHaveCount(1)
        ->and($manager->callbackApplicableTransactions()[0]->level)->toEqual(2);
});

test('a callback added inside the second transaction runs once both commit', function () {
    $testObject = new TestingDatabaseTransactionsManagerTestObject;
    $manager = new DatabaseTransactionsManager([null]);
    $manager->begin('foo', 1);
    $manager->begin('foo', 2);

    $manager->addCallback(fn () => $testObject->handle());

    expect($testObject->ran)->toBeFalse();

    $manager->commit('foo', 2, 1);
    $manager->commit('foo', 1, 0);

    expect($testObject->ran)->toBeTrue()
        ->and($testObject->runs)->toEqual(1);
});

test('after commit callbacks are executed at level one only', function () {
    $manager = new DatabaseTransactionsManager([null]);

    expect($manager->afterCommitCallbacksShouldBeExecuted(0))->toBeFalse()
        ->and($manager->afterCommitCallbacksShouldBeExecuted(1))->toBeTrue()
        ->and($manager->afterCommitCallbacksShouldBeExecuted(2))->toBeFalse();
});

test('the number of connections transacting is skipped', function () {
    $manager = new DatabaseTransactionsManager([null]);

    $manager->begin('foo', 1);
    $manager->begin('foo', 2);

    expect($manager->callbackApplicableTransactions())->toHaveCount(1);
});
