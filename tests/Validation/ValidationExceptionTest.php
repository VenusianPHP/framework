<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\ValidationException;
use Voyager\Validation\Validator;

function validationExceptionTranslator($locale = 'en', $loaded = [])
{
    $translator = new Translator(new ArrayLoader, $locale);
    $translator->setLoaded($loaded);

    return $translator;
}

function validationExceptionValidator($data = [], $rules = [], $translator = null)
{
    $translator ??= validationExceptionTranslator();

    return new Validator($translator, $data, $rules);
}

function validationExceptionFor($data = [], $rules = [], $translator = null)
{
    $validator = validationExceptionValidator($data, $rules, $translator);

    return new ValidationException($validator);
}

test('exception summarizes zero errors', function () {
    $exception = validationExceptionFor([], []);

    expect($exception->getMessage())->toBe('The given data was invalid.');
});

test('exception summarizes one error', function () {
    $exception = validationExceptionFor([], ['foo' => 'required']);

    expect($exception->getMessage())->toBe('validation.required');
});

test('exception summarizes two errors', function () {
    $exception = validationExceptionFor([], ['foo' => 'required', 'bar' => 'required']);

    expect($exception->getMessage())->toBe('validation.required (and 1 more error)');
});

test('exception summarizes three or more errors', function () {
    $exception = validationExceptionFor([], [
        'foo' => 'required',
        'bar' => 'required',
        'baz' => 'required',
    ]);

    expect($exception->getMessage())->toBe('validation.required (and 2 more errors)');
});

test('exception translated summarizes two errors', function () {
    $translator = validationExceptionTranslator('uk', [
        '*' => [
            '*' => [
                'uk' => [
                    '(and :count more error)' => '(та ще :count помилка)',
                    '(and :count more errors)' => '(та ще :count помилка)|(та ще :count помилки)|(та ще :count помилок)',
                ],
            ],
        ],
    ]);

    $exception = validationExceptionFor([], [
        'foo' => 'required',
        'bar' => 'required',
    ], $translator);

    expect($exception->getMessage())->toBe('validation.required (та ще 1 помилка)');
});

test('exception translated summarizes three or more errors', function () {
    $translator = validationExceptionTranslator('uk', [
        '*' => [
            '*' => [
                'uk' => [
                    '(and :count more error)' => '(та ще :count помилка)',
                    '(and :count more errors)' => '(та ще :count помилка)|(та ще :count помилки)|(та ще :count помилок)',
                ],
            ],
        ],
    ]);

    $exception = validationExceptionFor([], [
        'foo' => 'required',
        'bar' => 'required',
        'baz' => 'required',
    ], $translator);

    expect($exception->getMessage())->toBe('validation.required (та ще 2 помилки)');
});

test('exception translated summarizes five or more errors', function () {
    $translator = validationExceptionTranslator('uk', [
        '*' => [
            '*' => [
                'uk' => [
                    '(and :count more error)' => '(та ще :count помилка)',
                    '(and :count more errors)' => '(та ще :count помилка)|(та ще :count помилки)|(та ще :count помилок)',
                ],
            ],
        ],
    ]);

    $exception = validationExceptionFor([], [
        'foo' => 'required',
        'bar' => 'required',
        'baz' => 'required',
        'baq' => 'required',
        'baw' => 'required',
        'bae' => 'required',
    ], $translator);

    expect($exception->getMessage())->toBe('validation.required (та ще 5 помилок)');
});

test('exception error zero errors', function () {
    $exception = validationExceptionFor([], []);

    expect($exception->errors())->toBe([]);
});

test('exception error one error', function () {
    $exception = validationExceptionFor([], ['foo' => 'required']);

    expect($exception->errors())->toBe(['foo' => ['validation.required']]);
});

test('exception error bag one error', function () {
    $exception = validationExceptionFor([], ['foo' => 'required']);
    $exception->errorBag('milwad');

    expect($exception->errorBag)->toEqual('milwad');
});

// Laravel also covers `status()`, `redirectTo()` and `getResponse()` here.
// Those shape an HTTP response, so they are cut from ValidationException
// along with the rest of the response surface.

test('get exception class from validator', function () {
    $validator = validationExceptionValidator();

    $exception = $validator->getException();

    expect($exception)->toEqual(ValidationException::class);
});
