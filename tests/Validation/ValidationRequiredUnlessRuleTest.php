<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\RequiredUnless;
use Voyager\Validation\Validator;

beforeEach(function () {
    $this->translator = new Translator(new ArrayLoader, 'en');
});

test('instance of', function () {
    expect(Rule::requiredUnless(true))->toBeInstanceOf(RequiredUnless::class);
});

test('boolean condition true', function () {
    $rule = Rule::requiredUnless(true);
    expect((string) $rule)->toBe('');
});

test('boolean condition false', function () {
    $rule = Rule::requiredUnless(false);
    expect((string) $rule)->toBe('required');
});

test('closure condition true', function () {
    $rule = Rule::requiredUnless(fn () => true);
    expect((string) $rule)->toBe('');
});

test('closure condition false', function () {
    $rule = Rule::requiredUnless(fn () => false);
    expect((string) $rule)->toBe('required');
});

test('field is required when condition false', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor'],
        [
            'name' => 'required|string',
            'age' => [Rule::requiredUnless(false), 'integer'],
        ],
    );

    expect($validator->fails())->toBeTrue();
});

test('field is optional when condition true', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor'],
        [
            'name' => 'required|string',
            'age' => [Rule::requiredUnless(true), 'integer'],
        ],
    );

    expect($validator->passes())->toBeTrue();
});

test('invalid condition throws', function () {
    Rule::requiredUnless('invalid');
})->throws(InvalidArgumentException::class);
