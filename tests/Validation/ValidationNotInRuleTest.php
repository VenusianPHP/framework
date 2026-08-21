<?php

use Tests\Validation\fixtures\Values;
use Tests\Validation\IntegerStatus;
use Tests\Validation\PureEnum;
use Tests\Validation\StringStatus;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\NotIn;
use Voyager\Validation\Validator;

test('it correctly formats a string version of the rule', function () {
    $rule = new NotIn(['Laravel', 'Framework', 'PHP']);
    expect((string) $rule)->toBe('not_in:"Laravel","Framework","PHP"');

    $rule = new NotIn(collect(['Taylor', 'Michael', 'Tim']));
    expect((string) $rule)->toBe('not_in:"Taylor","Michael","Tim"');

    $rule = Rule::notIn(collect([1, 2, 3, 4]));
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn(collect([1, 2, 3, 4]));
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn([1, 2, 3, 4]);
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn(collect([1, 2, 3, 4]));
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn(new Values);
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = new NotIn(new Values);
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn('1', '2', '3', '4');
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = new NotIn('1', '2', '3', '4');
    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn([StringStatus::done]);
    expect((string) $rule)->toBe('not_in:"done"');

    $rule = Rule::notIn([IntegerStatus::done]);
    expect((string) $rule)->toBe('not_in:"2"');

    $rule = Rule::notIn([PureEnum::one]);
    expect((string) $rule)->toBe('not_in:"one"');
});

test('not in rule validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['x' => 'foo'], ['x' => Rule::notIn('bar', 'baz')]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => (string) Rule::notIn('bar', 'baz')]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => [Rule::notIn('foo', 'bar')]]);
    expect($v->passes())->toBeFalse();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['required', Rule::notIn('bar', 'baz')]]);
    expect($v->passes())->toBeTrue();
});
