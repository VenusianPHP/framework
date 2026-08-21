<?php

use Voyager\Cache\ApcStore;
use Voyager\Cache\ApcWrapper;

test('get returns null when not found', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['get'])->getMock();
    $apc->expects($this->once())->method('get')->with($this->equalTo('foobar'))->willReturn(null);
    $store = new ApcStore($apc, 'foo');

    expect($store->get('bar'))->toBeNull();
});

test('the APC value is returned', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['get'])->getMock();
    $apc->expects($this->once())->method('get')->willReturn('bar');
    $store = new ApcStore($apc);

    expect($store->get('foo'))->toBe('bar');
});

test('the APC false value is returned', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['get'])->getMock();
    $apc->expects($this->once())->method('get')->willReturn(false);
    $store = new ApcStore($apc);

    expect($store->get('foo'))->toBeFalse();
});

test('getMultiple returns null when not found and the value when found', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['get'])->getMock();
    $apc->expects($this->exactly(3))->method('get')->willReturnMap([
        ['foo', 'qux'],
        ['bar', null],
        ['baz', 'norf'],
    ]);
    $store = new ApcStore($apc);

    expect($store->many(['foo', 'bar', 'baz']))->toEqual([
        'foo' => 'qux',
        'bar' => null,
        'baz' => 'norf',
    ]);
});

test('the set method properly calls APC', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['put'])->getMock();
    $apc->expects($this->once())
        ->method('put')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(60))
        ->willReturn(true);
    $store = new ApcStore($apc);
    $result = $store->put('foo', 'bar', 60);

    expect($result)->toBeTrue();
});

test('the setMultiple method properly calls APC', function () {
    $apc = Mockery::mock(ApcWrapper::class);

    $apc->shouldReceive('put')
        ->once()
        ->with('foo', 'bar', 60)
        ->andReturn(true);

    $apc->shouldReceive('put')
        ->once()
        ->with('baz', 'qux', 60)
        ->andReturn(true);

    $apc->shouldReceive('put')
        ->once()
        ->with('bar', 'norf', 60)
        ->andReturn(true);

    $store = new ApcStore($apc);
    $result = $store->putMany([
        'foo' => 'bar',
        'baz' => 'qux',
        'bar' => 'norf',
    ], 60);

    expect($result)->toBeTrue();
});

test('the increment method properly calls APC', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['increment'])->getMock();
    $apc->expects($this->once())->method('increment')->with($this->equalTo('foo'), $this->equalTo(5));
    $store = new ApcStore($apc);
    $store->increment('foo', 5);
});

test('the decrement method properly calls APC', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['decrement'])->getMock();
    $apc->expects($this->once())->method('decrement')->with($this->equalTo('foo'), $this->equalTo(5));
    $store = new ApcStore($apc);
    $store->decrement('foo', 5);
});

test('storing an item forever properly calls APC', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['put'])->getMock();
    $apc->expects($this->once())
        ->method('put')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(0))
        ->willReturn(true);
    $store = new ApcStore($apc);
    $result = $store->forever('foo', 'bar');

    expect($result)->toBeTrue();
});

test('the forget method properly calls APC', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['delete'])->getMock();
    $apc->expects($this->once())->method('delete')->with($this->equalTo('foo'))->willReturn(true);
    $store = new ApcStore($apc);
    $result = $store->forget('foo');

    expect($result)->toBeTrue();
});

test('flushes the cache', function () {
    $apc = $this->getMockBuilder(ApcWrapper::class)->onlyMethods(['flush'])->getMock();
    $apc->expects($this->once())->method('flush')->willReturn(true);
    $store = new ApcStore($apc);
    $result = $store->flush();

    expect($result)->toBeTrue();
});
