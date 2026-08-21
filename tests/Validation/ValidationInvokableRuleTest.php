<?php

use Voyager\Contracts\Validation\DataAwareRule;
use Voyager\Contracts\Validation\ValidationRule;
use Voyager\Contracts\Validation\ValidatorAwareRule;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\InvokableValidationRule;
use Voyager\Validation\Validator;

function invokableArrayTranslator()
{
    return new Translator(
        new ArrayLoader(),
        'en'
    );
}

test('it can pass', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                //
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->passes())->toBeTrue();
        expect($validator->messages()->messages())->toBe([]);
    });

test('it can fail', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail("The {$attribute} attribute is not 'foo'. Got '{$value}' instead.");
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                "The foo attribute is not 'foo'. Got 'bar' instead.",
            ],
        ]);
    });

test('it can return multiple error messages', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('Error message 1.');
                $fail('Error message 2.');
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'Error message 1.',
                'Error message 2.',
            ],
        ]);
    });

test('it can translate messages', function () {
        $trans = invokableArrayTranslator();
        $trans->addLines(['validation.translated-error' => 'Translated error message.'], 'en');
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('validation.translated-error')->translate();
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'Translated error message.',
            ],
        ]);
    });

test('it performs replacements when translating', function () {
        $trans = invokableArrayTranslator();
        $trans->addLines(['validation.translated-error' => 'attribute: :attribute input: :input position: :position index: :index baz: :baz'], 'en');
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                if ($value !== null) {
                    $fail('validation.translated-error')->translate([
                        'baz' => 'xxxx',
                    ]);
                }
            }
        };

        $validator = new Validator($trans, ['foo' => [null, 'bar']], ['foo.*' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo.1' => [
                'attribute: foo.1 input: bar position: 2 index: 1 baz: xxxx',
            ],
        ]);
    });

test('it looks for language file customisations', function () {
        $trans = invokableArrayTranslator();
        $trans->addLines(['validation.translated-error' => 'attribute: :attribute'], 'en');
        $trans->addLines(['validation.attributes.foo' => 'email address'], 'en');
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                if ($value !== null) {
                    $fail('validation.translated-error')->translate();
                }
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'attribute: email address',
            ],
        ]);
    });

test('it can specify locale when translating', function () {
        $trans = invokableArrayTranslator();
        $trans->addLines(['validation.translated-error' => 'English'], 'en');
        $trans->addLines(['validation.translated-error' => 'French'], 'fr');
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('validation.translated-error')->translate([], 'en');
                $fail('validation.translated-error')->translate([], 'fr');
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'English',
                'French',
            ],
        ]);
    });

test('it can access data during validation', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule, DataAwareRule
        {
            public $data = [];

            public function setData($data)
            {
                $this->data = $data;
            }

            public function validate($attribute, $value, $fail): void
            {
                if ($this->data === []) {
                    $fail('xxxx');
                }
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar', 'bar' => 'baz'], ['foo' => $rule]);

        expect($validator->passes())->toBeTrue();
        expect($rule->data)->toBe([
            'foo' => 'bar',
            'bar' => 'baz',
        ]);
    });

test('it can access validator during validation', function () {
        $trans = invokableArrayTranslator();

        $rule = new class() implements ValidationRule, ValidatorAwareRule
        {
            public $validator = null;

            public function setValidator($validator)
            {
                $this->validator = $validator;
            }

            public function validate($attribute, $value, $fail): void
            {
                if ($this->validator === null) {
                    $fail('xxxx');
                }
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar', 'bar' => 'baz'], ['foo' => $rule]);

        expect($validator->passes())->toBeTrue();
        expect($rule->validator)->toBe($validator);
    });

test('it can be explicit', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public $implicit = false;

            public function validate($attribute, $value, $fail): void
            {
                $fail('xxxx');
            }
        };

        $validator = new Validator($trans, ['foo' => ''], ['foo' => $rule]);

        expect($validator->passes())->toBeTrue();
        expect($validator->messages()->messages())->toBe([]);
    });

test('it can be implicit', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public $implicit = true;

            public function validate($attribute, $value, $fail): void
            {
                $fail('xxxx');
            }
        };

        $validator = new Validator($trans, ['foo' => ''], ['foo' => $rule]);

        expect($validator->passes())->toBeFalse();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'xxxx',
            ],
        ]);
    });

test('it is explicit by default', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('xxxx');
            }
        };

        $validator = new Validator($trans, ['foo' => ''], ['foo' => $rule]);

        expect($validator->passes())->toBeTrue();
        expect($validator->messages()->messages())->toBe([]);
    });

test('it can specify the validation error key for the error message', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('bar.baz', 'Another attribute error.');
                $fail('This attribute error.');
            }
        };

        $validator = new Validator($trans, ['foo' => 'xxxx'], ['foo' => $rule]);

        expect($validator->passes())->toBeFalse();
        expect($validator->messages()->messages())->toBe([
            'bar.baz' => [
                'Another attribute error.',
            ],
            'foo' => [
                'This attribute error.',
            ],
        ]);
    });

test('it can translate with choices', function () {
        $trans = invokableArrayTranslator();
        $trans->addLines(['validation.translated-error' => 'There is one error.|There are many errors.'], 'en');
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('validation.translated-error')->translateChoice(2);
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule]);

        expect($validator->fails())->toBeTrue();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'There are many errors.',
            ],
        ]);
    });

test('explicit rule can use inline validation messages', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public $implicit = false;

            public function validate($attribute, $value, $fail): void
            {
                $fail('xxxx');
            }
        };

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule], [$rule::class => ':attribute custom.']);

        expect($validator->passes())->toBeFalse();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'foo custom.',
            ],
        ]);

        $validator = new Validator($trans, ['foo' => 'bar'], ['foo' => $rule], ['foo.'.$rule::class => ':attribute custom with key.']);

        expect($validator->passes())->toBeFalse();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'foo custom with key.',
            ],
        ]);
    });

test('implicit rule can use inline validation messages', function () {
        $trans = invokableArrayTranslator();
        $rule = new class() implements ValidationRule
        {
            public $implicit = true;

            public function validate($attribute, $value, $fail): void
            {
                $fail('xxxx');
            }
        };

        $validator = new Validator($trans, ['foo' => ''], ['foo' => $rule], [$rule::class => ':attribute custom.']);

        expect($validator->passes())->toBeFalse();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'foo custom.',
            ],
        ]);

        $validator = new Validator($trans, ['foo' => ''], ['foo' => $rule], ['foo.'.$rule::class => ':attribute custom with key.']);

        expect($validator->passes())->toBeFalse();
        expect($validator->messages()->messages())->toBe([
            'foo' => [
                'foo custom with key.',
            ],
        ]);
    });

test('it can return invokable rule', function () {
        $rule = new class() implements ValidationRule
        {
            public function validate($attribute, $value, $fail): void
            {
                $fail('xxxx');
            }
        };

        $invokableValidationRule = InvokableValidationRule::make($rule);

        expect($invokableValidationRule->invokable())->toBe($rule);
    });

