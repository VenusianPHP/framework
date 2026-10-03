<?php

use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\IOPools\QueryGig;
use Voyager\Database\IOPools\QueryChunk;
use Voyager\Database\Instrument\Collection;
use Voyager\Contracts\IOPools\WorkerPools\RemoteException;
use Venusian\Tests\Database\Fixtures\Book;
use Venusian\Tests\Database\Fixtures\Author;
use Venusian\Tests\Database\Fixtures\DatabaseApp;
use Venusian\Tests\Log\Fixtures\RecordingPool;

dataset('connections', function () {
    foreach (['sqlite', 'mysql', 'pgsql'] as $connection) {
        if (DatabaseApp::reachable($connection)) {
            yield $connection => [$connection];
        }
    }
});

dataset('servers', function () {
    foreach (['mysql' => 'select sleep(1)', 'pgsql' => 'select pg_sleep(1)'] as $connection => $sleep) {
        if (DatabaseApp::reachable($connection)) {
            yield $connection => [$connection, $sleep];
        }
    }
});

beforeEach(fn () => $this->app = DatabaseApp::boot());
afterEach(fn () => DatabaseApp::tearDown($this->app, $this));

function names(object $test, string $connection): string
{
    return DatabaseApp::table($test->app, $connection, fn ($table) => [$table->id(), $table->string('name'), $table->timestamps()]);
}

/**
 * Transaction callbacks made outside the test case: a closure keeps the class it was made in, and
 * a worker can't load Pest's test-case classes. An app's own classes load there as usual.
 */
function committing(string $table): Closure
{
    return static function ($worker) use ($table) {
        $worker->table($table)->insert(['name' => 'committed']);

        return $worker->table($table)->count();
    };
}

function throwing(string $table): Closure
{
    return static function ($worker) use ($table) {
        $worker->table($table)->insert(['name' => 'rolled back']);

        throw new DomainException('undo');
    };
}

/** Runs the loop until $count chunks of $name have been dispatched, or five seconds pass. */
function dbChunks(object $test, string $name, int $count): array
{
    $heard = [];
    $loop = $test->app->get(Loop::class);

    $test->app->get('signals')->listen($name, function (QueryChunk $chunk) use (&$heard, $count, $loop) {
        $heard[] = $chunk;

        if (count($heard) === $count) {
            $loop->stop();
        }
    });

    $loop->at(5, fn () => $loop->stop());
    $loop->run();

    return $heard;
}

it('runs a query\'s terminal in a worker and answers with a promise', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = names($this, $connection);
    $db->table($table)->insert([['name' => 'a'], ['name' => 'b']]);

    $promise = $db->table($table)->orderBy('id')->via()->pluck('name');

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and($promise->wait()->all())->toBe(['a', 'b'])
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
})->with('connections');

it('keeps a connection\'s calls in order: a read after offloaded writes sees them', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = names($this, $connection);

    $writes = array_map(fn (int $n): Promise => $db->table($table)->via()->insert(['name' => "row {$n}"]), range(1, 12));
    $count = $db->table($table)->via()->count();

    expect($count->wait())->toBe(12);
    array_map(fn (Promise $write) => $write->wait(), $writes);
})->with('connections');

it('makes a blocking query wait for the offloaded writes made on its connection', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = names($this, $connection);

    $db->table($table)->via()->insert(['name' => 'offloaded']);

    expect($db->table($table)->value('name'))->toBe('offloaded');
})->with('connections');

it('runs offloaded reads side by side', function (string $connection, string $sleep) {
    $db = $this->app['db']->connection($connection);
    $this->app->get('process-workers')->warm(2);
    $started = hrtime(true);

    $a = $db->via()->select($sleep);
    $b = $db->via()->select($sleep);
    $a->wait();
    $b->wait();

    // One after the other is two seconds at least; side by side is one, plus each worker's first
    // connect, which alone has measured up to 0.65 s here. Under 1.8 s is only possible in parallel.
    expect(hrtime(true) - $started)->toBeLessThan(1_800_000_000);
})->with('servers');

it('runs a write alone: it waits for the reads before it', function (string $connection, string $sleep) {
    $db = $this->app['db']->connection($connection);
    $table = names($this, $connection);
    $this->app->get('process-workers')->warm(2);
    $started = hrtime(true);

    $read = $db->via()->select($sleep);
    $write = $db->table($table)->via()->insert(['name' => 'after the read']);
    $write->wait();

    expect($read->settled())->toBeTrue()
        ->and(hrtime(true) - $started)->toBeGreaterThan(990_000_000);
})->with('servers');

it('brings models back from a worker, and fires retrieved here, once each', function (string $connection) {
    $this->app['config']->set('database.default', $connection);
    Author::$table_name = names($this, $connection);
    Author::create(['name' => 'Ursula']);
    Author::create(['name' => 'Octavia']);
    $heard = [];
    Author::retrieved(function (Author $author) use (&$heard) { $heard[] = $author->name; });

    $authors = Author::orderBy('id')->via()->get()->wait();

    expect($authors)->toBeInstanceOf(Collection::class)
        ->and($authors->first())->toBeInstanceOf(Author::class)
        ->and($authors->pluck('name')->all())->toBe(['Ursula', 'Octavia'])
        ->and($heard)->toBe(['Ursula', 'Octavia'])
        ->and($authors->first()->exists)->toBeTrue();

    Author::flushEventListeners();
})->with('connections');

it('runs a whole transaction in a worker, and rolls it back there when it throws', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = names($this, $connection);

    $count = $db->via()->transaction(committing($table))->wait();
    $failed = $db->via()->transaction(throwing($table));

    expect($count)->toBe(1)
        ->and(fn () => $failed->wait())->toThrow(RemoteException::class, 'undo')
        ->and($db->table($table)->pluck('name')->all())->toBe(['committed']);
})->with('connections');

it('streams rows as chunk mail, page by page, the last one marked', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = names($this, $connection);
    $db->table($table)->insert(array_map(fn (int $n) => ['name' => "row {$n}"], range(1, 25)));

    $streamed = $db->table($table)->stream(10);
    $heard = dbChunks($this, "db-chunk:{$connection}:{$table}", 3);

    expect(array_map(fn (QueryChunk $chunk) => [$chunk->page, $chunk->rows->count(), $chunk->last], $heard))
        ->toBe([[1, 10, false], [2, 10, false], [3, 5, true]])
        ->and($streamed->wait())->toBe(25);
})->with('connections');

it('marks the last full page last, and streams a query with no rows as one empty last chunk', function () {
    $db = $this->app['db']->connection('sqlite');
    $full = names($this, 'sqlite');
    $empty = names($this, 'sqlite');
    $db->table($full)->insert(array_map(fn (int $n) => ['name' => "row {$n}"], range(1, 20)));

    $db->table($full)->stream(10);
    $pages = dbChunks($this, "db-chunk:sqlite:{$full}", 2);

    $db->table($empty)->stream(10);
    $none = dbChunks($this, "db-chunk:sqlite:{$empty}", 1);

    expect(array_map(fn (QueryChunk $chunk) => [$chunk->page, $chunk->rows->count(), $chunk->last], $pages))->toBe([[1, 10, false], [2, 10, true]])
        ->and(array_map(fn (QueryChunk $chunk) => [$chunk->page, $chunk->rows->count(), $chunk->last], $none))->toBe([[1, 0, true]]);
});

it('streams models, firing retrieved here', function () {
    $this->app['config']->set('database.default', 'sqlite');
    Author::$table_name = names($this, 'sqlite');
    Author::create(['name' => 'Ursula']);
    $heard = [];
    Author::retrieved(function (Author $author) use (&$heard) { $heard[] = $author->name; });

    Author::query()->stream();
    $chunks = dbChunks($this, 'db-chunk:sqlite:'.Author::$table_name, 1);

    expect($chunks[0]->rows)->toBeInstanceOf(Collection::class)
        ->and($chunks[0]->rows->first())->toBeInstanceOf(Author::class)
        ->and($heard)->toBe(['Ursula']);

    Author::flushEventListeners();
});

it('refuses what a worker can\'t do', function () {
    $db = $this->app['db']->connection('sqlite');
    $table = names($this, 'sqlite');

    $this->app['config']->set('database.connections.memory', ['driver' => 'sqlite', 'database' => ':memory:']);
    $memory = $this->app['db']->connection('memory');

    expect(fn () => $db->table($table)->via()->chunk(10, fn () => null))->toThrow(BadMethodCallException::class, 'chunk() takes a callback or yields')
        ->and(fn () => $db->via()->beginTransaction())->toThrow(BadMethodCallException::class, 'beginTransaction() does not cross a worker')
        ->and(fn () => $memory->via())->toThrow(LogicException::class, 'is an in-memory SQLite database')
        ->and(fn () => $db->transaction(fn () => $db->table($table)->via()))->toThrow(LogicException::class, 'has a transaction open')
        ->and(fn () => $db->table($table)->via()->where('name', 'x')->wait())->toThrow(LogicException::class, 'where() builds the query rather than running it');
});

it('offloads to the thread workers when they are on', function () {
    $threads = new RecordingPool($this->app->get(Loop::class));
    $this->app->registerInstance('thread-workers', $threads);
    $table = names($this, 'sqlite');

    $this->app['db']->connection('sqlite')->table($table)->via()->count()->wait();

    expect($threads->gigs)->toHaveCount(1)
        ->and($threads->gigs[0])->toBeInstanceOf(QueryGig::class)
        ->and($this->app->get('process-workers')->workerCount())->toBe(0);
});
