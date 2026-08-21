<?php

use Tests\Validation\fixtures\TaggedUnionDiscriminatorType;
use Voyager\MagicAliases\MagicAlias;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\ValidationServiceProvider;
use Voyager\Validation\Validator;
use Voyager\Vessel\Vessel;

beforeEach(function () {
    $container = Vessel::getInstance();
    $container->bind('translator', function () {
        return new Translator(
            new ArrayLoader,
            'en'
        );
    });

    MagicAlias::setMagicAliasApplication($container);
    (new ValidationServiceProvider($container))->register();

    $this->taggedUnionRules = [
        [
            'type' => ['required', Rule::in([TaggedUnionDiscriminatorType::EMAIL])],
            'email' => ['required', 'email:rfc'],
        ],
        [
            'type' => ['required', Rule::in([TaggedUnionDiscriminatorType::URL])],
            'url' => ['required', 'url:http,https'],
        ],
    ];

    // Using AnyOf as nesting feature
    $this->nestedRules = [
        'user' => Rule::anyOf([
            [
                'identifier' => ['required', Rule::anyOf([
                    'email:rfc',
                    'integer',
                ])],
                'properties' => ['required', Rule::anyOf([
                    [
                        'bio' => 'nullable',
                        'name' => 'required',
                        'surname' => 'required',
                    ],
                ])],
            ],
        ]),
    ];

    $this->dotNotationNestedRules = [
        'user.identifier' => ['required', Rule::anyOf([
            'email:rfc',
            'integer',
        ])],
        'user.properties.bio' => 'nullable',
        'user.properties.name' => 'required',
        'user.properties.surname' => 'required',
    ];
});

afterEach(function () {
    Vessel::setInstance(null);
    MagicAlias::clearResolvedInstances();
    MagicAlias::setMagicAliasApplication(null);
});

test('basic validation', function () {
        $rule = Rule::anyOf([
            ['required', 'uuid:4'],
            ['required', 'email'],
        ]);
        $idRule = ['id' => $rule];
        $requiredIdRule = ['id' => ['required', $rule]];

        $validator = new Validator(resolve('translator'), [
            'id' => 'taylor@laravel.com',
        ], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [], $requiredIdRule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'id' => '3c8ff5cb-4bc1-457b-a477-1833c477b254',
        ], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'id' => null,
        ], $idRule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'id' => '',
        ], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'id' => '',
        ], $requiredIdRule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'id' => 'abc',
        ], $idRule);
        expect($validator->passes())->toBeFalse();
    });

test('basic string validation', function () {
        $rule = Rule::anyOf([
            'required|uuid:4',
            'required|email',
        ]);
        $idRule = ['id' => $rule];
        $requiredIdRule = ['id' => ['required', $rule]];

        $validator = new Validator(resolve('translator'), [
            'id' => 'test@example.com',
        ], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [], $requiredIdRule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'id' => '3c8ff5cb-4bc1-457b-a477-1833c477b254',
        ], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'id' => null,
        ], $idRule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'id' => '',
        ], $idRule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'id' => '',
        ], $requiredIdRule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'id' => 'abc',
        ], $idRule);
        expect($validator->passes())->toBeFalse();
    });

test('tagged union objects', function () {
        $validator = new Validator(resolve('translator'), [
            'data' => [
                'type' => TaggedUnionDiscriminatorType::EMAIL->value,
                'email' => 'taylor@laravel.com',
            ],
        ], ['data' => Rule::anyOf($this->taggedUnionRules)]);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'data' => [
                'type' => TaggedUnionDiscriminatorType::EMAIL->value,
                'email' => 'invalid-email',
            ],
        ], ['data' => Rule::anyOf($this->taggedUnionRules)]);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'data' => [
                'type' => TaggedUnionDiscriminatorType::URL->value,
                'url' => 'http://laravel.com',
            ],
        ], ['data' => Rule::anyOf($this->taggedUnionRules)]);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'data' => [
                'type' => TaggedUnionDiscriminatorType::URL->value,
                'url' => 'not-a-url',
            ],
        ], ['data' => Rule::anyOf($this->taggedUnionRules)]);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'data' => [
                'type' => TaggedUnionDiscriminatorType::EMAIL->value,
                'url' => 'url-should-not-be-present-with-email-discriminator',
            ],
        ], ['data' => Rule::anyOf($this->taggedUnionRules)]);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'data' => [
                'type' => 'doesnt-exist',
                'email' => 'taylor@laravel.com',
            ],
        ], ['data' => Rule::anyOf($this->taggedUnionRules)]);
        expect($validator->passes())->toBeFalse();
    });

test('nested validation', function () {
        $validator = new Validator(resolve('translator'), [
            'user' => [
                'identifier' => 1,
                'properties' => [
                    'name' => 'Taylor',
                    'surname' => 'Otwell',
                ],
            ],
        ], $this->nestedRules);
        expect($validator->passes())->toBeTrue();
        $validator->setRules($this->dotNotationNestedRules);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'user' => [
                'identifier' => 'taylor@laravel.com',
                'properties' => [
                    'bio' => 'biography',
                    'name' => 'Taylor',
                    'surname' => 'Otwell',
                ],
            ],
        ], $this->nestedRules);
        expect($validator->passes())->toBeTrue();
        $validator->setRules($this->dotNotationNestedRules);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'user' => [
                'identifier' => 'taylor@laravel.com',
                'properties' => [
                    'name' => null,
                    'surname' => 'Otwell',
                ],
            ],
        ], $this->nestedRules);
        expect($validator->passes())->toBeFalse();
        $validator->setRules($this->dotNotationNestedRules);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'user' => [
                'properties' => [
                    'name' => 'Taylor',
                    'surname' => 'Otwell',
                ],
            ],
        ], $this->nestedRules);
        expect($validator->passes())->toBeFalse();
        $validator->setRules($this->dotNotationNestedRules);
        expect($validator->passes())->toBeFalse();
    });

test('star rule simple', function () {
        $rule = [
            'persons.*.age' => ['required', Rule::anyOf([
                ['min:10'],
                ['integer'],
            ])],
        ];

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['age' => 12],
                ['age' => 'foobar'],
            ],
        ], $rule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['age' => 'foobarbazqux'],
                ['month' => 12],
            ],
        ], $rule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['age' => 12],
                ['age' => 'foobarbazqux'],
            ],
        ], $rule);
        expect($validator->passes())->toBeTrue();
    });

test('star rule nested', function () {
        $rule = [
            'persons.*.birth' => ['required', Rule::anyOf([
                ['year' => 'required|integer'],
                'required|min:10',
            ])],
        ];

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['age' => ['year' => 12]],
            ],
        ], $rule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['birth' => ['month' => 12]],
            ],
        ], $rule);
        expect($validator->passes())->toBeFalse();

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['birth' => ['year' => 12]],
            ],
        ], $rule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['birth' => 'foobarbazqux'],
                ['birth' => [
                    'year' => 12,
                ]],
            ],
        ], $rule);
        expect($validator->passes())->toBeTrue();

        $validator = new Validator(resolve('translator'), [
            'persons' => [
                ['birth' => 'foobar'],
                ['birth' => [
                    'year' => 12,
                ]],
            ],
        ], $rule);
        expect($validator->passes())->toBeFalse();
    });

test('custom message using dot notation and fqcn works', function () {
        $v = new Validator(
            resolve('translator'),
            [
                'string' => 123,
                'string_fqcn' => 456,
            ],
            [
                'string' => Rule::anyOf(['string']),
                'string_fqcn' => Rule::anyOf(['string']),
            ],
            [
                'string.any_of' => 'Please choose a valid string (dot notation)',
                'string_fqcn.Voyager\Validation\Rules\AnyOf' => 'Please choose a valid string (fqcn)',
            ]
        );

        expect($v->fails())->toBeTrue();

        expect($v->messages()->all())->toBe([
            'Please choose a valid string (dot notation)',
            'Please choose a valid string (fqcn)',
        ]);
    });

