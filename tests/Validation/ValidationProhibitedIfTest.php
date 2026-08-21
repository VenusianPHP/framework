<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rules\ProhibitedIf;
use Voyager\Validation\Validator;

test('it returns string version of rule when cast', function () {
    $rule = new ProhibitedIf(function () {
        return true;
    });

    expect((string) $rule)->toBe('prohibited');

    $rule = new ProhibitedIf(function () {
        return false;
    });

    expect((string) $rule)->toBe('');

    $rule = new ProhibitedIf(true);

    expect((string) $rule)->toBe('prohibited');

    $rule = new ProhibitedIf(false);

    expect((string) $rule)->toBe('');
});

test('it validates callable and boolean are acceptable arguments', function () {
    new ProhibitedIf(false);
    new ProhibitedIf(true);
    new ProhibitedIf(fn () => true);

    foreach ([1, 1.1, 'phpinfo', new stdClass] as $condition) {
        try {
            new ProhibitedIf($condition);
            $this->fail('The ProhibitedIf constructor must not accept '.gettype($condition));
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())->toEqual('The provided condition must be a callable or boolean.');
        }
    }
});

test('it throws exception if rule is not serializable', function () {
    serialize(new ProhibitedIf(function () {
        return true;
    }));
})->throws(Exception::class);

test('prohibited if rule validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $rule = new ProhibitedIf(true);

    $v = new Validator($trans, ['y' => 'foo'], ['x' => $rule]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['y' => 'foo'], ['x' => (string) $rule]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['y' => 'foo'], ['x' => [$rule]]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->fails())->toBeTrue();

    $rule = new ProhibitedIf(false);

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->passes())->toBeTrue();
});
