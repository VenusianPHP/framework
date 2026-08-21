<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rules\ExcludeIf;
use Voyager\Validation\Validator;

test('it returns string version of rule when cast', function () {
    $rule = new ExcludeIf(function () {
        return true;
    });

    expect((string) $rule)->toBe('exclude');

    $rule = new ExcludeIf(function () {
        return false;
    });

    expect((string) $rule)->toBe('');

    $rule = new ExcludeIf(true);

    expect((string) $rule)->toBe('exclude');

    $rule = new ExcludeIf(false);

    expect((string) $rule)->toBe('');
});

test('it validates callable and boolean are acceptable arguments', function () {
    new ExcludeIf(false);
    new ExcludeIf(true);
    new ExcludeIf(fn () => true);

    foreach ([1, 1.1, 'phpinfo', new stdClass, null] as $condition) {
        try {
            new ExcludeIf($condition);
            $this->fail('The ExcludeIf constructor must not accept '.gettype($condition));
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())->toEqual('The provided condition must be a callable or boolean.');
        }
    }
});

test('it throws exception if rule is not serializable', function () {
    serialize(new ExcludeIf(function () {
        return true;
    }));
})->throws(Exception::class);

test('exclude if rule validation', function () {
    $ruleTrue = new ExcludeIf(true);

    $ruleFalse = new ExcludeIf(false);

    $trans = new Translator(new ArrayLoader, 'en');

    $data = ['foo' => 'FOO', 'bar' => 'BAR'];

    $v = new Validator($trans, $data, ['foo' => $ruleTrue, 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue()
        ->and($v->validated())->toBe(['bar' => 'BAR']);

    $v = new Validator($trans, $data, ['foo' => (string) $ruleTrue, 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue()
        ->and($v->validated())->toBe(['bar' => 'BAR']);

    $v = new Validator($trans, $data, ['foo' => [$ruleTrue], 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue()
        ->and($v->validated())->toBe(['bar' => 'BAR']);

    $v = new Validator($trans, $data, ['foo' => $ruleFalse, 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue()
        ->and($v->validated())->toBe($data);
});
