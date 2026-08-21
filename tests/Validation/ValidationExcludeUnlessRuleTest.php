<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\ExcludeUnless;
use Voyager\Validation\Validator;

beforeEach(function () {
    $this->translator = new Translator(new ArrayLoader, 'en');
});

test('instance of', function () {
    expect(Rule::excludeUnless(true))->toBeInstanceOf(ExcludeUnless::class);
});

test('boolean condition true', function () {
    $rule = Rule::excludeUnless(true);
    expect((string) $rule)->toBe('');
});

test('boolean condition false', function () {
    $rule = Rule::excludeUnless(false);
    expect((string) $rule)->toBe('exclude');
});

test('closure condition true', function () {
    $rule = Rule::excludeUnless(fn () => true);
    expect((string) $rule)->toBe('');
});

test('closure condition false', function () {
    $rule = Rule::excludeUnless(fn () => false);
    expect((string) $rule)->toBe('exclude');
});

test('field is excluded when condition false', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor', 'extra' => 'value'],
        [
            'name' => 'required|string',
            'extra' => [Rule::excludeUnless(false), 'string'],
        ],
    );

    expect($validator->passes())->toBeTrue()
        ->and($validator->validated())->not->toHaveKey('extra');
});

test('field is kept when condition true', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor', 'extra' => 'value'],
        [
            'name' => 'required|string',
            'extra' => [Rule::excludeUnless(true), 'string'],
        ],
    );

    expect($validator->passes())->toBeTrue()
        ->and($validator->validated())->toHaveKey('extra');
});

test('invalid condition throws', function () {
    Rule::excludeUnless('invalid');
})->throws(InvalidArgumentException::class);
