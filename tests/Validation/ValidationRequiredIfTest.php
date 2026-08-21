<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rules\RequiredIf;
use Voyager\Validation\Validator;

test('it closure returns formats a string version of the rule', function () {
    $rule = new RequiredIf(function () {
        return true;
    });

    expect((string) $rule)->toBe('required');

    $rule = new RequiredIf(function () {
        return false;
    });

    expect((string) $rule)->toBe('');

    $rule = new RequiredIf(true);

    expect((string) $rule)->toBe('required');

    $rule = new RequiredIf(false);

    expect((string) $rule)->toBe('');
});

test('it only callable and boolean are acceptable arguments of the rule', function () {
    $rule = new RequiredIf(false);

    $rule = new RequiredIf(true);

    $rule = new RequiredIf('phpinfo');
})->throws(InvalidArgumentException::class);

test('it returned rule is not serializable', function () {
    $rule = serialize(new RequiredIf(function () {
        return true;
    }));
})->throws(Exception::class);

test('required if rule validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $rule = new RequiredIf(true);

    $v = new Validator($trans, ['x' => 'foo'], ['x' => $rule]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => ''], ['x' => (string) $rule]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => [$rule]]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->passes())->toBeTrue();

    $rule = new RequiredIf(false);

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->passes())->toBeTrue();

    $rule = new RequiredIf(null);

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->passes())->toBeTrue();
});
