<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\FileStore;
use Voyager\Cache\Lock;
use Voyager\Cache\RedisStore;
use Voyager\Cache\Repository;
use Voyager\Cache\TaggableStore;
use Voyager\Cache\TaggedCache;
use Voyager\Vessel\Vessel as Container;
use Voyager\Contracts\Cache\LockProvider;
use Voyager\Contracts\Cache\LockTimeoutException;
use Voyager\Contracts\Cache\Store;
use Voyager\Events\Dispatcher;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\DataObjects\Carbon;

/** The fixed "now" every test in this file runs against. */
function cacheRepositoryTestDate(): string
{
    return '2030-07-25 12:13:14 UTC';
}

/** A Repository wrapping a bare Store mock, with an event dispatcher already attached. */
function repositoryWithMockStore(): Repository
{
    $dispatcher = new Dispatcher(Mockery::mock(Container::class));
    $repository = new Repository(Mockery::mock(Store::class));

    $repository->setEventDispatcher($dispatcher);

    return $repository;
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse(cacheRepositoryTestDate()));
});

afterEach(function () {
    Carbon::setTestNow(null);
});

test('get returns the value from the cache', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('bar');

    expect($repo->get('foo'))->toBe('bar');
});

test('get returns multiple values from the cache when given an array', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('many')->once()->with(['foo', 'bar'])->andReturn(['foo' => 'bar', 'bar' => 'baz']);

    expect($repo->get(['foo', 'bar']))->toEqual(['foo' => 'bar', 'bar' => 'baz']);
});

test('get returns multiple values from the cache when given an array with default values', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('many')->once()->with(['foo', 'bar'])->andReturn(['foo' => null, 'bar' => 'baz']);

    expect($repo->get(['foo' => 'default', 'bar']))->toEqual(['foo' => 'default', 'bar' => 'baz']);
});

test('get returns multiple values from the cache when given an array of one two three', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('many')->once()->with([1, 2, 3])->andReturn([1 => null, 2 => null, 3 => null]);

    expect($repo->get([1, 2, 3]))->toEqual([1 => null, 2 => null, 3 => null]);
});

test('the default value is returned', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->times(2)->andReturn(null);

    expect($repo->get('foo', 'bar'))->toBe('bar')
        ->and($repo->get('boom', function () {
            return 'baz';
        }))->toBe('baz');
});

test('setDefaultCacheTime sets the default cache time', function () {
    $repo = repositoryWithMockStore();
    $repo->setDefaultCacheTime(10);

    expect($repo->getDefaultCacheTime())->toEqual(10);
});

test('has checks for a value in the cache', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);
    $repo->getStore()->shouldReceive('get')->once()->with('bar')->andReturn('bar');
    $repo->getStore()->shouldReceive('get')->once()->with('baz')->andReturn(false);

    expect($repo->has('bar'))->toBeTrue()
        ->and($repo->has('foo'))->toBeFalse()
        ->and($repo->has('baz'))->toBeTrue();
});

test('missing checks for the absence of a value in the cache', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);
    $repo->getStore()->shouldReceive('get')->once()->with('bar')->andReturn('bar');

    expect($repo->missing('foo'))->toBeTrue()
        ->and($repo->missing('bar'))->toBeFalse();
});

test('remember calls put and returns the default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->andReturn(null);
    $repo->getStore()->shouldReceive('put')->once()->with('foo', 'bar', 10);
    $result = $repo->remember('foo', 10, function () {
        return 'bar';
    });
    expect($result)->toBe('bar');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->times(2)->andReturn(null);
    $repo->getStore()->shouldReceive('put')->once()->with('foo', 'bar', 602);
    $repo->getStore()->shouldReceive('put')->once()->with('baz', 'qux', 598);
    $result = $repo->remember('foo', Carbon::now()->addMinutes(10)->addSeconds(2), function () {
        return 'bar';
    });
    expect($result)->toBe('bar');
    $result = $repo->remember('baz', Carbon::now()->addMinutes(10)->subSeconds(2), function () {
        return 'qux';
    });
    expect($result)->toBe('qux');

    // Use a callable...
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->andReturn(null);
    $repo->getStore()->shouldReceive('put')->once()->with('foo', 'bar', 10);
    $result = $repo->remember('foo', function () {
        return 10;
    }, function () {
        return 'bar';
    });
    expect($result)->toBe('bar');
});

test('rememberForever calls forever and returns the default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->andReturn(null);
    $repo->getStore()->shouldReceive('forever')->once()->with('foo', 'bar');
    $result = $repo->rememberForever('foo', function () {
        return 'bar';
    });

    expect($result)->toBe('bar');
});

test('putting multiple items in the cache', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('putMany')->once()->with(['foo' => 'bar', 'bar' => 'baz'], 1);

    $repo->put(['foo' => 'bar', 'bar' => 'baz'], 1);
});

test('setMultiple sets multiple items in the cache from an array', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('putMany')->once()->with(['foo' => 'bar', 'bar' => 'baz'], 1)->andReturn(true);
    $result = $repo->setMultiple(['foo' => 'bar', 'bar' => 'baz'], 1);

    expect($result)->toBeTrue();
});

test('setMultiple sets multiple items in the cache from an iterator', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('putMany')->once()->with(['foo' => 'bar', 'bar' => 'baz'], 1)->andReturn(true);
    $result = $repo->setMultiple(new ArrayIterator(['foo' => 'bar', 'bar' => 'baz']), 1);

    expect($result)->toBeTrue();
});

test('put with a null TTL remembers the item forever', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('forever')->once()->with('foo', 'bar')->andReturn(true);

    expect($repo->put('foo', 'bar'))->toBeTrue();
});

test('put with a datetime in the past or zero seconds removes the old item', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('put')->never();
    $repo->getStore()->shouldReceive('forget')->twice()->andReturn(true);
    $result = $repo->put('foo', 'bar', Carbon::now()->subMinutes(10));
    expect($result)->toBeTrue();

    $result = $repo->put('foo', 'bar', Carbon::now());
    expect($result)->toBeTrue();
});

test('putMany with a null TTL remembers the items forever', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('forever')->with('foo', 'bar')->andReturn(true);
    $repo->getStore()->shouldReceive('forever')->with('bar', 'baz')->andReturn(true);

    expect($repo->putMany(['foo' => 'bar', 'bar' => 'baz']))->toBeTrue();
});

test('add with a store failure returns false', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('add')->never();
    $repo->getStore()->shouldReceive('get')->andReturn(null);
    $repo->getStore()->shouldReceive('put')->andReturn(false);

    expect($repo->add('foo', 'bar', 60))->toBeFalse();
});

test('cache add calls RedisStore add', function () {
    $store = Mockery::mock(RedisStore::class);
    $store->shouldReceive('add')->once()->with('k', 'v', 60)->andReturn(true);
    $repository = new Repository($store);

    expect($repository->add('k', 'v', 60))->toBeTrue();
});

test('add can accept date intervals', function () {
    $storeWithAdd = Mockery::mock(RedisStore::class);
    $storeWithAdd->shouldReceive('add')->once()->with('k', 'v', 61)->andReturn(true);
    $repository = new Repository($storeWithAdd);
    expect($repository->add('k', 'v', DateInterval::createFromDateString('61 seconds')))->toBeTrue();

    $storeWithoutAdd = Mockery::mock(ArrayStore::class);
    expect(method_exists(ArrayStore::class, 'add'))->toBeFalse();
    $storeWithoutAdd->shouldReceive('get')->once()->with('k')->andReturn(null);
    $storeWithoutAdd->shouldReceive('put')->once()->with('k', 'v', 60)->andReturn(true);
    $repository = new Repository($storeWithoutAdd);
    expect($repository->add('k', 'v', DateInterval::createFromDateString('60 seconds')))->toBeTrue();
});

test('add can accept a DateTimeInterface', function () {
    $withAddStore = Mockery::mock(RedisStore::class);
    $withAddStore->shouldReceive('add')->once()->with('k', 'v', 61)->andReturn(true);
    $repository = new Repository($withAddStore);
    expect($repository->add('k', 'v', Carbon::now()->addSeconds(61)))->toBeTrue();

    $noAddStore = Mockery::mock(ArrayStore::class);
    expect(method_exists(ArrayStore::class, 'add'))->toBeFalse();
    $noAddStore->shouldReceive('get')->once()->with('k')->andReturn(null);
    $noAddStore->shouldReceive('put')->once()->with('k', 'v', 62)->andReturn(true);
    $repository = new Repository($noAddStore);
    expect($repository->add('k', 'v', Carbon::now()->addSeconds(62)))->toBeTrue();
});

test('add with a null TTL remembers the item forever', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);
    $repo->getStore()->shouldReceive('forever')->once()->with('foo', 'bar')->andReturn(true);

    expect($repo->add('foo', 'bar'))->toBeTrue();
});

test('add with a datetime in the past or zero seconds returns immediately', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('add', 'get', 'put')->never();
    $result = $repo->add('foo', 'bar', Carbon::now()->subMinutes(10));
    expect($result)->toBeFalse();

    $result = $repo->add('foo', 'bar', Carbon::now());
    expect($result)->toBeFalse();

    $result = $repo->add('foo', 'bar', -1);
    expect($result)->toBeFalse();
});

test('put resolves several duration shapes to the same number of seconds', function ($duration) {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('put')->once()->with($key = 'foo', $value = 'bar', 300);

    $repo->put($key, $value, $duration);
})->with([
    'Carbon' => [fn () => Carbon::parse(cacheRepositoryTestDate())->addMinutes(5)],
    'DateTime' => [fn () => (new DateTime(cacheRepositoryTestDate()))->modify('+5 minutes')],
    'DateTimeImmutable' => [fn () => (new DateTimeImmutable(cacheRepositoryTestDate()))->modify('+5 minutes')],
    'DateInterval' => [fn () => new DateInterval('PT5M')],
    'int seconds' => [fn () => 300],
]);

test('a macro can be registered with a non-static call', function () {
    // A fixed name stands in for the PHPUnit __CLASS__ this test used to
    // name its macro — that constant has no enclosing class in a Pest file,
    // and the macro name itself is otherwise irrelevant to what's tested.
    $macroName = 'cache_repository_test_macro';

    $repo = repositoryWithMockStore();
    $repo::macro($macroName, function () {
        return 'Taylor';
    });

    expect($repo->{$macroName}())->toBe('Taylor');
});

test('forget forgets a cache key', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('forget')->once()->with('a-key')->andReturn(true);

    $repo->forget('a-key');
});

test('delete removes a cache key', function () {
    // Alias of Forget
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('forget')->once()->with('a-key')->andReturn(true);

    $repo->delete('a-key');
});

test('set sets a value in the cache', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('put')->with($key = 'foo', $value = 'bar', 1)->andReturn(true);
    $result = $repo->set($key, $value, 1);

    expect($result)->toBeTrue();
});

test('clear clears the whole cache', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('flush')->andReturn(true);

    expect($repo->clear())->toBeTrue();
});

test('getMultiple gets multiple values from the cache', function () {
    $keys = ['key1', 'key2', 'key3'];
    $default = 5;

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('many')->once()->with(['key1', 'key2', 'key3'])->andReturn(['key1' => 1, 'key2' => null, 'key3' => null]);

    expect($repo->getMultiple($keys, $default))->toEqual(['key1' => 1, 'key2' => 5, 'key3' => 5]);
});

test('deleteMultiple removes multiple keys', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('forget')->once()->with('a-key')->andReturn(true);
    $repo->getStore()->shouldReceive('forget')->once()->with('a-second-key')->andReturn(true);

    expect($repo->deleteMultiple(['a-key', 'a-second-key']))->toBeTrue();
});

test('deleteMultiple fails if one key fails', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('forget')->once()->with('a-key')->andReturn(true);
    $repo->getStore()->shouldReceive('forget')->once()->with('a-second-key')->andReturn(false);

    expect($repo->deleteMultiple(['a-key', 'a-second-key']))->toBeFalse();
});

test('all tags are passed to the taggable store', function () {
    $store = Mockery::mock(ArrayStore::class);
    $repo = new Repository($store);

    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('setDefaultCacheTime');
    $store->shouldReceive('tags')->once()->with(['foo', 'bar', 'baz'])->andReturn($taggedCache);

    $repo->tags('foo', 'bar', 'baz');
});

test('it throws an exception when the store does not support tags', function () {
    $this->expectException(BadMethodCallException::class);

    $store = new FileStore(new Filesystem, '/usr');
    expect(method_exists($store, 'tags'))->toBeFalse();

    (new Repository($store))->tags('foo');
});

test('the tags method returns a tagged cache', function () {
    $store = (new Repository(new ArrayStore()))->tags('foo');

    expect($store)->toBeInstanceOf(TaggedCache::class);
});

test('possible input types to tags', function () {
    $repo = new Repository(new ArrayStore());

    $store = $repo->tags('foo');
    expect($store->getTags()->getNames())->toEqual(['foo']);

    $store = $repo->tags(['foo!', 'Kangaroo']);
    expect($store->getTags()->getNames())->toEqual(['foo!', 'Kangaroo']);

    $store = $repo->tags('r1', 'r2', 'r3');
    expect($store->getTags()->getNames())->toEqual(['r1', 'r2', 'r3']);
});

test('the event dispatcher is passed to the store from the repository', function () {
    $repo = new Repository(new ArrayStore());
    $repo->setEventDispatcher(new Dispatcher());

    $store = $repo->tags('foo');

    expect($store->getEventDispatcher())->toBe($repo->getEventDispatcher());
});

test('the default cache lifetime is set on the taggable store', function () {
    $repo = new Repository(new ArrayStore());
    $repo->setDefaultCacheTime(random_int(1, 100));

    $store = $repo->tags('foo');

    expect($store->getDefaultCacheTime())->toBe($repo->getDefaultCacheTime());
});

test('taggable repositories support tags', function () {
    $taggable = Mockery::mock(TaggableStore::class);
    $taggableRepo = new Repository($taggable);

    expect($taggableRepo->supportsTags())->toBeTrue();
});

test('a non-taggable repository does not support tags', function () {
    $nonTaggable = Mockery::mock(FileStore::class);
    $nonTaggableRepo = new Repository($nonTaggable);

    expect($nonTaggableRepo->supportsTags())->toBeFalse();
});

test('atomic executes the callback and returns the result', function () {
    $repo = new Repository(new ArrayStore);

    $result = $repo->withoutOverlapping('foo', function () {
        return 'bar';
    });

    expect($result)->toBe('bar');
});

test('atomic passes the lock and wait seconds to the lock', function () {
    $store = Mockery::mock(Store::class, LockProvider::class);
    $repo = new Repository($store);
    $lock = Mockery::mock(Lock::class);

    $store->shouldReceive('lock')->once()->with('foo', 30, null)->andReturn($lock);
    $lock->shouldReceive('block')->once()->with(15, Mockery::type('callable'))->andReturnUsing(function ($seconds, $callback) {
        return $callback();
    });

    $result = $repo->withoutOverlapping('foo', function () {
        return 'bar';
    }, 30, 15);

    expect($result)->toBe('bar');
});

test('atomic passes the owner to the lock', function () {
    $store = Mockery::mock(Store::class, LockProvider::class);
    $repo = new Repository($store);
    $lock = Mockery::mock(Lock::class);

    $store->shouldReceive('lock')->once()->with('foo', 10, 'my-owner')->andReturn($lock);
    $lock->shouldReceive('block')->once()->with(10, Mockery::type('callable'))->andReturnUsing(function ($seconds, $callback) {
        return $callback();
    });

    $result = $repo->withoutOverlapping('foo', function () {
        return 'bar';
    }, 10, 10, 'my-owner');

    expect($result)->toBe('bar');
});

test('atomic throws on lock timeout', function () {
    $repo = new Repository(new ArrayStore);

    $repo->getStore()->lock('foo', 10)->acquire();

    $called = false;

    try {
        $repo->withoutOverlapping('foo', function () use (&$called) {
            $called = true;
        }, 10, 0);

        $this->fail('Expected LockTimeoutException was not thrown.');
    } catch (LockTimeoutException) {
        expect($called)->toBeFalse();
    }
});

test('it gets a value as a string', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('bar');

    expect($repo->string('foo'))->toBe('bar');
});

test('it gets a value as a string with a default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);

    expect($repo->string('foo', 'default'))->toBe('default');
});

test('it throws an exception when getting a non-string as a string', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache value for key [foo] must be a string, integer given.');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(123);

    $repo->string('foo');
});

test('it gets a value as an integer', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(123);

    expect($repo->integer('foo'))->toBe(123);
});

test('it gets a value as an integer with a default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);

    expect($repo->integer('foo', 456))->toBe(456);
});

test('it gets a value as an integer from a numeric string', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('123');

    expect($repo->integer('foo'))->toBe(123);
});

test('it throws an exception when getting a non-integer as an integer', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache value for key [foo] must be an integer, string given.');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('bar');

    $repo->integer('foo');
});

test('it throws an exception when getting a float string as an integer', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache value for key [foo] must be an integer, string given.');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('1.5');

    $repo->integer('foo');
});

test('it gets a value as a float', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(1.5);

    expect($repo->float('foo'))->toBe(1.5);
});

test('it gets a value as a float with a default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);

    expect($repo->float('foo', 2.5))->toBe(2.5);
});

test('it gets a value as a float from a numeric string', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('1.5');

    expect($repo->float('foo'))->toBe(1.5);
});

test('it throws an exception when getting a non-float as a float', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache value for key [foo] must be a float, string given.');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('bar');

    $repo->float('foo');
});

test('it gets a value as a boolean', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(true);

    expect($repo->boolean('foo'))->toBeTrue();
});

test('it gets a value as a boolean with a default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);

    expect($repo->boolean('foo', false))->toBeFalse();
});

test('it throws an exception when getting a non-boolean as a boolean', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache value for key [foo] must be a boolean, string given.');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('bar');

    $repo->boolean('foo');
});

test('it gets a value as an array', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(['bar', 'baz']);

    expect($repo->array('foo'))->toBe(['bar', 'baz']);
});

test('it gets a value as an array with a default', function () {
    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn(null);

    expect($repo->array('foo', ['default']))->toBe(['default']);
});

test('it throws an exception when getting a non-array as an array', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache value for key [foo] must be an array, string given.');

    $repo = repositoryWithMockStore();
    $repo->getStore()->shouldReceive('get')->once()->with('foo')->andReturn('bar');

    $repo->array('foo');
});
