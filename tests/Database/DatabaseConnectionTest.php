<?php

namespace Tests\Database;

use DateTime;
use ErrorException;
use Exception;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Database\Connection;
use Voyager\Database\Events\QueryExecuted;
use Voyager\Database\Events\TransactionBeginning;
use Voyager\Database\Events\TransactionCommitted;
use Voyager\Database\Events\TransactionCommitting;
use Voyager\Database\Events\TransactionRolledBack;
use Voyager\Database\MultipleColumnsSelectedException;
use Voyager\Database\Query\Builder as BaseBuilder;
use Voyager\Database\Query\Grammars\Grammar;
use Voyager\Database\Query\Processors\Processor;
use Voyager\Database\QueryException;
use Voyager\Database\Schema\Builder;
use Mockery as m;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionClass;
use stdClass;

$dbConnectionMockConnection = function ($methods = [], $pdo = null) {
    $pdo = $pdo ?: new DatabaseConnectionTestMockPDO;
    $defaults = ['getDefaultQueryGrammar', 'getDefaultPostProcessor', 'getDefaultSchemaGrammar'];
    $connection = $this->getMockBuilder(Connection::class)->onlyMethods(array_merge($defaults, $methods))->setConstructorArgs([$pdo])->getMock();
    $connection->enableQueryLog();

    return $connection;
};

test('setting default calls get default grammar', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $mock = m::mock(stdClass::class);
    $connection->expects($this->once())->method('getDefaultQueryGrammar')->willReturn($mock);
    $connection->useDefaultQueryGrammar();
    expect($connection->getQueryGrammar())->toEqual($mock);
});

test('setting default calls get default post processor', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $mock = m::mock(stdClass::class);
    $connection->expects($this->once())->method('getDefaultPostProcessor')->willReturn($mock);
    $connection->useDefaultPostProcessor();
    expect($connection->getPostProcessor())->toEqual($mock);
});

test('select one calls select and returns single result', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['select']);
    $connection->expects($this->once())->method('select')->with('foo', ['bar' => 'baz'])->willReturn(['foo']);
    expect($connection->selectOne('foo', ['bar' => 'baz']))->toBe('foo');
});

test('scalar calls select one and returns single result', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['selectOne']);
    $connection->expects($this->once())->method('selectOne')->with('select count(*) from tbl')->willReturn((object) ['count(*)' => 5]);
    expect($connection->scalar('select count(*) from tbl'))->toBe(5);
});

test('scalar throws exception if multiple columns are selected', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['selectOne']);
    $connection->expects($this->once())->method('selectOne')->with('select a, b from tbl')->willReturn((object) ['a' => 'a', 'b' => 'b']);
    $connection->scalar('select a, b from tbl');
})->throws(MultipleColumnsSelectedException::class);

test('scalar returns null if underlying select returns no rows', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['selectOne']);
    $connection->expects($this->once())->method('selectOne')->with('select foo from tbl where 0=1')->willReturn(null);
    expect($connection->scalar('select foo from tbl where 0=1'))->toBeNull();
});

test('select properly calls PDO', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
    $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
    $writePdo->expects($this->never())->method('prepare');
    $statement = $this->getMockBuilder('PDOStatement')
        ->onlyMethods(['setFetchMode', 'execute', 'fetchAll', 'bindValue'])
        ->getMock();
    $statement->expects($this->once())->method('setFetchMode');
    $statement->expects($this->once())->method('bindValue')->with('foo', 'bar', 2);
    $statement->expects($this->once())->method('execute');
    $statement->expects($this->once())->method('fetchAll')->willReturn(['boom']);
    $pdo->expects($this->once())->method('prepare')->with('foo')->willReturn($statement);
    $mock = $dbConnectionMockConnection->call($this, ['prepareBindings'], $writePdo);
    $mock->setReadPdo($pdo);
    $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->willReturn(['foo' => 'bar']);
    $results = $mock->select('foo', ['foo' => 'bar']);
    expect($results)->toEqual(['boom']);
    $log = $mock->getQueryLog();
    expect($log[0]['query'])->toBe('foo');
    expect($log[0]['bindings'])->toEqual(['foo' => 'bar']);
    $this->assertIsNumeric($log[0]['time']);
});

test('select resultsets returns multiple rowset', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
    $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
    $writePdo->expects($this->never())->method('prepare');
    $statement = $this->getMockBuilder('PDOStatement')
        ->onlyMethods(['setFetchMode', 'execute', 'fetchAll', 'bindValue', 'nextRowset'])
        ->getMock();
    $statement->expects($this->once())->method('setFetchMode');
    $statement->expects($this->once())->method('bindValue')->with(1, 'foo', 2);
    $statement->expects($this->once())->method('execute');
    $statement->expects($this->atLeastOnce())->method('fetchAll')->willReturn(['boom']);
    $statement->expects($this->atLeastOnce())->method('nextRowset')->willReturnCallback(function () {
        static $i = 1;

        return ++$i <= 2;
    });
    $pdo->expects($this->once())->method('prepare')->with('CALL a_procedure(?)')->willReturn($statement);
    $mock = $dbConnectionMockConnection->call($this, ['prepareBindings'], $writePdo);
    $mock->setReadPdo($pdo);
    $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo']))->willReturn(['foo']);
    $results = $mock->selectResultsets('CALL a_procedure(?)', ['foo']);
    expect($results)->toEqual([['boom'], ['boom']]);
    $log = $mock->getQueryLog();
    expect($log[0]['query'])->toBe('CALL a_procedure(?)');
    expect($log[0]['bindings'])->toEqual(['foo']);
    $this->assertIsNumeric($log[0]['time']);
});

test('insert calls the statement method', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['statement']);
    $connection->expects($this->once())->method('statement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn('baz');
    $results = $connection->insert('foo', ['bar']);
    expect($results)->toBe('baz');
});

test('update calls the affecting statement method', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['affectingStatement']);
    $connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn('baz');
    $results = $connection->update('foo', ['bar']);
    expect($results)->toBe('baz');
});

test('delete calls the affecting statement method', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this, ['affectingStatement']);
    $connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn(true);
    $results = $connection->delete('foo', ['bar']);
    expect($results)->toBeTrue();
});

test('statement properly calls PDO', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
    $statement = $this->getMockBuilder('PDOStatement')->onlyMethods(['execute', 'bindValue'])->getMock();
    $statement->expects($this->once())->method('bindValue')->with(1, 'bar', 2);
    $statement->expects($this->once())->method('execute')->willReturn(true);
    $pdo->expects($this->once())->method('prepare')->with($this->equalTo('foo'))->willReturn($statement);
    $mock = $dbConnectionMockConnection->call($this, ['prepareBindings'], $pdo);
    $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['bar']))->willReturn(['bar']);
    $results = $mock->statement('foo', ['bar']);
    expect($results)->toBeTrue();
    $log = $mock->getQueryLog();
    expect($log[0]['query'])->toBe('foo');
    expect($log[0]['bindings'])->toEqual(['bar']);
    $this->assertIsNumeric($log[0]['time']);
});

test('affecting statement properly calls PDO', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
    $statement = $this->getMockBuilder('PDOStatement')->onlyMethods(['execute', 'rowCount', 'bindValue'])->getMock();
    $statement->expects($this->once())->method('bindValue')->with('foo', 'bar', 2);
    $statement->expects($this->once())->method('execute');
    $statement->expects($this->once())->method('rowCount')->willReturn(42);
    $pdo->expects($this->once())->method('prepare')->with('foo')->willReturn($statement);
    $mock = $dbConnectionMockConnection->call($this, ['prepareBindings'], $pdo);
    $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->willReturn(['foo' => 'bar']);
    $results = $mock->update('foo', ['foo' => 'bar']);
    expect($results)->toBe(42);
    $log = $mock->getQueryLog();
    expect($log[0]['query'])->toBe('foo');
    expect($log[0]['bindings'])->toEqual(['foo' => 'bar']);
    $this->assertIsNumeric($log[0]['time']);
});

test('transaction level not incremented on transaction exception', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $pdo->expects($this->once())->method('beginTransaction')->will($this->throwException(new Exception));
    $connection = $dbConnectionMockConnection->call($this, [], $pdo);
    try {
        $connection->beginTransaction();
    } catch (Exception) {
        expect($connection->transactionLevel())->toEqual(0);
    }
});

test('begin transaction method retries on failure', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $pdo->method('beginTransaction')
        ->willReturnOnConsecutiveCalls($this->throwException(new ErrorException('server has gone away')), true);
    $connection = $dbConnectionMockConnection->call($this, ['reconnect'], $pdo);
    $connection->expects($this->once())->method('reconnect');
    $connection->beginTransaction();
    expect($connection->transactionLevel())->toEqual(1);
});

test('begin transaction method reconnects missing connection', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $connection->setReconnector(function ($connection) {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $connection->setPdo($pdo);
    });
    $connection->disconnect();
    $connection->beginTransaction();
    expect($connection->transactionLevel())->toEqual(1);
});

test('begin transaction method never retries if within transaction', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('exec')->will($this->throwException(new Exception));
    $connection = $dbConnectionMockConnection->call($this, ['reconnect'], $pdo);
    $queryGrammar = $this->createMock(Grammar::class);
    $queryGrammar->expects($this->once())->method('compileSavepoint')->willReturn('trans1');
    $queryGrammar->expects($this->once())->method('supportsSavepoints')->willReturn(true);
    $connection->setQueryGrammar($queryGrammar);
    $connection->expects($this->never())->method('reconnect');
    $connection->beginTransaction();
    expect($connection->transactionLevel())->toEqual(1);
    try {
        $connection->beginTransaction();
    } catch (Exception) {
        expect($connection->transactionLevel())->toEqual(1);
    }
});

test('swap PDO with open transaction resets transaction level', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
    $connection = $dbConnectionMockConnection->call($this, [], $pdo);
    $connection->beginTransaction();
    $connection->disconnect();
    expect($connection->transactionLevel())->toEqual(0);
});

test('began transaction fires events if set', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $connection = $dbConnectionMockConnection->call($this, ['getName'], $pdo);
    $connection->expects($this->any())->method('getName')->willReturn('name');
    $connection->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(TransactionBeginning::class));
    $connection->beginTransaction();
});

test('committed fires events if set', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $connection = $dbConnectionMockConnection->call($this, ['getName'], $pdo);
    $connection->expects($this->any())->method('getName')->willReturn('name');
    $connection->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(TransactionCommitted::class));
    $connection->commit();
});

test('committing fires events if set', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $connection = $dbConnectionMockConnection->call($this, ['getName', 'transactionLevel'], $pdo);
    $connection->expects($this->any())->method('getName')->willReturn('name');
    $connection->expects($this->any())->method('transactionLevel')->willReturn(1);
    $connection->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(TransactionCommitting::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(TransactionCommitted::class));
    $connection->commit();
});

test('roll backed fires events if set', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $connection = $dbConnectionMockConnection->call($this, ['getName'], $pdo);
    $connection->expects($this->any())->method('getName')->willReturn('name');
    $connection->beginTransaction();
    $connection->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(TransactionRolledBack::class));
    $connection->rollBack();
});

test('redundant roll back fires no event', function () use ($dbConnectionMockConnection) {
    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $connection = $dbConnectionMockConnection->call($this, ['getName'], $pdo);
    $connection->expects($this->any())->method('getName')->willReturn('name');
    $connection->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldNotReceive('dispatch');
    $connection->rollBack();
});

test('transaction method runs successfully', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['beginTransaction', 'commit'])->getMock();
    $mock = $dbConnectionMockConnection->call($this, [], $pdo);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('commit');
    $result = $mock->transaction(function ($db) {
        return $db;
    });
    expect($result)->toEqual($mock);
});

test('transaction retries on commit deadlock when PDO has active transaction', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
    $mock = $dbConnectionMockConnection->call($this, [], $pdo);

    $pdo->expects($this->exactly(2))->method('beginTransaction');
    $pdo->expects($this->exactly(2))->method('commit')->willReturnOnConsecutiveCalls(
        $this->throwException(new DatabaseConnectionTestMockPDOException('Serialization failure', '40001')),
        true,
    );
    $pdo->method('inTransaction')->willReturn(true);
    $pdo->expects($this->once())->method('rollBack');

    $result = $mock->transaction(function () {
        return 'success';
    }, 2);

    expect($result)->toBe('success');
});

test('transaction retries on serialization failure', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
    $mock = $dbConnectionMockConnection->call($this, [], $pdo);
    $pdo->expects($this->exactly(3))->method('commit')->will($this->throwException(new DatabaseConnectionTestMockPDOException('Serialization failure', '40001')));
    $pdo->expects($this->exactly(3))->method('beginTransaction');
    $pdo->method('inTransaction')->willReturn(true);
    $pdo->expects($this->exactly(2))->method('rollBack');
    $mock->transaction(function () {
    }, 3);
})->throws(PDOException::class, 'Serialization failure');

test('transaction method retries on deadlock', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
    $mock = $dbConnectionMockConnection->call($this, [], $pdo);
    $pdo->method('inTransaction')->willReturn(true);
    $pdo->expects($this->exactly(3))->method('beginTransaction');
    $pdo->expects($this->exactly(3))->method('rollBack');
    $pdo->expects($this->never())->method('commit');
    $mock->transaction(function () {
        throw new QueryException('conn', '', [], new Exception('Deadlock found when trying to get lock'));
    }, 3);
})->throws(QueryException::class, 'Deadlock found when trying to get lock (Connection: conn, SQL: )');

test('transaction method rollsback and throws', function () use ($dbConnectionMockConnection) {
    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
    $mock = $dbConnectionMockConnection->call($this, [], $pdo);
    // $pdo->expects($this->once())->method('inTransaction');
    $pdo->method('inTransaction')->willReturn(true);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('rollBack');
    $pdo->expects($this->never())->method('commit');
    try {
        $mock->transaction(function () {
            throw new Exception('foo');
        });
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('foo');
    }
});

test('on lost connection PDO is not swapped within a transaction', function () {
    $pdo = m::mock(PDO::class);
    $pdo->shouldReceive('beginTransaction')->once();
    $statement = m::mock(PDOStatement::class);
    $pdo->shouldReceive('prepare')->once()->andReturn($statement);
    $statement->shouldReceive('execute')->once()->andThrow(new PDOException('server has gone away'));

    $connection = new Connection($pdo);
    $connection->beginTransaction();
    $connection->statement('foo');
})->throws(QueryException::class, 'server has gone away (Connection: , Host: , Port: , Database: , SQL: foo)');

test('on lost connection PDO is swapped outside transaction', function () {
    $pdo = m::mock(PDO::class);

    $statement = m::mock(PDOStatement::class);
    $statement->shouldReceive('execute')->once()->andThrow(new PDOException('server has gone away'));
    $statement->shouldReceive('execute')->once()->andReturn(true);

    $pdo->shouldReceive('prepare')->twice()->andReturn($statement);

    $connection = new Connection($pdo);

    $called = false;

    $connection->setReconnector(function ($connection) use (&$called) {
        $called = true;
    });

    expect($connection->statement('foo'))->toBeTrue();

    expect($called)->toBeTrue();
});

test('run method retries on failure', function () use ($dbConnectionMockConnection) {
    $method = (new ReflectionClass(Connection::class))->getMethod('run');

    $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
    $mock = $dbConnectionMockConnection->call($this, ['tryAgainIfCausedByLostConnection'], $pdo);
    $mock->expects($this->once())->method('tryAgainIfCausedByLostConnection');

    $method->invokeArgs($mock, ['', [], function () {
        throw new QueryException('', '', [], new Exception);
    }]);
});

test('run method never retries if within transaction', function () use ($dbConnectionMockConnection) {
    $method = (new ReflectionClass(Connection::class))->getMethod('run');

    $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['beginTransaction'])->getMock();
    $mock = $dbConnectionMockConnection->call($this, ['tryAgainIfCausedByLostConnection'], $pdo);
    $pdo->expects($this->once())->method('beginTransaction');
    $mock->expects($this->never())->method('tryAgainIfCausedByLostConnection');
    $mock->beginTransaction();

    $method->invokeArgs($mock, ['', [], function () {
        throw new QueryException('conn', '', [], new Exception);
    }]);
})->throws(QueryException::class, '(Connection: conn, SQL: ) (Connection: , Host: , Port: , Database: , SQL: )');

test('from creates new query builder', function () use ($dbConnectionMockConnection) {
    $conn = $dbConnectionMockConnection->call($this);
    $conn->setQueryGrammar(m::mock(Grammar::class));
    $conn->setPostProcessor(m::mock(Processor::class));
    $builder = $conn->table('users');
    expect($builder)->toBeInstanceOf(BaseBuilder::class);
    expect($builder->from)->toBe('users');
});

test('prepare bindings', function () use ($dbConnectionMockConnection) {
    $date = m::mock(DateTime::class);
    $date->shouldReceive('format')->once()->with('foo')->andReturn('bar');
    $bindings = ['test' => $date];
    $conn = $dbConnectionMockConnection->call($this);
    $grammar = m::mock(Grammar::class);
    $grammar->shouldReceive('getDateFormat')->once()->andReturn('foo');
    $conn->setQueryGrammar($grammar);
    $result = $conn->prepareBindings($bindings);
    expect($result)->toEqual(['test' => 'bar']);
});

test('log query fires events if set', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $connection->logQuery('foo', [], time());
    $connection->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(QueryExecuted::class));
    $connection->logQuery('foo', [], null);
});

test('before executing hooks can be registered', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $connection->beforeExecuting(function () {
        throw new Exception('The callback was fired');
    });
    $connection->select('foo bar', ['baz']);
})->throws(Exception::class, 'The callback was fired');

test('before starting transaction hooks can be registered', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $connection->beforeStartingTransaction(function () {
        throw new Exception('The callback was fired');
    });
    $connection->beginTransaction();
})->throws(Exception::class, 'The callback was fired');

test('pretend only logs queries', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $queries = $connection->pretend(function ($connection) {
        $connection->select('foo bar', ['baz']);
    });
    expect($queries[0]['query'])->toBe('foo bar');
    expect($queries[0]['bindings'])->toEqual(['baz']);
});

test('schema builder can be created', function () use ($dbConnectionMockConnection) {
    $connection = $dbConnectionMockConnection->call($this);
    $schema = $connection->getSchemaBuilder();
    expect($schema)->toBeInstanceOf(Builder::class);
    expect($schema->getConnection())->toBe($connection);
});

test('get raw query log', function () use ($dbConnectionMockConnection) {
    $mock = $dbConnectionMockConnection->call($this, ['getQueryLog']);
    $mock->expects($this->once())->method('getQueryLog')->willReturn([
        [
            'query' => 'select * from tbl where col = ?',
            'bindings' => [
                0 => 'foo',
            ],
            'time' => 1.23,
        ],
    ]);

    $queryGrammar = $this->createMock(Grammar::class);
    $queryGrammar->expects($this->once())
        ->method('substituteBindingsIntoRawSql')
        ->with('select * from tbl where col = ?', ['foo'])
        ->willReturn("select * from tbl where col = 'foo'");
    $mock->setQueryGrammar($queryGrammar);

    $log = $mock->getRawQueryLog();

    expect($log[0]['raw_query'])->toEqual("select * from tbl where col = 'foo'");
    expect($log[0]['time'])->toEqual(1.23);
});

test('query exception contains read connection details when using read pdo', function () {
    // Create write PDO mock that will NOT be used for this query
    $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)
        ->onlyMethods(['prepare'])
        ->getMock();
    $writePdo->expects($this->never())->method('prepare');

    // Create read PDO mock that throws an exception
    $readPdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)
        ->onlyMethods(['prepare'])
        ->getMock();
    $readPdo->expects($this->once())
        ->method('prepare')
        ->willThrowException(new PDOException('Connection refused'));

    // Write configuration (passed to constructor)
    $writeConfig = [
        'driver' => 'mysql',
        'name' => 'mysql',
        'host' => '192.168.1.10',
        'port' => '3306',
        'database' => 'write_db',
    ];

    // Create connection with write config
    $connection = new Connection($writePdo, 'write_db', '', $writeConfig);
    $connection->useDefaultQueryGrammar();
    $connection->useDefaultPostProcessor();

    // Read configuration (different from write)
    $readConfig = [
        'host' => '192.168.1.20',
        'port' => '3307',
        'database' => 'read_db',
    ];

    // Set read PDO and its config
    $connection->setReadPdo($readPdo);
    $connection->setReadPdoConfig($readConfig);

    try {
        $connection->select('SELECT * FROM users', useReadPdo: true);
        $this->fail('Expected QueryException was not thrown');
    } catch (QueryException $e) {
        // Verify the readWriteType is correctly set to 'read'
        expect($e->readWriteType)->toBe('read');

        // Verify connection details show READ config, not write config
        $connectionDetails = $e->getConnectionDetails();
        expect($connectionDetails['host'])->toBe('192.168.1.20');
        expect($connectionDetails['port'])->toBe('3307');
        expect($connectionDetails['database'])->toBe('read_db');
    }
});

test('query exception contains read connection details when read pdo connection fails', function () {
    // Write PDO (won't be used)
    $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)
        ->onlyMethods(['prepare'])
        ->getMock();
    $writePdo->expects($this->never())->method('prepare');

    // Write configuration
    $writeConfig = [
        'driver' => 'mysql',
        'name' => 'mysql',
        'host' => '192.168.1.10',
        'port' => '3306',
        'database' => 'write_db',
    ];

    $connection = new Connection($writePdo, 'write_db', '', $writeConfig);
    $connection->useDefaultQueryGrammar();
    $connection->useDefaultPostProcessor();

    // Read config (different host)
    $readConfig = [
        'host' => '192.168.1.20',
        'port' => '3307',
        'database' => 'read_db',
    ];

    // Simulate lazy PDO that fails during connection (e.g., SET NAMES fails)
    $connection->setReadPdo(function () {
        throw new PDOException('SQLSTATE[HY000] SET NAMES failed');
    });
    $connection->setReadPdoConfig($readConfig);

    try {
        $connection->select('SELECT * FROM users', useReadPdo: true);
        $this->fail('Expected QueryException was not thrown');
    } catch (QueryException $e) {
        expect($e->readWriteType)->toBe('read');

        // Verify connection details show READ config even for connection-time failures
        $connectionDetails = $e->getConnectionDetails();
        expect($connectionDetails['host'])->toBe('192.168.1.20');
        expect($connectionDetails['port'])->toBe('3307');
        expect($connectionDetails['database'])->toBe('read_db');
    }
});

test('query exception contains write connection details when using write pdo', function () {
    // Create write PDO mock that throws an exception
    $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)
        ->onlyMethods(['prepare'])
        ->getMock();
    $writePdo->expects($this->once())
        ->method('prepare')
        ->willThrowException(new PDOException('Connection refused'));

    // Create read PDO mock that will NOT be used
    $readPdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)
        ->onlyMethods(['prepare'])
        ->getMock();
    $readPdo->expects($this->never())->method('prepare');

    // Write configuration (passed to constructor)
    $writeConfig = [
        'driver' => 'mysql',
        'name' => 'mysql',
        'host' => '192.168.1.10',
        'port' => '3306',
        'database' => 'write_db',
    ];

    $connection = new Connection($writePdo, 'write_db', '', $writeConfig);
    $connection->useDefaultQueryGrammar();
    $connection->useDefaultPostProcessor();

    // Read configuration (different from write)
    $readConfig = [
        'host' => '192.168.1.20',
        'port' => '3307',
        'database' => 'read_db',
    ];

    $connection->setReadPdo($readPdo);
    $connection->setReadPdoConfig($readConfig);

    try {
        $connection->select('SELECT * FROM users', useReadPdo: false);
        $this->fail('Expected QueryException was not thrown');
    } catch (QueryException $e) {
        // Verify the readWriteType is correctly set to 'write'
        expect($e->readWriteType)->toBe('write');

        // Verify connection details show WRITE config, not read config
        $connectionDetails = $e->getConnectionDetails();
        expect($connectionDetails['host'])->toBe('192.168.1.10');
        expect($connectionDetails['port'])->toBe('3306');
        expect($connectionDetails['database'])->toBe('write_db');
    }
});

test('query exception contains write connection details when write pdo connection fails', function () {
    // Write configuration
    $writeConfig = [
        'driver' => 'mysql',
        'name' => 'mysql',
        'host' => '192.168.1.10',
        'port' => '3306',
        'database' => 'write_db',
    ];

    // Simulate lazy write PDO that fails during connection (e.g., SET NAMES fails)
    $connection = new Connection(function () {
        throw new PDOException('SQLSTATE[HY000] SET NAMES failed');
    }, 'write_db', '', $writeConfig);
    $connection->useDefaultQueryGrammar();
    $connection->useDefaultPostProcessor();

    // Read config (different host)
    $readConfig = [
        'host' => '192.168.1.20',
        'port' => '3307',
        'database' => 'read_db',
    ];

    $connection->setReadPdo(new DatabaseConnectionTestMockPDO);
    $connection->setReadPdoConfig($readConfig);

    try {
        $connection->select('SELECT * FROM users', useReadPdo: false);
        $this->fail('Expected QueryException was not thrown');
    } catch (QueryException $e) {
        expect($e->readWriteType)->toBe('write');

        // Verify connection details show WRITE config even for connection-time failures
        $connectionDetails = $e->getConnectionDetails();
        expect($connectionDetails['host'])->toBe('192.168.1.10');
        expect($connectionDetails['port'])->toBe('3306');
        expect($connectionDetails['database'])->toBe('write_db');
    }
});

class DatabaseConnectionTestMockPDO extends PDO
{
    public function __construct()
    {
        //
    }
}

class DatabaseConnectionTestMockPDOException extends PDOException
{
    /**
     * Overrides Exception::__construct, which casts $code to integer, so that we can create
     * an exception with a string $code consistent with the real PDOException behavior.
     *
     * @param  string|null  $message
     * @param  string|null  $code
     */
    public function __construct($message = null, $code = null)
    {
        $this->message = $message;
        $this->code = $code;
    }
}
