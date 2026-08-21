<?php

use Tests\NutsAndBolts\Fixtures\ForwardsCallsOne;

test('a call is forwarded one level', function () {
    expect((new ForwardsCallsOne)->forwardedTwo('foo', 'bar'))->toEqual(['foo', 'bar']);
});

test('a call is forwarded through nested objects', function () {
    expect((new ForwardsCallsOne)->forwardedBase('foo', 'bar'))->toEqual(['foo', 'bar']);
});

test('a missing forwarded call reports the original class', function () {
    (new ForwardsCallsOne)->missingMethod('foo', 'bar');
})->throws(
    BadMethodCallException::class,
    'Call to undefined method Tests\NutsAndBolts\Fixtures\ForwardsCallsOne::missingMethod()',
);

test('a missing alphanumeric forwarded call reports the original class', function () {
    (new ForwardsCallsOne)->this1_shouldWork_too('foo', 'bar');
})->throws(
    BadMethodCallException::class,
    'Call to undefined method Tests\NutsAndBolts\Fixtures\ForwardsCallsOne::this1_shouldWork_too()',
);

test('an error raised inside the target is not tampered with', function () {
    (new ForwardsCallsOne)->baseError('foo', 'bar');
})->throws(
    Error::class,
    'Call to undefined method Tests\NutsAndBolts\Fixtures\ForwardsCallsBase::missingMethod()',
);

test('throwBadMethodCallException names the calling class', function () {
    (new ForwardsCallsOne)->throwTestException('test');
})->throws(
    BadMethodCallException::class,
    'Call to undefined method Tests\NutsAndBolts\Fixtures\ForwardsCallsOne::test()',
);
