<?php

use Voyager\Testing\Assert;
use Voyager\Testing\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

test('array subset', function () {
    Assert::assertArraySubset([
        'string' => 'string',
        'object' => new stdClass(),
    ], [
        'int' => 1,
        'string' => 'string',
        'object' => new stdClass(),
    ]);
});

test('array subset may fail', function () {
    Assert::assertArraySubset([
        'int' => 2,
        'string' => 'string',
        'object' => new stdClass(),
    ], [
        'int' => 1,
        'string' => 'string',
        'object' => new stdClass(),
    ]);
})->throws(ExpectationFailedException::class);

test('array subset with strict', function () {
    Assert::assertArraySubset([
        'string' => 'string',
        'object' => $object = new stdClass(),
    ], [
        'int' => 1,
        'string' => 'string',
        'object' => $object,
    ], true);
});

test('array subset with strict may fail', function () {
    Assert::assertArraySubset([
        'string' => 'string',
        'object' => new stdClass(),
    ], [
        'int' => 1,
        'string' => 'string',
        'object' => new stdClass(),
    ], true);
})->throws(ExpectationFailedException::class);

test('array subset may fail if array is not array', function () {
    Assert::assertArraySubset('string', [
        'int' => 1,
        'string' => 'string',
        'object' => new stdClass(),
    ]);
})->throws(
    InvalidArgumentException::class,
    'Argument #1 of Voyager\Testing\Assert::assertArraySubset() must be an array or ArrayAccess'
);

test('array subset may fail if subset is not array', function () {
    Assert::assertArraySubset([
        'string' => 'string',
        'object' => new stdClass(),
    ], 'string');
})->throws(
    InvalidArgumentException::class,
    'Argument #2 of Voyager\Testing\Assert::assertArraySubset() must be an array or ArrayAccess'
);
