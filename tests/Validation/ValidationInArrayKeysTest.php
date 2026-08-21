<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Validator;

function inArrayKeysTranslator()
{
    return new Translator(new ArrayLoader, 'en');
}

test('in array keys validation', function () {
    $trans = inArrayKeysTranslator();

    // Test passes when array has at least one of the specified keys
    $v = new Validator($trans, ['foo' => ['first_key' => 'bar', 'second_key' => 'baz']], ['foo' => 'in_array_keys:first_key,third_key']);
    expect($v->passes())->toBeTrue();

    // Test passes when array has multiple of the specified keys
    $v = new Validator($trans, ['foo' => ['first_key' => 'bar', 'second_key' => 'baz']], ['foo' => 'in_array_keys:first_key,second_key']);
    expect($v->passes())->toBeTrue();

    // Test fails when array doesn't have any of the specified keys
    $v = new Validator($trans, ['foo' => ['first_key' => 'bar', 'second_key' => 'baz']], ['foo' => 'in_array_keys:third_key,fourth_key']);
    expect($v->fails())->toBeTrue();

    // Test fails when value is not an array
    $v = new Validator($trans, ['foo' => 'not-an-array'], ['foo' => 'in_array_keys:first_key']);
    expect($v->fails())->toBeTrue();

    // Test fails when no keys are specified
    $v = new Validator($trans, ['foo' => ['first_key' => 'bar']], ['foo' => 'in_array_keys:']);
    expect($v->fails())->toBeTrue();
});

test('in array keys validation with nested arrays', function () {
    $trans = inArrayKeysTranslator();

    // Test passes with nested arrays
    $v = new Validator($trans, [
        'foo' => [
            'first_key' => ['nested' => 'value'],
            'second_key' => 'baz',
        ],
    ], ['foo' => 'in_array_keys:first_key,third_key']);
    expect($v->passes())->toBeTrue();

    // Test with dot notation for nested arrays
    $v = new Validator($trans, [
        'foo' => [
            'first' => [
                'nested_key' => 'value',
            ],
        ],
    ], ['foo.first' => 'in_array_keys:nested_key']);
    expect($v->passes())->toBeTrue();
});

test('in array keys validation error message', function () {
    $trans = inArrayKeysTranslator();
    $trans->addLines([
        'validation.in_array_keys' => 'The :attribute field must contain at least one of the following keys: :values.',
    ], 'en');

    $v = new Validator($trans, ['foo' => ['wrong_key' => 'bar']], ['foo' => 'in_array_keys:first_key,second_key']);
    expect($v->passes())->toBeFalse()
        ->and($v->messages()->first('foo'))->toEqual('The foo field must contain at least one of the following keys: first_key, second_key.');
});
