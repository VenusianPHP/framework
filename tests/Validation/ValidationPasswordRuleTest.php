<?php

use Voyager\Contracts\Validation\Rule as RuleContract;
use Voyager\MagicAliases\MagicAlias;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rules\Password;
use Voyager\Validation\ValidationServiceProvider;
use Voyager\Validation\Validator;
use Voyager\Vessel\Vessel;

function passwordAssertValidationRules($rule, $values, $result, $messages)
{
    foreach ($values as $value) {
        $v = new Validator(
            resolve('translator'),
            ['my_password' => $value, 'my_password_confirmation' => $value],
            ['my_password' => is_object($rule) ? clone $rule : $rule]
        );

        expect($v->passes())->toBe($result)
            ->and($v->messages()->toArray())->toBe($result ? [] : ['my_password' => $messages]);
    }
}

function passwordPasses($rule, $values)
{
    passwordAssertValidationRules($rule, $values, true, []);
}

function passwordFails($rule, $values, $messages)
{
    passwordAssertValidationRules($rule, $values, false, $messages);
}

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

    Password::$defaultCallback = null;
});

test('string', function () {
        passwordFails(Password::min(3), [['foo' => 'bar'], ['foo']], [
            'validation.string',
            'validation.min.string',
        ]);

        passwordFails(Password::min(3), [1234567, 545], [
            'validation.string',
        ]);

        passwordPasses(Password::min(3), ['abcd', '454qb^', '接2133手田']);
    });

test('min', function () {
        passwordFails(new Password(8), ['a', 'ff', '12'], [
            'validation.min.string',
        ]);

        passwordFails(Password::min(3), ['a', 'ff', '12'], [
            'validation.min.string',
        ]);

        passwordPasses(Password::min(3), ['333', 'abcd']);
        passwordPasses(new Password(8), ['88888888']);
    });

test('max', function () {
        passwordFails(Password::min(2)->max(4), ['aaaaa', '11111111'], [
            'validation.max.string',
        ]);

        passwordPasses(Password::min(2)->max(3), ['aa', '111']);
    });

test('conditional', function () {
        $is_privileged_user = true;
        $rule = (new Password(8))->when($is_privileged_user, function ($rule) {
            $rule->symbols();
        });

        passwordFails($rule, ['aaaaaaaa', '11111111'], [
            'validation.password.symbols',
        ]);

        $is_privileged_user = false;
        $rule = (new Password(8))->when($is_privileged_user, function ($rule) {
            $rule->symbols();
        });

        passwordPasses($rule, ['aaaaaaaa', '11111111']);
    });

test('mixed case', function () {
        passwordFails(Password::min(2)->mixedCase(), ['nn', 'MM'], [
            'validation.password.mixed',
        ]);

        passwordPasses(Password::min(2)->mixedCase(), ['Nn', 'Mn', 'âA']);
    });

test('letters', function () {
        passwordFails(Password::min(2)->letters(), ['11', '22', '^^', '``', '**'], [
            'validation.password.letters',
        ]);

        passwordPasses(Password::min(2)->letters(), ['1a', 'b2', 'â1', '1 京都府']);
    });

test('numbers', function () {
        passwordFails(Password::min(2)->numbers(), ['aa', 'bb', '  a', '京都府'], [
            'validation.password.numbers',
        ]);

        passwordPasses(Password::min(2)->numbers(), ['1a', 'b2', '00', '京都府 1']);
    });

test('default rules', function () {
        passwordFails(Password::min(3), [null], [
            'validation.string',
            'validation.min.string',
        ]);
    });

test('symbols', function () {
        passwordFails(Password::min(2)->symbols(), ['ab', '1v'], [
            'validation.password.symbols',
        ]);

        passwordPasses(Password::min(2)->symbols(), ['n^d', 'd^!', 'âè$', '金廿土弓竹中；']);
    });

test('uncompromised', function () {
        passwordFails(Password::min(2)->uncompromised(), [
            '123456',
            'password',
            'welcome',
            'abc123',
            '123456789',
            '12345678',
            'nuno',
        ], [
            'validation.password.uncompromised',
        ]);

        passwordPasses(Password::min(2)->uncompromised(9999999), [
            'nuno',
        ]);

        passwordPasses(Password::min(2)->uncompromised(), [
            '手田日尸Ｚ難金木水口火女月土廿卜竹弓一十山',
            '!p8VrB',
            '&xe6VeKWF#n4',
            '%HurHUnw7zM!',
            'rundeliekend',
            '7Z^k5EvqQ9g%c!Jt9$ufnNpQy#Kf',
            'NRs*Gz2@hSmB$vVBSPDfqbRtEzk4nF7ZAbM29VMW$BPD%b2U%3VmJAcrY5eZGVxP%z%apnwSX',
        ]);
    });

test('messages order', function () {
        $makeRules = function () {
            return ['required', Password::min(8)->mixedCase()->numbers()];
        };

        passwordFails($makeRules(), [null], [
            'validation.required',
        ]);

        passwordFails($makeRules(), ['foo', 'azdazd'], [
            'validation.min.string',
            'validation.password.mixed',
            'validation.password.numbers',
        ]);

        passwordFails($makeRules(), ['1231231'], [
            'validation.min.string',
            'validation.password.mixed',
        ]);

        passwordFails($makeRules(), ['4564654564564'], [
            'validation.password.mixed',
        ]);

        passwordFails($makeRules(), ['aaaaaaaaa', 'TJQSJQSIUQHS'], [
            'validation.password.mixed',
            'validation.password.numbers',
        ]);

        passwordPasses($makeRules(), ['4564654564564Abc']);

        $makeRules = function () {
            return ['nullable', 'confirmed', Password::min(8)->letters()->symbols()->uncompromised()];
        };

        passwordPasses($makeRules(), [null]);

        passwordFails($makeRules(), ['foo', 'azdazd'], [
            'validation.min.string',
            'validation.password.symbols',
        ]);

        passwordFails($makeRules(), ['1231231'], [
            'validation.min.string',
            'validation.password.letters',
            'validation.password.symbols',
        ]);

        passwordFails($makeRules(), ['aaaaaaaaa', 'TJQSJQSIUQHS'], [
            'validation.password.symbols',
        ]);

        passwordFails($makeRules(), ['4564654564564'], [
            'validation.password.letters',
            'validation.password.symbols',
        ]);

        passwordFails($makeRules(), ['abcabcabc!'], [
            'validation.password.uncompromised',
        ]);

        $v = new Validator(
            resolve('translator'),
            ['my_password' => 'Nuno'],
            ['my_password' => ['nullable', 'confirmed', Password::min(3)->letters()]]
        );

        expect($v->passes())->toBeFalse();

        expect($v->messages()->toArray())->toBe(['my_password' => ['validation.confirmed']]);
    });

test('it can use default', function () {
        expect(Password::default())->toBeInstanceOf(Password::class);
    });

test('it can set default using', function () {
        expect(Password::default())->toBeInstanceOf(Password::class);

        $password = Password::min(3);
        $password2 = Password::min(2)->mixedCase();

        Password::defaults(function () use ($password) {
            return $password;
        });

        passwordPasses(Password::default(), ['abcd', '454qb^', '接2133手田']);
        expect(Password::default())->toBe($password);
        expect(Password::required())->toBeInstanceOf(Password::class);
        expect(Password::sometimes())->toBeInstanceOf(Password::class);

        Password::defaults($password2);
        passwordPasses(Password::default(), ['Nn', 'Mn', 'âA']);
        expect(Password::default())->toBe($password2);
        expect(Password::required())->toBeInstanceOf(Password::class);
        expect(Password::sometimes())->toBeInstanceOf(Password::class);
    });

test('it cannot set default using given string', function () {
        Password::defaults('required|password');
    })->throws(InvalidArgumentException::class, 'given callback should be callable');

test('it passes with valid data if the same validation rules are reused', function () {
        $rules = [
            'password' => Password::default(),
        ];

        $v = new Validator(
            resolve('translator'),
            ['password' => '1234'],
            $rules
        );

        expect($v->passes())->toBeFalse();

        $v1 = new Validator(
            resolve('translator'),
            ['password' => '12341234'],
            $rules
        );

        expect($v1->passes())->toBeTrue();
    });

test('custom messages', function () {
        $rules = [
            'my_password' => Password::min(6)->letters(),
        ];

        $messages = [
            'min' => 'Message for validating length',
            'password.letters' => 'Message for validating letters',
        ];

        $v = new Validator(
            resolve('translator'),
            ['my_password' => '1234'],
            $rules,
            $messages,
        );

        expect($v->passes())->toBeFalse();

        expect($v->messages()->toArray())->toBe(['my_password' => array_values($messages)]);
    });

test('passes with custom rules', function () {
        $closureRule = function ($attribute, $value, $fail) {
            if ($value !== 'aa') {
                $fail('Custom rule closure failed');
            }
        };

        $ruleObject = new class implements RuleContract
        {
            public function passes($attribute, $value)
            {
                return $value === 'aa';
            }

            public function message()
            {
                return 'Custom rule object failed';
            }
        };

        passwordPasses(Password::min(2)->rules($closureRule), ['aa']);
        passwordPasses(Password::min(2)->rules([$closureRule]), ['aa']);
        passwordPasses(Password::min(2)->rules($ruleObject), ['aa']);
        passwordPasses(Password::min(2)->rules([$closureRule, $ruleObject]), ['aa']);

        passwordFails(Password::min(2)->rules($closureRule), ['ab'], [
            'Custom rule closure failed',
        ]);

        passwordFails(Password::min(2)->rules($ruleObject), ['ab'], [
            'Custom rule object failed',
        ]);
    });

test('can retrieve all rules applied', function () {
        $password = Password::min(2)
            ->max(4)
            ->mixedCase()
            ->numbers()
            ->letters()
            ->symbols();

        expect([
            'min' => 2,
            'max' => 4,
            'mixedCase' => true,
            'letters' => true,
            'numbers' => true,
            'symbols' => true,
            'uncompromised' => false,
            'compromisedThreshold' => 0,
            'customRules' => [],
        ])->toBe($password->appliedRules());

        $password = Password::min(2);

        expect([
            'min' => 2,
            'max' => null,
            'mixedCase' => false,
            'letters' => false,
            'numbers' => false,
            'symbols' => false,
            'uncompromised' => false,
            'compromisedThreshold' => 0,
            'customRules' => [],
        ])->toBe($password->appliedRules());
    });

test('required', function () {
        passwordFails(Password::required(), [null], [
            'validation.required',
        ]);

        passwordPasses(Password::required(), ['12345678', 'password123']);

        passwordFails([Password::required()], ['short'], [
            'validation.min.string',
        ]);

        passwordPasses(Password::required()->mixedCase()->numbers(), ['Password1']);

        // Ensure it still correct when using array
        passwordPasses([Password::required()], ['12345678', 'password123']);

        passwordFails([Password::required()], ['short'], [
            'validation.min.string',
        ]);

        passwordPasses(['string', Password::required()], ['12345678', 'password123']);

        passwordPasses([Password::required()->mixedCase()->numbers()], ['Password1']);

        // Test with custom defaults
        Password::defaults(Password::min(6)->letters());

        passwordFails(Password::required(), [null], [
            'validation.required',
        ]);

        passwordPasses(Password::required(), ['Password123', 'password123']);
        passwordPasses([Password::required()], ['Password123', 'password123']);
    });

test('sometimes', function () {
        passwordFails(Password::sometimes(), ['short'], [
            'validation.min.string',
        ]);

        passwordPasses(Password::sometimes(), ['12345678', 'password123']);

        passwordFails([Password::sometimes()], ['12345'], [
            'validation.min.string',
        ]);

        passwordPasses(Password::sometimes()->mixedCase()->numbers(), ['Password1']);

        // Ensure it still correct when using array
        passwordPasses([Password::sometimes()], ['12345678', 'password123']);

        passwordFails([Password::sometimes()], ['12345'], [
            'validation.min.string',
        ]);

        passwordPasses(['string', Password::sometimes()], ['12345678', 'password123']);

        passwordPasses([Password::sometimes()->mixedCase()->numbers()], ['Password1']);

        // Test with custom defaults
        Password::defaults(Password::min(6)->letters());

        passwordPasses(Password::sometimes(), ['Password123', 'password123']);
        passwordPasses([Password::sometimes()], ['Password123', 'password123']);
    });

test('required with missing value', function () {
        $v = new Validator(
            resolve('translator'),
            [],
            ['password' => [Password::required()]]
        );

        expect($v->passes())->toBeFalse();
        expect($v->messages()->toArray())->toHaveKey('password');
        expect($v->messages()->first('password'))->toContain('required');

        $v = \Voyager\NutsAndBolts\MagicAliases\Validator::make(
            [],
            [
                'password' => [\Voyager\Validation\Rules\Password::required()],
            ]
        );

        expect($v->passes())->toBeFalse();
    });

test('nullable with empty string', function () {
        $v = new Validator(
            resolve('translator'),
            ['password' => ''],
            ['password' => ['nullable', Password::min(8)->letters()->numbers()]]
        );

        expect($v->passes())->toBeTrue();

        $v = new Validator(
            resolve('translator'),
            ['password' => null],
            ['password' => ['nullable', Password::min(8)->letters()->numbers()]]
        );

        expect($v->passes())->toBeTrue();

        $v = new Validator(
            resolve('translator'),
            ['password' => ''],
            ['password' => ['nullable', Password::sometimes()->min(8)->letters()->numbers()]]
        );

        expect($v->passes())->toBeTrue();
    });

test('it can returns as unpacked array', function () {
        expect([...Password::required()])->toBe(['required', 'string', 'min:8']);
        expect([...Password::sometimes()])->toBe(['sometimes', 'string', 'min:8']);
    });

