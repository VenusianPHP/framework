<?php

use Venusian\Tests\Graph\Fixtures\GraphApp;
use Venusian\Tests\Graph\Fixtures\Person;
use Voyager\Graph\Database\Neo4jConnection;

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

it('resolves the neo4j connection from database.connections', function () {
    expect($this->app['db']->connection('neo4j'))->toBeInstanceOf(Neo4jConnection::class)
        ->and(neo4j_connection('neo4j')->getDriverName())->toBe('neo4j');
});

it('runs cypher through the helpers, naming positional placeholders and leaving a ? inside a string alone', function () {
    $label = Person::$label;

    cypher_run("CREATE (n:`{$label}` {id: ?, name: ?, tags: ?})", ['a', 'Ada', ['x', 'y']]);

    expect(cypher("MATCH (n:`{$label}`) WHERE n.name = ? RETURN n", ['Ada']))->toEqual([['id' => 'a', 'name' => 'Ada', 'tags' => ['x', 'y']]])
        ->and(cypher_one("MATCH (n:`{$label}`) WHERE n.id = ? RETURN 'why?' AS q, n.tags AS tags", ['a']))->toBe(['q' => 'why?', 'tags' => ['x', 'y']]);
});

it('creates, finds and saves graph models', function () {
    $ada = Person::create(['id' => 'a', 'name' => 'Ada', 'age' => 36, 'tags' => ['math']]);
    $found = Person::find('a');

    $found->name = 'Ada Lovelace';
    $found->save();

    expect($ada->exists)->toBeTrue()
        ->and($found->age)->toBe(36)
        ->and($found->tags)->toBe(['math'])
        ->and($found->created_at)->not->toBeNull()
        ->and(Person::find('a')->name)->toBe('Ada Lovelace')
        ->and(Person::find('missing'))->toBeNull();
});

it('queries models with wheres, order, windows, aggregates and pluck', function () {
    foreach ([['a', 'Ada', 36], ['b', 'Bob', 20], ['c', 'Cy', 50], ['d', 'Dee', null]] as [$id, $name, $age]) {
        Person::create(['id' => $id, 'name' => $name, 'age' => $age]);
    }

    expect(Person::where('age', '>', 25)->orderBy('age')->pluck('name')->all())->toBe(['Ada', 'Cy'])
        ->and(Person::whereIn('id', ['a', 'b', 'z'])->orderBy('id')->pluck('id')->all())->toBe(['a', 'b'])
        ->and(Person::whereNull('age')->pluck('name')->all())->toBe(['Dee'])
        ->and(Person::whereBetween('age', [20, 36])->count())->toBe(2)
        ->and(Person::where('name', 'like', 'a%')->pluck('name')->all())->toBe(['Ada'])
        ->and(Person::whereLike('name', 'c_', caseSensitive: true)->count())->toBe(0)
        ->and(Person::where(fn ($q) => $q->where('id', 'a')->orWhere('id', 'c'))->orderByDesc('id')->pluck('id')->all())->toBe(['c', 'a'])
        ->and(Person::orderBy('id')->skip(1)->take(2)->pluck('id')->all())->toBe(['b', 'c'])
        ->and(Person::count())->toBe(4)
        ->and(Person::sum('age'))->toBe(106)
        ->and(Person::max('age'))->toBe(50)
        ->and(Person::where('id', 'a')->exists())->toBeTrue()
        ->and(Person::where('id', 'z')->exists())->toBeFalse()
        ->and(Person::orderBy('id')->paginate(3)->total())->toBe(4);
});

it('updates, increments and deletes, reporting the nodes touched', function () {
    foreach (['a', 'b', 'c'] as $id) {
        Person::create(['id' => $id, 'name' => $id, 'age' => 10]);
    }

    $updated = Person::whereIn('id', ['a', 'b'])->update(['name' => 'renamed', 'age' => 11]);
    $incremented = Person::where('id', 'c')->increment('age', 5);
    $oldest = Person::orderBy('age')->limit(1)->delete();
    $deleted = Person::where('name', 'renamed')->delete();

    expect($updated)->toBe(2)
        ->and($incremented)->toBe(1)
        ->and(Person::find('c')->age)->toBe(15)
        ->and($oldest)->toBe(1)
        ->and($deleted)->toBe(1)
        ->and(Person::pluck('id')->all())->toBe(['c']);
});

it('upserts on the unique properties', function () {
    Person::create(['id' => 'a', 'name' => 'Ada', 'age' => 1]);

    $affected = Person::query()->upsert([['id' => 'a', 'name' => 'Ada', 'age' => 2], ['id' => 'b', 'name' => 'Bob', 'age' => 3]], ['id'], ['age']);

    expect($affected)->toBe(2)
        ->and(Person::orderBy('id')->get(['id', 'age'])->map->only(['id', 'age'])->all())->toBe([['id' => 'a', 'age' => 2], ['id' => 'b', 'age' => 3]]);
});

it('commits and rolls back transactions', function () {
    $db = $this->app['db']->connection('neo4j');

    $db->transaction(fn () => Person::create(['id' => 'kept', 'name' => 'kept']));

    expect(fn () => $db->transaction(function () {
        Person::create(['id' => 'undone', 'name' => 'undone']);

        throw new DomainException('undo');
    }))->toThrow(DomainException::class)
        ->and(Person::pluck('id')->all())->toBe(['kept']);
});

it('reads rows one at a time through cursor()', function () {
    Person::create(['id' => 'a', 'name' => 'Ada']);
    Person::create(['id' => 'b', 'name' => 'Bob']);

    expect(Person::orderBy('id')->cursor()->map(fn (Person $person) => $person->id)->all())->toBe(['a', 'b']);
});
