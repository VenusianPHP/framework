<?php

namespace Tests\Database;

use Exception;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\DatabaseTransactionsManager;
use Mockery as m;
use Throwable;

function dbTransactionsCreateSchema()
{
    foreach (['default', 'second_connection'] as $connection) {
        dbTransactionsSchema($connection)->create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('value')->nullable();
        });
    }
}

function dbTransactionsSchema($connection = 'default')
{
    return dbTransactionsConnection($connection)->getSchemaBuilder();
}

function dbTransactionsConnection($name = 'default')
{
    return DB::connection($name);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ], 'second_connection');

    $db->setAsGlobal();

    dbTransactionsCreateSchema();
});

afterEach(function () {
    foreach (['default', 'second_connection'] as $connection) {
        dbTransactionsSchema($connection)->drop('users');
    }

    parent::tearDown();
});

test('transaction is recorded and committed', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('commit')->once()->with('default', 1, 0);

    dbTransactionsConnection()->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    dbTransactionsConnection()->transaction(function () {
        dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
            'value' => 2,
        ]);
    });
});

test('transaction is recorded and committed using the separate methods', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('commit')->once()->with('default', 1, 0);

    dbTransactionsConnection()->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    dbTransactionsConnection()->beginTransaction();
    dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
        'value' => 2,
    ]);
    dbTransactionsConnection()->commit();
});

test('nested transaction is recorded and committed', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('begin')->once()->with('default', 2);
    $transactionManager->shouldReceive('commit')->once()->with('default', 2, 1);
    $transactionManager->shouldReceive('commit')->once()->with('default', 1, 0);

    dbTransactionsConnection()->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    dbTransactionsConnection()->transaction(function () {
        dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
            'value' => 2,
        ]);

        dbTransactionsConnection()->transaction(function () {
            dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
                'value' => 2,
            ]);
        });
    });
});

test('nested transaction is recorde for different connectionsd and committed', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('begin')->once()->with('second_connection', 1);
    $transactionManager->shouldReceive('begin')->once()->with('second_connection', 2);
    $transactionManager->shouldReceive('commit')->once()->with('default', 1, 0);
    $transactionManager->shouldReceive('commit')->once()->with('second_connection', 2, 1);
    $transactionManager->shouldReceive('commit')->once()->with('second_connection', 1, 0);

    dbTransactionsConnection()->setTransactionManager($transactionManager);
    dbTransactionsConnection('second_connection')->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    dbTransactionsConnection()->transaction(function () {
        dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
            'value' => 2,
        ]);

        dbTransactionsConnection('second_connection')->transaction(function () {
            dbTransactionsConnection('second_connection')->table('users')->where(['name' => 'zain'])->update([
                'value' => 2,
            ]);

            dbTransactionsConnection('second_connection')->transaction(function () {
                dbTransactionsConnection('second_connection')->table('users')->where(['name' => 'zain'])->update([
                    'value' => 2,
                ]);
            });
        });
    });
});

test('transaction is rolled back', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('rollback')->once()->with('default', 0);
    $transactionManager->shouldNotReceive('commit');

    dbTransactionsConnection()->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    try {
        dbTransactionsConnection()->transaction(function () {
            dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
                'value' => 2,
            ]);

            throw new Exception;
        });
    } catch (Throwable) {
    }
});

test('transaction is rolled back using separate methods', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('rollback')->once()->with('default', 0);
    $transactionManager->shouldNotReceive('commit', 1, 0);

    dbTransactionsConnection()->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    dbTransactionsConnection()->beginTransaction();

    dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
        'value' => 2,
    ]);

    dbTransactionsConnection()->rollBack();
});

test('nested transactions are rolled back', function () {
    $transactionManager = m::mock(new DatabaseTransactionsManager);
    $transactionManager->shouldReceive('begin')->once()->with('default', 1);
    $transactionManager->shouldReceive('begin')->once()->with('default', 2);
    $transactionManager->shouldReceive('rollback')->once()->with('default', 1);
    $transactionManager->shouldReceive('rollback')->once()->with('default', 0);
    $transactionManager->shouldNotReceive('commit');

    dbTransactionsConnection()->setTransactionManager($transactionManager);

    dbTransactionsConnection()->table('users')->insert([
        'name' => 'zain', 'value' => 1,
    ]);

    try {
        dbTransactionsConnection()->transaction(function () {
            dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
                'value' => 2,
            ]);

            dbTransactionsConnection()->transaction(function () {
                dbTransactionsConnection()->table('users')->where(['name' => 'zain'])->update([
                    'value' => 2,
                ]);

                throw new Exception;
            });
        });
    } catch (Throwable) {
    }
});

