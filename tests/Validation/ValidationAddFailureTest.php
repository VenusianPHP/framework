<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Validator;

function validationAddFailureValidator()
{
    $trans = new Translator(new ArrayLoader, 'en');

    return new Validator($trans, ['foo' => ['bar' => ['baz' => '']]], ['foo.bar.baz' => 'sometimes|required']);
}

test('add failure exists', function () {
    $validator = validationAddFailureValidator();
    $method_name = 'addFailure';

    expect(method_exists($validator, $method_name))->toBeTrue()
        ->and(is_callable([$validator, $method_name]))->toBeTrue();
});

test('add failure is functional', function () {
    $attribute = 'Eugene';
    $validator = validationAddFailureValidator();
    $validator->addFailure($attribute, 'not_in');
    $messages = json_decode($validator->messages());

    expect($messages->{'foo.bar.baz'}[0])->toBe('validation.required')
        ->and($messages->{$attribute}[0])->toBe('validation.not_in');
});
