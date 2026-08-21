<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\ProhibitedUnless;
use Voyager\Validation\Validator;

beforeEach(function () {
    $this->translator = new Translator(new ArrayLoader, 'en');
});

test('instance of', function () {
    expect(Rule::prohibitedUnless(true))->toBeInstanceOf(ProhibitedUnless::class);
});

test('boolean condition true', function () {
    $rule = Rule::prohibitedUnless(true);
    expect((string) $rule)->toBe('');
});

test('boolean condition false', function () {
    $rule = Rule::prohibitedUnless(false);
    expect((string) $rule)->toBe('prohibited');
});

test('closure condition true', function () {
    $rule = Rule::prohibitedUnless(fn () => true);
    expect((string) $rule)->toBe('');
});

test('closure condition false', function () {
    $rule = Rule::prohibitedUnless(fn () => false);
    expect((string) $rule)->toBe('prohibited');
});

test('field is prohibited when condition false', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor', 'secret' => 'value'],
        [
            'name' => 'required|string',
            'secret' => [Rule::prohibitedUnless(false)],
        ],
    );

    expect($validator->fails())->toBeTrue();
});

test('field is allowed when condition true', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor', 'secret' => 'value'],
        [
            'name' => 'required|string',
            'secret' => [Rule::prohibitedUnless(true)],
        ],
    );

    expect($validator->passes())->toBeTrue();
});

test('invalid condition throws', function () {
    Rule::prohibitedUnless('invalid');
})->throws(InvalidArgumentException::class);
