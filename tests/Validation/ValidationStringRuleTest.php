<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\StringRule;
use Voyager\Validation\Validator;

test('default string rule', function () {
        $rule = Rule::string();
        expect((string) $rule)->toBe('string');

        $rule = new StringRule();
        expect((string) $rule)->toBe('string');
    });

test('min rule', function () {
        $rule = Rule::string()->min(3);
        expect((string) $rule)->toBe('string|min:3');
    });

test('max rule', function () {
        $rule = Rule::string()->max(255);
        expect((string) $rule)->toBe('string|max:255');
    });

test('between rule', function () {
        $rule = Rule::string()->between(3, 255);
        expect((string) $rule)->toBe('string|between:3,255');
    });

test('exactly rule', function () {
        $rule = Rule::string()->exactly(10);
        expect((string) $rule)->toBe('string|size:10');
    });

test('alpha rule', function () {
        $rule = Rule::string()->alpha();
        expect((string) $rule)->toBe('string|alpha');

        $rule = Rule::string()->alpha(ascii: true);
        expect((string) $rule)->toBe('string|alpha:ascii');
    });

test('alpha numeric rule', function () {
        $rule = Rule::string()->alphaNumeric();
        expect((string) $rule)->toBe('string|alpha_num');

        $rule = Rule::string()->alphaNumeric(ascii: true);
        expect((string) $rule)->toBe('string|alpha_num:ascii');
    });

test('alpha dash rule', function () {
        $rule = Rule::string()->alphaDash();
        expect((string) $rule)->toBe('string|alpha_dash');

        $rule = Rule::string()->alphaDash(ascii: true);
        expect((string) $rule)->toBe('string|alpha_dash:ascii');
    });

test('ascii rule', function () {
        $rule = Rule::string()->ascii();
        expect((string) $rule)->toBe('string|ascii');
    });

test('uppercase rule', function () {
        $rule = Rule::string()->uppercase();
        expect((string) $rule)->toBe('string|uppercase');
    });

test('lowercase rule', function () {
        $rule = Rule::string()->lowercase();
        expect((string) $rule)->toBe('string|lowercase');
    });

test('starts with rule', function () {
        $rule = Rule::string()->startsWith('foo');
        expect((string) $rule)->toBe('string|starts_with:foo');

        $rule = Rule::string()->startsWith('foo', 'bar');
        expect((string) $rule)->toBe('string|starts_with:foo,bar');
    });

test('ends with rule', function () {
        $rule = Rule::string()->endsWith('.com');
        expect((string) $rule)->toBe('string|ends_with:.com');

        $rule = Rule::string()->endsWith('.com', '.org');
        expect((string) $rule)->toBe('string|ends_with:.com,.org');
    });

test('doesnt start with rule', function () {
        $rule = Rule::string()->doesntStartWith('foo');
        expect((string) $rule)->toBe('string|doesnt_start_with:foo');

        $rule = Rule::string()->doesntStartWith('foo', 'bar');
        expect((string) $rule)->toBe('string|doesnt_start_with:foo,bar');
    });

test('doesnt end with rule', function () {
        $rule = Rule::string()->doesntEndWith('.exe');
        expect((string) $rule)->toBe('string|doesnt_end_with:.exe');

        $rule = Rule::string()->doesntEndWith('.exe', '.bat');
        expect((string) $rule)->toBe('string|doesnt_end_with:.exe,.bat');
    });

test('chained rules', function () {
        $rule = Rule::string()
            ->min(3)
            ->max(255)
            ->alpha()
            ->uppercase();
        expect((string) $rule)->toBe('string|min:3|max:255|alpha|uppercase');

        $rule = Rule::string()
            ->between(1, 100)
            ->when(true, function ($rule) {
                $rule->startsWith('prefix');
            })
            ->unless(true, function ($rule) {
                $rule->endsWith('suffix');
            });
        expect((string) $rule)->toBe('string|between:1,100|starts_with:prefix');
    });

test('string validation', function () {
        $trans = new Translator(new ArrayLoader, 'en');

        $rule = Rule::string();

        $validator = new Validator(
            $trans,
            ['field' => 123],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->toBe($trans->get('validation.string'));

        $validator = new Validator(
            $trans,
            ['field' => 'hello'],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->toBeEmpty();

        $rule = Rule::string()->min(3)->max(10);

        $validator = new Validator(
            $trans,
            ['field' => 'hello'],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->toBeEmpty();

        $rule = Rule::string()->min(3)->max(10);

        $validator = new Validator(
            $trans,
            ['field' => 'ab'],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->not->toBeEmpty();

        $rule = Rule::string()->min(3)->max(10);

        $validator = new Validator(
            $trans,
            ['field' => 'this string is too long'],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->not->toBeEmpty();

        $rule = Rule::string()->uppercase();

        $validator = new Validator(
            $trans,
            ['field' => 'HELLO'],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->toBeEmpty();

        $rule = Rule::string()->uppercase();

        $validator = new Validator(
            $trans,
            ['field' => 'hello'],
            ['field' => $rule]
        );

        expect($validator->errors()->first('field'))->not->toBeEmpty();
    });

test('uniqueness of constraints', function () {
        $rule = Rule::string()->alpha()->alpha();
        expect((string) $rule)->toBe('string|alpha');
    });

