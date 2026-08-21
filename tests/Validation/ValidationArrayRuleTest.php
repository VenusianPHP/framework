<?php

use Tests\Validation\ArrayKeys;
use Tests\Validation\ArrayKeysBacked;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Validator;

test('it correctly formats a string version of the rule', function () {
    $rule = Rule::array();
    expect((string) $rule)->toBe('array');

    $rule = Rule::array([]);
    expect((string) $rule)->toBe('array');

    $rule = Rule::array('key_1', 'key_2', 'key_3');
    expect((string) $rule)->toBe('array:key_1,key_2,key_3');

    $rule = Rule::array(['key_1', 'key_2', 'key_3']);
    expect((string) $rule)->toBe('array:key_1,key_2,key_3');

    $rule = Rule::array(collect(['key_1', 'key_2', 'key_3']));
    expect((string) $rule)->toBe('array:key_1,key_2,key_3');

    $rule = Rule::array([ArrayKeys::key_1, ArrayKeys::key_2, ArrayKeys::key_3]);
    expect((string) $rule)->toBe('array:key_1,key_2,key_3');

    $rule = Rule::array([ArrayKeysBacked::key_1, ArrayKeysBacked::key_2, ArrayKeysBacked::key_3]);
    expect((string) $rule)->toBe('array:key_1,key_2,key_3');

    $rule = Rule::array(['key_1', 'key_1']);
    expect((string) $rule)->toBe('array:key_1,key_1');

    $rule = Rule::array([1, 2, 3]);
    expect((string) $rule)->toBe('array:1,2,3');
});

test('array validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['foo' => 'not an array'], ['foo' => Rule::array()]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['foo' => (object) ['key_1' => 'bar']], ['foo' => Rule::array()]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['foo' => null], ['foo' => ['nullable', Rule::array()]]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => []], ['foo' => Rule::array()]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => []]], ['foo' => Rule::array(['key_1'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['bar']], ['foo' => (string) Rule::array()]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar', 'key_2' => '']], ['foo' => Rule::array(['key_1', 'key_2'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar', 'key_2' => '']], ['foo' => ['required', Rule::array(['key_1', 'key_2'])]]);
    expect($v->passes())->toBeTrue();
});
