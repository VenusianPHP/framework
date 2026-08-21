<?php

use Voyager\NutsAndBolts\NamespacedItemResolver;

test('parseKey splits a key into namespace, group and item', function (string $key, array $expected) {
    expect((new NamespacedItemResolver)->parseKey($key))->toEqual($expected);
})->with([
    'namespaced with item' => ['foo::bar.baz', ['foo', 'bar', 'baz']],
    'namespaced group'     => ['foo::bar', ['foo', 'bar', null]],
    'basic with item'      => ['bar.baz', [null, 'bar', 'baz']],
    'basic group'          => ['bar', [null, 'bar', null]],
]);

test('parsed keys are cached', function () {
    $r = Mockery::mock(NamespacedItemResolver::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $r->setParsedKey('foo.bar', ['foo']);
    $r->shouldNotReceive('parseBasicSegments');
    $r->shouldNotReceive('parseNamespacedSegments');

    expect($r->parseKey('foo.bar'))->toEqual(['foo']);
});

test('parsed keys may be flushed', function () {
    $r = Mockery::mock(NamespacedItemResolver::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $r->shouldReceive('parseBasicSegments')->once()->andReturn(['bar']);

    $r->setParsedKey('foo.bar', ['foo']);
    $r->flushParsedKeys();

    expect($r->parseKey('foo.bar'))->toEqual(['bar']);
});
