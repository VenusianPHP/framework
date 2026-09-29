<?php

use Venusian\Tests\Graph\Fixtures\GraphApp;
use Venusian\Tests\Graph\Fixtures\Person;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\IOPools\QueryChunk;
use Voyager\Database\Instrument\Collection;

beforeEach(function () {
    if (! GraphApp::reachable()) {
        $this->markTestSkipped('Needs laudis/neo4j-php-client and a Neo4j server.');
    }

    $this->app = GraphApp::boot();
    Person::$label = GraphApp::label();
});

afterEach(function () {
    if (isset($this->app)) {
        GraphApp::tearDown($this->app, $this);
    }
});

/** A transaction callback made outside the test case, so a worker can load its scope. */
function creatingPerson(string $label): Closure
{
    return static function ($worker) use ($label) {
        $worker->table($label)->insert(['id' => 'in-worker', 'name' => 'in worker']);

        return $worker->table($label)->count();
    };
}

/** Runs the loop until $count chunks of $name have been dispatched, or five seconds pass. */
function graphChunks(object $test, string $name, int $count): array
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

it('runs a graph model query in a worker and answers with models', function () {
    Person::create(['id' => 'a', 'name' => 'Ada']);
    Person::create(['id' => 'b', 'name' => 'Bob']);

    $people = Person::orderBy('id')->via()->get();

    expect($people)->toBeInstanceOf(Promise::class)
        ->and($people = $people->wait())->toBeInstanceOf(Collection::class)
        ->and($people->pluck('name')->all())->toBe(['Ada', 'Bob'])
        ->and($people->first()->getTable())->toBe(Person::$label)
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
});

it('creates a model in a worker and keeps the connection\'s calls in order', function () {
    $created = Person::query()->via()->create(['id' => 'a', 'name' => 'Ada']);
    $writes = array_map(fn (int $n): Promise => Person::query()->via()->insert(['id' => "n{$n}", 'name' => "n{$n}"]), range(1, 5));
    $count = Person::query()->via()->count();

    expect($created->wait())->toBeInstanceOf(Person::class)
        ->and($created->wait()->exists)->toBeTrue()
        ->and($count->wait())->toBe(6)
        ->and(Person::count())->toBe(6);

    array_map(fn (Promise $write) => $write->wait(), $writes);
});

it('makes a blocking graph query wait for the offloaded writes on its connection', function () {
    Person::query()->via()->insert(['id' => 'a', 'name' => 'Ada']);

    expect(Person::pluck('id')->all())->toBe(['a']);
});

it('runs a transaction in a worker', function () {
    $count = $this->app['db']->connection('neo4j')->via()->transaction(creatingPerson(Person::$label))->wait();

    expect($count)->toBe(1)
        ->and(Person::pluck('id')->all())->toBe(['in-worker']);
});

it('streams graph models as chunk mail, keyed by id', function () {
    foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
        Person::create(['id' => $id, 'name' => $id]);
    }

    $streamed = Person::query()->stream(2);
    $heard = graphChunks($this, 'db-chunk:neo4j:'.Person::$label, 3);

    expect(array_map(fn (QueryChunk $chunk) => [$chunk->page, $chunk->rows->pluck('id')->all(), $chunk->last], $heard))
        ->toBe([[1, ['a', 'b'], false], [2, ['c', 'd'], false], [3, ['e'], true]])
        ->and($streamed->wait())->toBe(5);
});
