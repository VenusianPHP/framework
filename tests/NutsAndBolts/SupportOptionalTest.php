<?php

use Voyager\NutsAndBolts\Optional;

describe('object targets', function () {
    test('an existing property is read through', function () {
        $targetObj = new stdClass;
        $targetObj->item = 'test';

        expect((new Optional($targetObj))->item)->toEqual('test');
    });

    test('a missing property reads as null', function () {
        expect((new Optional(new stdClass))->item)->toBeNull();
    });

    test('isset is true for an existing property, even when empty', function () {
        $targetObj = new stdClass;
        $targetObj->item = '';

        expect(isset((new Optional($targetObj))->item))->toBeTrue();
    });

    test('isset is false for a missing property', function () {
        expect(isset((new Optional(new stdClass))->item))->toBeFalse();
    });
});

describe('array targets', function () {
    test('an existing key is read through', function () {
        expect((new Optional(['item' => 'test']))['item'])->toEqual('test');
    });

    test('a missing key reads as null', function () {
        expect((new Optional([]))['item'])->toBeNull();
    });

    test('isset is true for an existing key, by offset and by property', function () {
        $optional = new Optional(['item' => '']);

        expect(isset($optional['item']))->toBeTrue()
            ->and(isset($optional->item))->toBeTrue();
    });

    test('isset is false for a missing key, by offset and by property', function () {
        $optional = new Optional([]);

        expect(isset($optional['item']))->toBeFalse()
            ->and(isset($optional->item))->toBeFalse();
    });
});

test('isset is false on a null target', function () {
    expect(isset((new Optional(null))->item))->toBeFalse();
});
