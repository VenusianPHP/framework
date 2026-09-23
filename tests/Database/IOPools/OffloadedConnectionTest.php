<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Core\RenderedInstance;
use Voyager\Database\Connection;
use Voyager\Database\DatabaseServiceProvider;
use Voyager\Database\IOPools\ConnectionGig;
use Voyager\Database\IOPools\OffloadedConnection;
use Voyager\Graph\Database\Neo4jConnection;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\WorkTargetManager;

beforeEach(function () {
    $this->app = RenderedInstance::setInstance(new RenderedInstance(dirname(__DIR__, 3)));
    $this->app->registerInstance('config', new Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'io-pools' => ['work' => ['default' => 'sync']],
    ]));
    $this->app->registerInstance(Loop::class, new EventLoop);
    $this->app->registerInstance('work-targets', new WorkTargetManager($this->app));
    $this->app->register(new DatabaseServiceProvider($this->app));
    $this->db = $this->app['db']->connection();
    $this->db->statement('create table t (id integer primary key, v text)');
});

afterEach(fn () => RenderedInstance::setInstance(null));

it('a connection gig runs the statement on the named connection', function () {
    $gig = unserialize(serialize(new ConnectionGig('sqlite', 'insert', ['insert into t (v) values (?)', ['x']])));

    expect($gig->handle())->toBeTrue()->and($this->db->table('t')->count())->toBe(1);
});

it('via() offloads raw statements and answers with the same values', function () {
    expect($this->db->via())->toBeInstanceOf(OffloadedConnection::class);

    $insert = $this->db->via()->insert('insert into t (v) values (?), (?)', ['a', 'b']);
    expect($insert)->toBeInstanceOf(Promise::class)->and($insert->wait())->toBeTrue()
        ->and($this->db->via()->select('select v from t order by id')->wait())->toHaveCount(2)
        ->and($this->db->via()->scalar('select count(*) from t')->wait())->toBe(2)
        ->and($this->db->via()->update('update t set v = ? where v = ?', ['z', 'a'])->wait())->toBe(1)
        ->and($this->db->via()->delete('delete from t')->wait())->toBe(2);
});

it('refuses transactions and the pdo', function () {
    foreach (['transaction', 'beginTransaction', 'commit', 'rollBack', 'getPdo', 'getReadPdo', 'cursor'] as $method) {
        expect(fn () => $this->db->via()->{$method}())->toThrow(BadMethodCallException::class);
    }
});

it('unnamed connection refuses via()', function () {
    expect(fn () => (new Connection(new PDO('sqlite::memory:')))->via())->toThrow(LogicException::class);
});

it('neo4j inherits via()', function () {
    expect(is_subclass_of(Neo4jConnection::class, Connection::class))->toBeTrue()
        ->and(method_exists(Neo4jConnection::class, 'via'))->toBeTrue();
});
