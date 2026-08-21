<?php

use Voyager\JsonSchema\Types\StringType;

test('it sets a min length', function () {
    $type = (new StringType)->min(5);

    expect($type->toArray())->toEqual([
        'type' => 'string',
        'minLength' => 5,
    ]);
});

test('it sets a max length', function () {
    $type = (new StringType)->description('User handle')->max(10);

    expect($type->toArray())->toEqual([
        'type' => 'string',
        'description' => 'User handle',
        'maxLength' => 10,
    ]);
});

test('it sets a pattern', function () {
    $type = (new StringType)->default('foo')->pattern('^foo.*$');

    expect($type->toArray())->toEqual([
        'type' => 'string',
        'default' => 'foo',
        'pattern' => '^foo.*$',
    ]);
});

test('it sets a format', function () {
    $type = (new StringType)->default('foo')->format('date');

    expect($type->toArray())->toEqual([
        'type' => 'string',
        'default' => 'foo',
        'format' => 'date',
    ]);
});

test('it sets an enum', function () {
    $type = (new StringType)->enum(['draft', 'published']);

    expect($type->toArray())->toEqual([
        'type' => 'string',
        'enum' => ['draft', 'published'],
    ]);
});
