<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\Numeric;
use Voyager\Validation\Validator;

test('default numeric rule', function () {
        $rule = Rule::numeric();
        expect((string) $rule)->toEqual('numeric');

        $rule = new Numeric();
        expect((string) $rule)->toBe('numeric');
    });

test('between rule', function () {
        $rule = Rule::numeric()->between(1, 10);
        expect((string) $rule)->toEqual('numeric|between:1,10');

        $rule = Rule::numeric()->between(1.5, 10.5);
        expect((string) $rule)->toEqual('numeric|between:1.5,10.5');
    });

test('decimal rule', function () {
        $rule = Rule::numeric()->decimal(2, 4);
        expect((string) $rule)->toEqual('numeric|decimal:2,4');

        $rule = Rule::numeric()->decimal(2);
        expect((string) $rule)->toEqual('numeric|decimal:2');
    });

test('different rule', function () {
        $rule = Rule::numeric()->different('some_field');
        expect((string) $rule)->toEqual('numeric|different:some_field');
    });

test('digits rule', function () {
        $rule = Rule::numeric()->digits(10);
        expect((string) $rule)->toEqual('numeric|integer|digits:10');
    });

test('digits between rule', function () {
        $rule = Rule::numeric()->digitsBetween(2, 10);
        expect((string) $rule)->toEqual('numeric|integer|digits_between:2,10');
    });

test('greater than rule', function () {
        $rule = Rule::numeric()->greaterThan('some_field');
        expect((string) $rule)->toEqual('numeric|gt:some_field');
    });

test('greater than or equal rule', function () {
        $rule = Rule::numeric()->greaterThanOrEqualTo('some_field');
        expect((string) $rule)->toEqual('numeric|gte:some_field');
    });

test('integer rule', function () {
        $rule = Rule::numeric()->integer();
        expect((string) $rule)->toEqual('numeric|integer');

        $rule = Rule::numeric()->integer(strict: true);
        expect((string) $rule)->toEqual('numeric|integer:strict');
    });

test('less than rule', function () {
        $rule = Rule::numeric()->lessThan('some_field');
        expect((string) $rule)->toEqual('numeric|lt:some_field');
    });

test('less than or equal rule', function () {
        $rule = Rule::numeric()->lessThanOrEqualTo('some_field');
        expect((string) $rule)->toEqual('numeric|lte:some_field');
    });

test('max rule', function () {
        $rule = Rule::numeric()->max(10);
        expect((string) $rule)->toEqual('numeric|max:10');

        $rule = Rule::numeric()->max(10.5);
        expect((string) $rule)->toEqual('numeric|max:10.5');
    });

test('max digits rule', function () {
        $rule = Rule::numeric()->maxDigits(10);
        expect((string) $rule)->toEqual('numeric|max_digits:10');
    });

test('min rule', function () {
        $rule = Rule::numeric()->min(10);
        expect((string) $rule)->toEqual('numeric|min:10');

        $rule = Rule::numeric()->min(10.5);
        expect((string) $rule)->toEqual('numeric|min:10.5');
    });

test('min digits rule', function () {
        $rule = Rule::numeric()->minDigits(10);
        expect((string) $rule)->toEqual('numeric|min_digits:10');
    });

test('multiple of rule', function () {
        $rule = Rule::numeric()->multipleOf(10);
        expect((string) $rule)->toEqual('numeric|multiple_of:10');
    });

test('same rule', function () {
        $rule = Rule::numeric()->same('some_field');
        expect((string) $rule)->toEqual('numeric|same:some_field');
    });

test('size rule', function () {
        $rule = Rule::numeric()->exactly(10);
        expect((string) $rule)->toEqual('numeric|integer|size:10');
    });

test('chained rules', function () {
        $rule = Rule::numeric()
            ->integer()
            ->multipleOf(10)
            ->lessThanOrEqualTo('some_field')
            ->max(100);
        expect((string) $rule)->toEqual('numeric|integer|multiple_of:10|lte:some_field|max:100');

        $rule = Rule::numeric()
            ->decimal(2)
            ->when(true, function ($rule) {
                $rule->same('some_field');
            })
            ->unless(true, function ($rule) {
                $rule->different('some_field_2');
            });
        expect((string) $rule)->toBe('numeric|decimal:2|same:some_field');
    });

test('numeric validation', function () {
        $trans = new Translator(new ArrayLoader, 'en');

        $rule = Rule::numeric();

        $validator = new Validator(
            $trans,
            ['numeric' => 'NaN'],
            ['numeric' => $rule]
        );

        expect($validator->errors()->first('numeric'))->toBe($trans->get('validation.numeric'));

        $validator = new Validator(
            $trans,
            ['numeric' => '100'],
            ['numeric' => $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->between(10, 100);

        $validator = new Validator(
            $trans,
            ['numeric' => '50'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->different('some_field');

        $validator = new Validator(
            $trans,
            ['numeric' => '50', 'some_field' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->digits(2);

        $validator = new Validator(
            $trans,
            ['numeric' => '10'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->digitsBetween(2, 4);

        $validator = new Validator(
            $trans,
            ['numeric' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->greaterThan('some_field');

        $validator = new Validator(
            $trans,
            ['numeric' => '100', 'some_field' => '10'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->greaterThanOrEqualTo('some_field');

        $validator = new Validator(
            $trans,
            ['numeric' => '100', 'some_field' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->integer();

        $validator = new Validator(
            $trans,
            ['numeric' => '10'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->lessThan('some_field');

        $validator = new Validator(
            $trans,
            ['numeric' => '100', 'some_field' => '150'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->lessThanOrEqualTo('some_field');

        $validator = new Validator(
            $trans,
            ['numeric' => '100', 'some_field' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->max(200);

        $validator = new Validator(
            $trans,
            ['numeric' => '200'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->maxDigits(3);

        $validator = new Validator(
            $trans,
            ['numeric' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->min(2);

        $validator = new Validator(
            $trans,
            ['numeric' => '10'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->minDigits(2);

        $validator = new Validator(
            $trans,
            ['numeric' => '10'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->multipleOf(10);

        $validator = new Validator(
            $trans,
            ['numeric' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->same('some_field');

        $validator = new Validator(
            $trans,
            ['numeric' => '100', 'some_field' => '100'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->exactly(10);

        $validator = new Validator(
            $trans,
            ['numeric' => '10'],
            ['numeric' => (string) $rule]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();

        $rule = Rule::numeric()->exactly(10);

        $validator = new Validator(
            $trans,
            ['numeric' => 10],
            ['numeric' => [$rule]]
        );

        expect($validator->errors()->first('numeric'))->toBeEmpty();
    });

test('uniqueness validation', function () {
        $rule = Rule::numeric()->integer()->digits(2)->exactly(2);
        expect((string) $rule)->toEqual('numeric|integer|digits:2|size:2');
    });

