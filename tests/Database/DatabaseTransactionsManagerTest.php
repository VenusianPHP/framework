<?php

use Voyager\Database\DatabaseTransactionsManager;

test('beginning transactions', function () {
    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);
    $manager->begin('default', 2);
    $manager->begin('admin', 1);

    $this->assertCount(3, $manager->getPendingTransactions());
    $this->assertSame('default', $manager->getPendingTransactions()[0]->connection);
    $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);
    $this->assertSame('default', $manager->getPendingTransactions()[1]->connection);
    $this->assertEquals(2, $manager->getPendingTransactions()[1]->level);
    $this->assertSame('admin', $manager->getPendingTransactions()[2]->connection);
    $this->assertEquals(1, $manager->getPendingTransactions()[2]->level);
});

test('rolling back transactions', function () {
    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);
    $manager->begin('default', 2);
    $manager->begin('admin', 1);

    $manager->rollback('default', 1);

    $this->assertCount(2, $manager->getPendingTransactions());

    $this->assertSame('default', $manager->getPendingTransactions()[0]->connection);
    $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);

    $this->assertSame('admin', $manager->getPendingTransactions()[1]->connection);
    $this->assertEquals(1, $manager->getPendingTransactions()[1]->level);
});

test('rolling back transactions all the way', function () {
    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);
    $manager->begin('default', 2);
    $manager->begin('admin', 1);

    $manager->rollback('default', 0);

    $this->assertCount(1, $manager->getPendingTransactions());

    $this->assertSame('admin', $manager->getPendingTransactions()[0]->connection);
    $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);
});

test('committing transactions', function () {
    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);
    $manager->begin('default', 2);
    $manager->begin('admin', 1);
    $manager->begin('admin', 2);

    $manager->commit('default', 2, 1);
    $executedTransactions = $manager->commit('default', 1, 0);

    $executedAdminTransactions = $manager->commit('admin', 2, 1);

    $this->assertCount(1, $manager->getPendingTransactions()); // One pending "admin" transaction left...
    $this->assertCount(2, $executedTransactions); // Two committed transactions on "default"
    $this->assertCount(0, $executedAdminTransactions); // Zero executed committed transactions on "default"

    // Level 2 "admin" callback has been staged...
    $this->assertSame('admin', $manager->getCommittedTransactions()[0]->connection);
    $this->assertEquals(2, $manager->getCommittedTransactions()[0]->level);

    // Level 1 "admin" callback still pending...
    $this->assertSame('admin', $manager->getPendingTransactions()[0]->connection);
    $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);
});

test('callbacks are added to the current transaction', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);

    $manager->addCallback(function () use (&$callbacks) {
    });

    $manager->begin('default', 2);

    $manager->begin('admin', 1);

    $manager->addCallback(function () use (&$callbacks) {
    });

    $this->assertCount(1, $manager->getPendingTransactions()[0]->getCallbacks());
    $this->assertCount(0, $manager->getPendingTransactions()[1]->getCallbacks());
    $this->assertCount(1, $manager->getPendingTransactions()[2]->getCallbacks());
});

test('callbacks run in fifo order', function () {
    $manager = new DatabaseTransactionsManager;

    $order = [];

    $manager->begin('default', 1);

    $manager->addCallback(function () use (&$order) {
        $order[] = 1;
    });

    $manager->addCallback(function () use (&$order) {
        $order[] = 2;
    });

    $manager->addCallback(function () use (&$order) {
        $order[] = 3;
    });

    $manager->commit('default', 1, 0);

    expect($order)->toBe([1, 2, 3]);
});

test('committing transactions executes callbacks', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);

    $manager->addCallback(function () use (&$callbacks) {
        $callbacks[] = ['default', 1];
    });

    $manager->begin('default', 2);

    $manager->addCallback(function () use (&$callbacks) {
        $callbacks[] = ['default', 2];
    });

    $manager->begin('admin', 1);

    $manager->commit('default', 2, 1);
    $manager->commit('default', 1, 0);

    $this->assertCount(2, $callbacks);
    $this->assertEquals(['default', 2], $callbacks[0]);
    $this->assertEquals(['default', 1], $callbacks[1]);
});

test('committing executes only callbacks of the connection', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);

    $manager->addCallback(function () use (&$callbacks) {
        $callbacks[] = ['default', 1];
    });

    $manager->begin('default', 2);
    $manager->begin('admin', 1);

    $manager->addCallback(function () use (&$callbacks) {
        $callbacks[] = ['admin', 1];
    });

    $manager->commit('default', 2, 1);
    $manager->commit('default', 1, 0);

    $this->assertCount(1, $callbacks);
    $this->assertEquals(['default', 1], $callbacks[0]);
});

test('callback is executed if no transactions', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->addCallback(function () use (&$callbacks) {
        $callbacks[] = ['default', 1];
    });

    $this->assertCount(1, $callbacks);
    $this->assertEquals(['default', 1], $callbacks[0]);
});

test('callbacks for rollback are added to the current transaction', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);

    $manager->addCallbackForRollback(function () use (&$callbacks) {
    });

    $manager->begin('default', 2);

    $manager->begin('admin', 1);

    $manager->addCallbackForRollback(function () use (&$callbacks) {
    });

    $this->assertCount(1, $manager->getPendingTransactions()[0]->getCallbacksForRollback());
    $this->assertCount(0, $manager->getPendingTransactions()[1]->getCallbacksForRollback());
    $this->assertCount(1, $manager->getPendingTransactions()[2]->getCallbacksForRollback());
});

test('rollback transactions executes callbacks', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);

    $manager->addCallbackForRollback(function () use (&$callbacks) {
        $callbacks[] = ['default', 1];
    });

    $manager->begin('default', 2);

    $manager->addCallbackForRollback(function () use (&$callbacks) {
        $callbacks[] = ['default', 2];
    });

    $manager->begin('admin', 1);

    $manager->rollback('default', 1);
    $manager->rollback('default', 0);

    $this->assertCount(2, $callbacks);
    $this->assertEquals(['default', 2], $callbacks[0]);
    $this->assertEquals(['default', 1], $callbacks[1]);
});

test('rollback executes only callbacks of the connection', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);

    $manager->addCallbackForRollback(function () use (&$callbacks) {
        $callbacks[] = ['default', 1];
    });

    $manager->begin('default', 2);
    $manager->begin('admin', 1);

    $manager->addCallbackForRollback(function () use (&$callbacks) {
        $callbacks[] = ['admin', 1];
    });

    $manager->rollback('default', 1);
    $manager->rollback('default', 0);

    $this->assertCount(1, $callbacks);
    $this->assertEquals(['default', 1], $callbacks[0]);
});

test('callback for rollback is not executed if no transactions', function () {
    $callbacks = [];

    $manager = new DatabaseTransactionsManager;

    $manager->addCallbackForRollback(function () use (&$callbacks) {
        $callbacks[] = ['default', 1];
    });

    $this->assertCount(0, $callbacks);
});

test('stage transactions', function () {
    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);
    $manager->begin('admin', 1);

    $this->assertCount(2, $manager->getPendingTransactions());

    $pendingTransactions = $manager->getPendingTransactions();

    $this->assertEquals(1, $pendingTransactions[0]->level);
    $this->assertEquals('default', $pendingTransactions[0]->connection);
    $this->assertEquals(1, $pendingTransactions[1]->level);
    $this->assertEquals('admin', $pendingTransactions[1]->connection);

    $manager->stageTransactions('default', 1);

    $this->assertCount(1, $manager->getPendingTransactions());
    $this->assertCount(1, $manager->getCommittedTransactions());
    $this->assertEquals('default', $manager->getCommittedTransactions()[0]->connection);

    $manager->stageTransactions('admin', 1);

    $this->assertCount(0, $manager->getPendingTransactions());
    $this->assertCount(2, $manager->getCommittedTransactions());
    $this->assertEquals('admin', $manager->getCommittedTransactions()[1]->connection);
});

test('stage transactions only stages the transactions at or above the given level', function () {
    $manager = new DatabaseTransactionsManager;

    $manager->begin('default', 1);
    $manager->begin('default', 2);
    $manager->begin('default', 3);
    $manager->stageTransactions('default', 2);

    $this->assertCount(1, $manager->getPendingTransactions());
    $this->assertCount(2, $manager->getCommittedTransactions());
});
