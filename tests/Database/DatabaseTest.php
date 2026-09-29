<?php

use Voyager\Database\Connection;
use Voyager\Database\DatabaseManager;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\ConnectionResolverInterface;
use Venusian\Tests\Database\Fixtures\Book;
use Venusian\Tests\Database\Fixtures\Author;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

dataset('connections', function () {
    foreach (['sqlite', 'mysql', 'pgsql'] as $connection) {
        if (DatabaseApp::reachable($connection)) {
            yield $connection => [$connection];
        }
    }
});

beforeEach(fn () => $this->app = DatabaseApp::boot());
afterEach(fn () => DatabaseApp::tearDown($this->app, $this));

/** An authors table and a books table of the test's own, with the models pointed at them. */
function library(object $test, string $connection): void
{
    $test->app['config']->set('database.default', $connection);
    Author::$table_name = DatabaseApp::table($test->app, $connection, function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Book::$table_name = DatabaseApp::table($test->app, $connection, function ($table) {
        $table->id();
        $table->foreignId('author_id');
        $table->string('title');
        $table->timestamps();
    });
}

it('binds the manager, the default connection and the schema builder', function () {
    expect($this->app->make('db'))->toBeInstanceOf(DatabaseManager::class)
        ->and($this->app->make(ConnectionResolverInterface::class))->toBe($this->app->make('db'))
        ->and($this->app->make('db.connection'))->toBeInstanceOf(Connection::class)
        ->and($this->app->make('db.schema'))->toBeInstanceOf(Voyager\Database\Schema\Builder::class);
});

it('inserts, reads, updates and deletes through the query builder', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = DatabaseApp::table($this->app, $connection, fn ($t) => [$t->id(), $t->string('name'), $t->integer('score')->default(0)]);

    $db->table($table)->insert([['name' => 'a', 'score' => 1], ['name' => 'b', 'score' => 2]]);
    $db->table($table)->where('name', 'a')->update(['score' => 5]);
    $db->table($table)->where('name', 'b')->delete();

    expect($db->table($table)->count())->toBe(1)
        ->and($db->table($table)->value('score'))->toBe(5)
        ->and($db->table($table)->pluck('name')->all())->toBe(['a']);
})->with('connections');

it('commits a transaction, and rolls one back when its callback throws', function (string $connection) {
    $db = $this->app['db']->connection($connection);
    $table = DatabaseApp::table($this->app, $connection, fn ($t) => [$t->id(), $t->string('name')]);

    $db->transaction(fn () => $db->table($table)->insert(['name' => 'kept']));

    expect(fn () => $db->transaction(function () use ($db, $table) {
        $db->table($table)->insert(['name' => 'rolled back']);

        throw new RuntimeException('undo');
    }))->toThrow(RuntimeException::class, 'undo')
        ->and($db->table($table)->pluck('name')->all())->toBe(['kept']);
})->with('connections');

it('saves models, loads their relations eagerly, and paginates them', function (string $connection) {
    library($this, $connection);

    $author = Author::create(['name' => 'Ursula']);
    $author->books()->createMany([['title' => 'Earthsea'], ['title' => 'Lathe'], ['title' => 'Dispossessed']]);

    $loaded = Author::with('books')->first();
    $page = Book::orderBy('id')->paginate(2);

    expect($loaded->books)->toBeInstanceOf(Collection::class)
        ->and($loaded->books->pluck('title')->all())->toBe(['Earthsea', 'Lathe', 'Dispossessed'])
        ->and(Book::first()->author->name)->toBe('Ursula')
        ->and($page->lastPage())->toBe(2)
        ->and($page->pluck('title')->all())->toBe(['Earthsea', 'Lathe']);
})->with('connections');

it('fires model events, and serializes a model with its relations', function (string $connection) {
    library($this, $connection);
    $heard = [];
    Author::created(function (Author $author) use (&$heard) { $heard[] = "created {$author->name}"; });
    Author::retrieved(function (Author $author) use (&$heard) { $heard[] = "retrieved {$author->name}"; });

    Author::create(['name' => 'Octavia'])->books()->create(['title' => 'Kindred']);
    $copy = unserialize(serialize(Author::with('books')->first()));

    expect($heard)->toBe(['created Octavia', 'retrieved Octavia'])
        ->and($copy->name)->toBe('Octavia')
        ->and($copy->books->first()->title)->toBe('Kindred')
        ->and($copy->exists)->toBeTrue();

    Author::flushEventListeners();
})->with('connections');
