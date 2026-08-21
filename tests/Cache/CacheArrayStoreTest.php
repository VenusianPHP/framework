<?php

use Voyager\Cache\ArrayStore;
use Voyager\NutsAndBolts\DataObjects\Carbon;

afterEach(function () {
    Carbon::setTestNow(null);
});

test('items can be set and retrieved', function () {
    $store = new ArrayStore;
    $result = $store->put('foo', 'bar', 10);

    expect($result)->toBeTrue()
        ->and($store->get('foo'))->toBe('bar');
});

test('cache ttl', function () {
    $store = new ArrayStore();

    Carbon::setTestNow('2000-01-01 00:00:00.500'); // 500 milliseconds past
    $store->put('hello', 'world', 1);

    Carbon::setTestNow('2000-01-01 00:00:01.499'); // progress 0.999 seconds
    expect($store->get('hello'))->toBe('world');

    Carbon::setTestNow('2000-01-01 00:00:01.500'); // progress 0.001 seconds. 1 second since putting into cache.
    expect($store->get('hello'))->toBeNull();
});

test('multiple items can be set and retrieved', function () {
    $store = new ArrayStore;
    $result = $store->put('foo', 'bar', 10);
    $resultMany = $store->putMany([
        'fizz' => 'buz',
        'quz' => 'baz',
    ], 10);

    expect($result)->toBeTrue()
        ->and($resultMany)->toBeTrue()
        ->and($store->many(['foo', 'fizz', 'quz', 'norf']))->toEqual([
            'foo' => 'bar',
            'fizz' => 'buz',
            'quz' => 'baz',
            'norf' => null,
        ]);
});

test('items can expire', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore;

    $store->put('foo', 'bar', 10);
    Carbon::setTestNow(Carbon::now()->addSeconds(10)->addSecond());
    $result = $store->get('foo');

    expect($result)->toBeNull();
});

test('storing an item forever properly stores it in the array', function () {
    $mock = $this->getMockBuilder(ArrayStore::class)->onlyMethods(['put'])->getMock();
    $mock->expects($this->once())
        ->method('put')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(0))
        ->willReturn(true);
    $result = $mock->forever('foo', 'bar');

    expect($result)->toBeTrue();
});

test('values can be incremented', function () {
    $store = new ArrayStore;
    $store->put('foo', 1, 10);
    $result = $store->increment('foo');
    expect($result)->toEqual(2)
        ->and($store->get('foo'))->toEqual(2);

    $result = $store->increment('foo', 2);
    expect($result)->toEqual(4)
        ->and($store->get('foo'))->toEqual(4);
});

test('values get casted by increment or decrement', function () {
    $store = new ArrayStore;
    $store->put('foo', '1', 10);
    $result = $store->increment('foo');
    expect($result)->toEqual(2)
        ->and($store->get('foo'))->toEqual(2);

    $store->put('bar', '1', 10);
    $result = $store->decrement('bar');
    expect($result)->toEqual(0)
        ->and($store->get('bar'))->toEqual(0);
});

test('incrementing non-numeric values', function () {
    $store = new ArrayStore;
    $store->put('foo', 'I am string', 10);
    $result = $store->increment('foo');

    expect($result)->toEqual(1)
        ->and($store->get('foo'))->toEqual(1);
});

test('non-existing keys can be incremented', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore;
    $result = $store->increment('foo');
    expect($result)->toEqual(1)
        ->and($store->get('foo'))->toEqual(1);

    // Will be there forever
    Carbon::setTestNow(Carbon::now()->addYears(10));
    expect($store->get('foo'))->toEqual(1);
});

test('expired keys are incremented like non-existing keys', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore;

    $store->put('foo', 999, 10);
    Carbon::setTestNow(Carbon::now()->addSeconds(10)->addSecond());
    $result = $store->increment('foo');

    expect($result)->toEqual(1);
});

test('values can be decremented', function () {
    $store = new ArrayStore;
    $store->put('foo', 1, 10);
    $result = $store->decrement('foo');
    expect($result)->toEqual(0)
        ->and($store->get('foo'))->toEqual(0);

    $result = $store->decrement('foo', 2);
    expect($result)->toEqual(-2)
        ->and($store->get('foo'))->toEqual(-2);
});

test('items can be removed', function () {
    $store = new ArrayStore;
    $store->put('foo', 'bar', 10);

    expect($store->forget('foo'))->toBeTrue()
        ->and($store->get('foo'))->toBeNull()
        ->and($store->forget('foo'))->toBeFalse();
});

test('items can be flushed', function () {
    $store = new ArrayStore;
    $store->put('foo', 'bar', 10);
    $store->put('baz', 'boom', 10);
    $result = $store->flush();

    expect($result)->toBeTrue()
        ->and($store->get('foo'))->toBeNull()
        ->and($store->get('baz'))->toBeNull();
});

test('the cache key prefix is empty', function () {
    $store = new ArrayStore;

    expect($store->getPrefix())->toBeEmpty();
});

test('a lock cannot be acquired twice', function () {
    $store = new ArrayStore;
    $lock = $store->lock('foo', 10);

    expect($lock->acquire())->toBeTrue()
        ->and($lock->acquire())->toBeFalse();
});

test('a lock can be acquired again after expiry', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore;
    $lock = $store->lock('foo', 10);
    $lock->acquire();
    Carbon::setTestNow(Carbon::now()->addSeconds(10));

    expect($lock->acquire())->toBeTrue();
});

test('the lock expiration lower boundary', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore;
    $lock = $store->lock('foo', 10);
    $lock->acquire();
    Carbon::setTestNow(Carbon::now()->addSeconds(10)->subMicrosecond());

    expect($lock->acquire())->toBeFalse();
});

test('a lock with no expiration never expires', function () {
    $store = new ArrayStore;
    $lock = $store->lock('foo');
    $lock->acquire();
    Carbon::setTestNow(Carbon::now()->addYears(100));

    expect($lock->acquire())->toBeFalse();
});

test('a lock can be acquired after release', function () {
    $store = new ArrayStore;
    $lock = $store->lock('foo', 10);
    $lock->acquire();

    expect($lock->release())->toBeTrue()
        ->and($lock->acquire())->toBeTrue();
});

test('another owner cannot release a lock', function () {
    $store = new ArrayStore;
    $owner = $store->lock('foo', 10);
    $wannabeOwner = $store->lock('foo', 10);
    $owner->acquire();

    expect($wannabeOwner->release())->toBeFalse();
});

test('another owner can force-release a lock', function () {
    $store = new ArrayStore;
    $owner = $store->lock('foo', 10);
    $wannabeOwner = $store->lock('foo', 10);
    $owner->acquire();
    $wannabeOwner->forceRelease();

    expect($wannabeOwner->acquire())->toBeTrue();
});

test('values are not stored by reference', function () {
    $store = new ArrayStore($serialize = true);
    $object = new stdClass;
    $object->foo = true;

    $store->put('object', $object, 10);
    $object->bar = true;

    $retrievedObject = $store->get('object');

    expect($retrievedObject->foo)->toBeTrue()
        ->and(property_exists($retrievedObject, 'bar'))->toBeFalse();
});

test('values are stored by reference if serialization is disabled', function () {
    $store = new ArrayStore;
    $object = new stdClass;
    $object->foo = true;

    $store->put('object', $object, 10);
    $object->bar = true;

    $retrievedObject = $store->get('object');

    expect($retrievedObject->foo)->toBeTrue()
        ->and($retrievedObject->bar)->toBeTrue();
});

test('releasing a lock after it was already force-released by another owner fails', function () {
    $store = new ArrayStore;
    $owner = $store->lock('foo', 10);
    $wannabeOwner = $store->lock('foo', 10);
    $owner->acquire();
    $wannabeOwner->forceRelease();

    expect($wannabeOwner->release())->toBeFalse();
});

test('the owner status can be checked after restoring the lock', function () {
    $store = new ArrayStore;
    $firstLock = $store->lock('foo', 10);

    expect($firstLock->get())->toBeTrue();

    $owner = $firstLock->owner();

    $secondLock = $store->restoreLock('foo', $owner);
    expect($secondLock->isOwnedByCurrentProcess())->toBeTrue();
});

test('another owner does not own the lock after restore', function () {
    $store = new ArrayStore;
    $firstLock = $store->lock('foo', 10);

    expect($firstLock->get())->toBeTrue();

    $secondLock = $store->restoreLock('foo', 'other_owner');

    expect($secondLock->isOwnedByCurrentProcess())->toBeFalse();
});

test('an expired lock cannot be refreshed by the previous owner', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore;
    $lock = $store->lock('foo', 10);
    expect($lock->get())->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addSeconds(10)->addSecond());

    expect($lock->refresh(20))->toBeFalse();
});

test('restoring a non-existing lock does not own anything', function () {
    $store = new ArrayStore;
    $firstLock = $store->restoreLock('foo', 'owner');

    expect($firstLock->isOwnedByCurrentProcess())->toBeFalse();
});

test('it can get all items', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore(false);
    $store->put('foo', 'bar', 10);

    expect($store->all())->toEqual([
        'foo' => ['value' => 'bar', 'expiresAt' => Carbon::now()->addSeconds(10)->getPreciseTimestamp(3) / 1000],
    ]);
});

test('it can get all items when serialized', function () {
    Carbon::setTestNow(Carbon::now());

    $store = new ArrayStore(true);
    $store->put('foo', 'bar', 10);
    expect($store->all())->toEqual([
        'foo' => ['value' => 'bar', 'expiresAt' => $expiresAt = (Carbon::now()->addSeconds(10)->getPreciseTimestamp(3) / 1000)],
    ]);

    // Now let's put a serializable value in there
    $store->forget('foo');
    $store->put('foo', Carbon::now(), 10);

    expect($store->all())->toEqual([
        'foo' => [
            'value' => Carbon::now(),
            'expiresAt' => $expiresAt,
        ],
    ]);

    expect($store->all(false)['foo']['value'])->toEqual(serialize(Carbon::now()));
});
