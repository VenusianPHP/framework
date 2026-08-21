<?php

/**
 * Ported from Illuminate\Tests\Support\SupportLazyCollectionTest. Unlike
 * SupportCollectionTest, upstream never runs this file through a dual
 * Collection/LazyCollection provider — every case here is LazyCollection-
 * specific (laziness, remembering, throttling, heartbeats), so it is a
 * plain one-off test per case, same as upstream.
 */

use Carbon\CarbonInterval as Duration;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\LazyCollection;
use Voyager\NutsAndBolts\Sleep;
use Voyager\System\Testing\Wormhole;

test('canCreateEmptyCollection', function () {
    $this->assertSame([], LazyCollection::make()->all());
    $this->assertSame([], LazyCollection::empty()->all());
});

test('canCreateCollectionFromArray', function () {
    $array = [1, 2, 3];

    $data = LazyCollection::make($array);

    $this->assertSame($array, $data->all());

    $array = ['a' => 1, 'b' => 2, 'c' => 3];

    $data = LazyCollection::make($array);

    $this->assertSame($array, $data->all());
});

test('canCreateCollectionFromArrayable', function () {
    $array = [1, 2, 3];

    $data = LazyCollection::make(Collection::make($array));

    $this->assertSame($array, $data->all());

    $array = ['a' => 1, 'b' => 2, 'c' => 3];

    $data = LazyCollection::make(Collection::make($array));

    $this->assertSame($array, $data->all());
});

test('canCreateCollectionFromGeneratorFunction', function () {
    $data = LazyCollection::make(function () {
        yield 1;
        yield 2;
        yield 3;
    });

    $this->assertSame([1, 2, 3], $data->all());

    $data = LazyCollection::make(function () {
        yield 'a' => 1;
        yield 'b' => 2;
        yield 'c' => 3;
    });

    $this->assertSame([
        'a' => 1,
        'b' => 2,
        'c' => 3,
    ], $data->all());
});

test('canCreateCollectionFromNonGeneratorFunction', function () {
    $data = LazyCollection::make(function () {
        return 'laravel';
    });

    $this->assertSame(['laravel'], $data->all());
});

test('doesNotCreateCollectionFromGenerator', function () {
    $this->expectException(InvalidArgumentException::class);

    $generateNumber = function () {
        yield 1;
    };

    LazyCollection::make($generateNumber());
});

test('eager', function () {
    $source = [1, 2, 3, 4, 5];

    $data = LazyCollection::make(function () use (&$source) {
        yield from $source;
    })->eager();

    $source[] = 6;

    $this->assertSame([1, 2, 3, 4, 5], $data->all());
});

test('remember', function () {
    $source = [1, 2, 3, 4];

    $collection = LazyCollection::make(function () use (&$source) {
        yield from $source;
    })->remember();

    $this->assertSame([1, 2, 3, 4], $collection->all());

    $source = [];

    $this->assertSame([1, 2, 3, 4], $collection->all());
});

test('rememberWithTwoRunners', function () {
    $source = [1, 2, 3, 4];

    $collection = LazyCollection::make(function () use (&$source) {
        yield from $source;
    })->remember();

    $a = $collection->getIterator();
    $b = $collection->getIterator();

    $this->assertEquals(1, $a->current());
    $this->assertEquals(1, $b->current());

    $b->next();

    $this->assertEquals(1, $a->current());
    $this->assertEquals(2, $b->current());

    $b->next();

    $this->assertEquals(1, $a->current());
    $this->assertEquals(3, $b->current());

    $a->next();

    $this->assertEquals(2, $a->current());
    $this->assertEquals(3, $b->current());

    $a->next();

    $this->assertEquals(3, $a->current());
    $this->assertEquals(3, $b->current());

    $a->next();

    $this->assertEquals(4, $a->current());
    $this->assertEquals(3, $b->current());

    $b->next();

    $this->assertEquals(4, $a->current());
    $this->assertEquals(4, $b->current());
});

test('rememberWithDuplicateKeys', function () {
    $collection = LazyCollection::make(function () {
        yield 'key' => 1;
        yield 'key' => 2;
    })->remember();

    $results = $collection->map(function ($value, $key) {
        return [$key, $value];
    })->values()->all();

    $this->assertSame([['key', 1], ['key', 2]], $results);
});

test('takeUntilTimeout', function () {
    $timeout = Carbon::now();

    $mock = Mockery::mock(LazyCollection::class.'[now]');

    $timedOutWith = [];

    $results = $mock
        ->times(10)
        ->tap(function ($collection) use ($mock, $timeout) {
            tap($collection)
                ->mockery_init($mock->mockery_getContainer())
                ->shouldAllowMockingProtectedMethods()
                ->shouldReceive('now')
                ->times(3)
                ->andReturn(
                    (clone $timeout)->sub(2, 'minute')->getTimestamp(),
                    (clone $timeout)->sub(1, 'minute')->getTimestamp(),
                    $timeout->getTimestamp()
                );
        })
        ->takeUntilTimeout($timeout, function ($value, $key) use (&$timedOutWith) {
            $timedOutWith = [$value, $key];
        })
        ->all();

    $this->assertSame([1, 2], $results);
    $this->assertSame([2, 1], $timedOutWith);
});

test('tapEach', function () {
    $data = LazyCollection::times(10);

    $tapped = [];

    $data = $data->tapEach(function ($value, $key) use (&$tapped) {
        $tapped[$key] = $value;
    });

    $this->assertEmpty($tapped);

    $data = $data->take(5)->all();

    $this->assertSame([1, 2, 3, 4, 5], $data);
    $this->assertSame([1, 2, 3, 4, 5], $tapped);
});

test('throttle', function () {
    Sleep::fake();

    $data = LazyCollection::times(3)
        ->throttle(2)
        ->all();

    Sleep::assertSlept(function (Duration $duration) {
        $this->assertEqualsWithDelta(
            2_000_000, $duration->totalMicroseconds, 1_000
        );

        return true;
    }, times: 3);

    $this->assertSame([1, 2, 3], $data);

    Sleep::fake(false);
});

test('throttleAccountsForTimePassed', function () {
    Sleep::fake();
    Carbon::setTestNow(now());

    $data = LazyCollection::times(3)
        ->throttle(3)
        ->tapEach(function ($value, $index) {
            if ($index == 1) {
                // Travel in time...
                (new Wormhole(1))->second();
            }
        })
        ->all();

    Sleep::assertSlept(function (Duration $duration, int $index) {
        $expectation = $index == 1 ? 2_000_000 : 3_000_000;

        $this->assertEqualsWithDelta(
            $expectation, $duration->totalMicroseconds, 1_000
        );

        return true;
    }, times: 3);

    $this->assertSame([1, 2, 3], $data);

    Sleep::fake(false);
    Carbon::setTestNow();
});

test('uniqueDoubleEnumeration', function () {
    $data = LazyCollection::times(2)->unique();

    $data->all();

    $this->assertSame([1, 2], $data->all());
});

test('after', function () {
    $data = new LazyCollection([1, '2', 3, 4]);

    // Test finding item after value with non-strict comparison
    $result = $data->after(1);
    $this->assertSame('2', $result);

    // Test with strict comparison
    $result = $data->after('2', true);
    $this->assertSame(3, $result);

    $users = new LazyCollection([
        ['name' => 'Taylor', 'age' => 35],
        ['name' => 'Jeffrey', 'age' => 45],
        ['name' => 'Mohamed', 'age' => 35],
    ]);

    // Test finding item after the one that matches a condition
    $result = $users->after(function ($user) {
        return $user['name'] === 'Jeffrey';
    });

    $this->assertSame(['name' => 'Mohamed', 'age' => 35], $result);
});

test('before', function () {
    // Test finding item before value with non-strict comparison
    $data = new LazyCollection([1, 2, '3', 4]);
    $result = $data->before(2);
    $this->assertSame(1, $result);

    // Test finding item before value with strict comparison
    $result = $data->before(4, true);
    $this->assertSame('3', $result);

    // Test finding item before the one that matches a callback condition
    $users = new LazyCollection([
        ['name' => 'Taylor', 'age' => 35],
        ['name' => 'Jeffrey', 'age' => 45],
        ['name' => 'Mohamed', 'age' => 35],
    ]);
    $result = $users->before(function ($user) {
        return $user['name'] === 'Jeffrey';
    });
    $this->assertSame(['name' => 'Taylor', 'age' => 35], $result);
});

test('shuffle', function () {
    $data = new LazyCollection([1, 2, 3, 4, 5]);
    $shuffled = $data->shuffle();

    $this->assertCount(5, $shuffled);
    $this->assertEquals([1, 2, 3, 4, 5], $shuffled->sort()->values()->all());

    // Test shuffling associative array maintains key-value pairs
    $users = new LazyCollection([
        'first' => ['name' => 'Taylor'],
        'second' => ['name' => 'Jeffrey'],
    ]);
    $shuffled = $users->shuffle();

    $this->assertCount(2, $shuffled);
    $this->assertTrue($shuffled->contains('name', 'Taylor'));
    $this->assertTrue($shuffled->contains('name', 'Jeffrey'));
});

test('collapseWithKeys', function () {
    $collection = new LazyCollection([
        ['a' => 1, 'b' => 2],
        ['c' => 3, 'd' => 4],
    ]);
    $collapsed = $collection->collapseWithKeys();

    $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $collapsed->all());

    $collection = new LazyCollection([
        ['a' => 1],
        new LazyCollection(['b' => 2]),
    ]);
    $collapsed = $collection->collapseWithKeys();

    $this->assertEquals(['a' => 1, 'b' => 2], $collapsed->all());
});

test('containsOneItem', function () {
    $collection = new LazyCollection([5]);
    $this->assertTrue($collection->containsOneItem());

    $emptyCollection = new LazyCollection([]);
    $this->assertFalse($emptyCollection->containsOneItem());

    $multipleCollection = new LazyCollection([1, 2, 3]);
    $this->assertFalse($multipleCollection->containsOneItem());
});

test('containsManyItems', function () {
    $emptyCollection = new LazyCollection([]);
    $this->assertFalse($emptyCollection->containsManyItems());

    $singleCollection = new LazyCollection([1]);
    $this->assertFalse($singleCollection->containsManyItems());

    $multipleCollection = new LazyCollection([1, 2]);
    $this->assertTrue($multipleCollection->containsManyItems());

    $manyCollection = new LazyCollection([1, 2, 3]);
    $this->assertTrue($manyCollection->containsManyItems());
});

test('doesntContain', function () {
    $collection = new LazyCollection([1, 2, 3, 4, 5]);

    $this->assertTrue($collection->doesntContain(10));
    $this->assertFalse($collection->doesntContain(3));
    $this->assertTrue($collection->doesntContain('value', '>', 10));
    $this->assertTrue($collection->doesntContain(function ($value) {
        return $value > 10;
    }));

    $users = new LazyCollection([
        [
            'name' => 'Taylor',
            'role' => 'developer',
        ],
        [
            'name' => 'Jeffrey',
            'role' => 'designer',
        ],
    ]);

    $this->assertTrue($users->doesntContain('name', 'Adam'));
    $this->assertFalse($users->doesntContain('name', 'Taylor'));
});

test('dot', function () {
    $collection = new LazyCollection([
        'foo' => [
            'bar' => 'baz',
        ],
        'user' => [
            'name' => 'Taylor',
            'profile' => [
                'age' => 30,
            ],
        ],
        'users' => [
            0 => [
                'name' => 'Taylor',
            ],
            1 => [
                'name' => 'Jeffrey',
            ],
        ],
    ]);

    $dotted = $collection->dot();

    $expected = [
        'foo.bar' => 'baz',
        'user.name' => 'Taylor',
        'user.profile.age' => 30,
        'users.0.name' => 'Taylor',
        'users.1.name' => 'Jeffrey',
    ];

    $this->assertEquals($expected, $dotted->all());
});

test('withHeartbeat', function () {
    $start = Carbon::create(2000, 1, 1);
    $after2Minutes = $start->copy()->addMinutes(2);
    $after5Minutes = $start->copy()->addMinutes(5);
    $after7Minutes = $start->copy()->addMinutes(7);
    $after11Minutes = $start->copy()->addMinutes(11);

    Carbon::setTestNow($start);

    $output = new Collection();

    $numbers = LazyCollection::range(1, 10)

        // Move the clock to possibly trigger the heartbeat...
        ->tapEach(fn ($number) => Carbon::setTestNow(
            match ($number) {
                3 => $after2Minutes,
                4 => $after5Minutes,
                6 => $after7Minutes,
                9 => $after11Minutes,
                default => Carbon::now(),
            }
        ))

        // Push the current date to `output` when heartbeat is triggered...
        ->withHeartbeat(Duration::minutes(5), fn () => $output[] = Carbon::now())

        // Push every number onto `output` as it's enumerated...
        ->tapEach(fn ($number) => $output[] = $number)->all();

    $this->assertEquals(range(1, 10), $numbers);

    $this->assertEquals(
        [
            1, 2, 3,
            $after5Minutes,
            4, 5, 6, 7, 8,
            $after11Minutes,
            9, 10,
        ],
        $output->all(),
    );

    Carbon::setTestNow();
});

test('randomPreservesKeys', function () {
    $collection = new LazyCollection([
        'first' => 1,
        'second' => 2,
        'third' => 3,
    ]);

    $keysWithoutPreserve = array_keys($collection->random(2)->all());

    $this->assertEquals([0, 1], $keysWithoutPreserve);

    $keysWithPreserve = array_keys($collection->random(2, true)->all());

    foreach ($keysWithPreserve as $key) {
        $this->assertContains($key, ['first', 'second', 'third']);
    }
});
