<?php

use Tests\Validation\IntegerStatus;
use Tests\Validation\PureEnum;
use Tests\Validation\StringStatus;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\Collection;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rules\Enum;
use Voyager\Validation\ValidationServiceProvider;
use Voyager\Validation\Validator;
use Voyager\Vessel\Vessel;

beforeEach(function () {
    $container = Vessel::getInstance();

    $container->bind('translator', function () {
        return new Translator(
            new ArrayLoader, 'en'
        );
    });

    MagicAlias::setMagicAliasApplication($container);

    (new ValidationServiceProvider($container))->register();
});

afterEach(function () {
    Vessel::setInstance(null);

    MagicAlias::clearResolvedInstances();

    MagicAlias::setMagicAliasApplication(null);
});

test('validation passes when passing correct enum', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 'pending',
                'int_status' => 1,
            ],
            [
                'status' => new Enum(StringStatus::class),
                'int_status' => new Enum(IntegerStatus::class),
            ]
        );

        expect($v->fails())->toBeFalse();
    });

test('validation passes when passing instance of enum', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => StringStatus::done,
            ],
            [
                'status' => new Enum(StringStatus::class),
            ]
        );

        expect($v->fails())->toBeFalse();
    });

test('validation passes when passing instance of pure enum', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => PureEnum::one,
            ],
            [
                'status' => new Enum(PureEnum::class),
            ]
        );

        expect($v->fails())->toBeFalse();
    });

test('validation fails when providing no existing cases', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 'finished',
            ],
            [
                'status' => new Enum(StringStatus::class),
            ]
        );

        expect($v->fails())->toBeTrue();
        expect($v->messages()->get('status'))->toEqual(['The selected status is invalid.']);
    });

test('validation passes for all cases until either only or except is passed', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status_1' => PureEnum::one,
                'status_2' => PureEnum::two,
                'status_3' => IntegerStatus::done->value,
            ],
            [
                'status_1' => new Enum(PureEnum::class),
                'status_2' => (new Enum(PureEnum::class))->only([])->except([]),
                'status_3' => new Enum(IntegerStatus::class),
            ],
        );

        expect($v->passes())->toBeTrue();
    });

test('validation passes when only cases provided', function (IntegerStatus|int $enum, array|Arrayable|IntegerStatus $only, bool $expected) {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => $enum,
            ],
            [
                'status' => (new Enum(IntegerStatus::class))->only($only),
            ],
        );

        expect($v->passes())->toBe($expected);
    })->with('conditionalCasesDataProvider');

test('validation passes when except cases provided', function (int|IntegerStatus $enum, array|Arrayable|IntegerStatus $except, bool $expected) {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => $enum,
            ],
            [
                'status' => (new Enum(IntegerStatus::class))->except($except),
            ],
        );

        expect($v->fails())->toBe($expected);
    })->with('conditionalCasesDataProvider');

test('only has higher order than except', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => PureEnum::one,
            ],
            [
                'status' => (new Enum(PureEnum::class))
                    ->only(PureEnum::one)
                    ->except(PureEnum::one),
            ],
        );

        expect($v->passes())->toBeTrue();
    });

test('validation fails when providing different type', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 10,
            ],
            [
                'status' => new Enum(StringStatus::class),
            ]
        );

        expect($v->fails())->toBeTrue();
        expect($v->messages()->get('status'))->toEqual(['The selected status is invalid.']);
    });

test('validation passes when providing different type that is castable to the enum type', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => '1',
            ],
            [
                'status' => new Enum(IntegerStatus::class),
            ]
        );

        expect($v->fails())->toBeFalse();
    });

test('validation fails when providing null', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => null,
            ],
            [
                'status' => new Enum(StringStatus::class),
            ]
        );

        expect($v->fails())->toBeTrue();
        expect($v->messages()->get('status'))->toEqual(['The selected status is invalid.']);
    });

test('validation passes when providing null but the field is nullable', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => null,
            ],
            [
                'status' => ['nullable', new Enum(StringStatus::class)],
            ]
        );

        expect($v->fails())->toBeFalse();
    });

test('validation fails on pure enum', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 'one',
            ],
            [
                'status' => ['required', new Enum(PureEnum::class)],
            ]
        );

        expect($v->fails())->toBeTrue();
    });

test('validation fails when providing string to integer type', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 'abc',
            ],
            [
                'status' => new Enum(IntegerStatus::class),
            ]
        );

        expect($v->fails())->toBeTrue();
        expect($v->messages()->get('status'))->toEqual(['The selected status is invalid.']);
    });

test('validation fails when using different case', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 'DONE',
            ],
            [
                'status' => new Enum(StringStatus::class),
            ]
        );

        expect($v->fails())->toBeTrue();
        expect($v->messages()->get('status'))->toEqual(['The selected status is invalid.']);
    });

test('custom message using dot notation and fqcn works', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'status' => 'invalid_value',
                'status_fqcn' => 'another_invalid',
            ],
            [
                'status' => new Enum(StringStatus::class),
                'status_fqcn' => new Enum(StringStatus::class),
            ],
            [
                'status.enum' => 'Please choose a valid status (dot notation)',
                'status_fqcn.Voyager\Validation\Rules\Enum' => 'Please choose a valid status (fqcn)',
            ]
        );

        expect($v->fails())->toBeTrue();

        expect($v->messages()->all())->toBe([
            'Please choose a valid status (dot notation)',
            'Please choose a valid status (fqcn)',
        ]);
    });

test('enum rule is stringable', function () {
        $rule = new Enum(StringStatus::class);

        expect((string) $rule)->toBe('in:"pending","done"');
    });

test('enum rule stringable with only', function () {
        $rule = (new Enum(StringStatus::class))->only([StringStatus::pending]);

        expect((string) $rule)->toBe('in:"pending"');
    });

test('enum rule stringable with except', function () {
        $rule = (new Enum(StringStatus::class))->except([StringStatus::pending]);

        expect((string) $rule)->toBe('in:"done"');
    });


dataset('conditionalCasesDataProvider', [
    [IntegerStatus::done, IntegerStatus::done, true],
    [IntegerStatus::done, [IntegerStatus::done, IntegerStatus::pending], true],
    // Laravel also covers Instrument's ArrayObject cast here; it arrives in wave 6.
    [IntegerStatus::done, new Collection([IntegerStatus::done, IntegerStatus::pending]), true],
    [IntegerStatus::pending->value, [IntegerStatus::done, IntegerStatus::pending], true],
    [IntegerStatus::done->value, IntegerStatus::pending, false],
]);
