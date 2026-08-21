<?php

use Voyager\NutsAndBolts\Fluent;
use Voyager\Validation\Rule;
use Voyager\Validation\ValidationRuleParser;

test('conditional rules are properly expanded and filtered', function () {
        $isAdmin = true;

        $rules = ValidationRuleParser::filterConditionalRules([
            'name' => Rule::when($isAdmin, ['required', 'min:2']),
            'email' => Rule::unless($isAdmin, ['required', 'min:2']),
            'password' => Rule::when($isAdmin, 'required|min:2'),
            'username' => ['required', Rule::when($isAdmin, ['min:2'])],
            'address' => ['required', Rule::unless($isAdmin, ['min:2'])],
            'city' => ['required', Rule::when(function (Fluent $input) {
                return true;
            }, ['min:2'])],
            'state' => ['required', Rule::when($isAdmin, function (Fluent $input) {
                return 'min:2';
            })],
            'zip' => ['required', Rule::when($isAdmin, function (Fluent $input) {
                return ['min:2'];
            })],
            'when_cb_true' => Rule::when(fn () => true, ['required'], ['nullable']),
            'when_cb_false' => Rule::when(fn () => false, ['required'], ['nullable']),
            'unless_cb_true' => Rule::unless(fn () => true, ['required'], ['nullable']),
            'unless_cb_false' => Rule::unless(fn () => false, ['required'], ['nullable']),
        ]);

        expect($rules)->toEqual([
            'name' => ['required', 'min:2'],
            'email' => [],
            'password' => ['required', 'min:2'],
            'username' => ['required', 'min:2'],
            'address' => ['required'],
            'city' => ['required', 'min:2'],
            'state' => ['required', 'min:2'],
            'zip' => ['required', 'min:2'],
            'when_cb_true' => ['required'],
            'when_cb_false' => ['nullable'],
            'unless_cb_true' => ['nullable'],
            'unless_cb_false' => ['required'],
        ]);
    });

test('empty rules are preserved', function () {
        $isAdmin = true;

        $rules = ValidationRuleParser::filterConditionalRules([
            'name' => [],
            'email' => '',
            'password' => Rule::when($isAdmin, 'required|min:2'),
            'gender' => Rule::unless($isAdmin, 'required'),
        ]);

        expect($rules)->toEqual([
            'name' => [],
            'email' => '',
            'password' => ['required', 'min:2'],
            'gender' => [],
        ]);
    });

test('empty rules can be exploded', function () {
        $parser = new ValidationRuleParser(['foo' => 'bar']);

        expect($parser->explode(['foo' => []]))->toBeObject();
    });

test('conditional rules with default', function () {
        $isAdmin = true;

        $rules = ValidationRuleParser::filterConditionalRules([
            'name' => Rule::when($isAdmin, ['required', 'min:2'], ['string', 'max:10']),
            'email' => Rule::unless($isAdmin, ['required', 'min:2'], ['string', 'max:10']),
            'password' => Rule::unless($isAdmin, 'required|min:2', 'string|max:10'),
            'username' => ['required', Rule::when($isAdmin, ['min:2'], ['string', 'max:10'])],
            'address' => ['required', Rule::unless($isAdmin, ['min:2'], ['string', 'max:10'])],
        ]);

        expect($rules)->toEqual([
            'name' => ['required', 'min:2'],
            'email' => ['string', 'max:10'],
            'password' => ['string', 'max:10'],
            'username' => ['required', 'min:2'],
            'address' => ['required', 'string', 'max:10'],
        ]);
    });

test('empty conditional rules are preserved', function () {
        $isAdmin = true;

        $rules = ValidationRuleParser::filterConditionalRules([
            'name' => Rule::when($isAdmin, '', ['string', 'max:10']),
            'email' => Rule::unless($isAdmin, ['required', 'min:2']),
            'password' => Rule::unless($isAdmin, 'required|min:2', 'string|max:10'),
        ]);

        expect($rules)->toEqual([
            'name' => [],
            'email' => [],
            'password' => ['string', 'max:10'],
        ]);
    });

test('explode fails parsing single regex rule containing pipe', function () {
        $data = ['items' => [['type' => 'foo']]];

        $exploded = (new ValidationRuleParser($data))->explode(
            ['items.*.type' => 'regex:/^(foo|bar)$/i']
        );

        expect($exploded->rules['items.0.type'][0])->toBe('regex:/^(foo');
        expect($exploded->rules['items.0.type'][1])->toBe('bar)$/i');
    });

test('explode properly parses single regex rule not containing pipe', function () {
        $data = ['items' => [['type' => 'foo']]];

        $exploded = (new ValidationRuleParser($data))->explode(
            ['items.*.type' => 'regex:/^[\d\-]*$/|max:20']
        );

        expect($exploded->rules['items.0.type'][0])->toBe('regex:/^[\d\-]*$/');
        expect($exploded->rules['items.0.type'][1])->toBe('max:20');
    });

test('explode properly parses regex with array of rules', function () {
        $data = ['items' => [['type' => 'foo']]];

        $exploded = (new ValidationRuleParser($data))->explode(
            ['items.*.type' => ['in:foo', 'regex:/^(foo|bar)$/i']]
        );

        expect($exploded->rules['items.0.type'][0])->toBe('in:foo');
        expect($exploded->rules['items.0.type'][1])->toBe('regex:/^(foo|bar)$/i');
    });

test('explode properly parses regex that does not contain pipe', function () {
        $data = ['items' => [['type' => 'foo']]];

        $exploded = (new ValidationRuleParser($data))->explode(
            ['items.*.type' => 'in:foo|regex:/^(bar)$/i']
        );

        expect($exploded->rules['items.0.type'][0])->toBe('in:foo');
        expect($exploded->rules['items.0.type'][1])->toBe('regex:/^(bar)$/i');
    });

test('explode fails parsing regex with other rules in single string', function () {
        $data = ['items' => [['type' => 'foo']]];

        $exploded = (new ValidationRuleParser($data))->explode(
            ['items.*.type' => 'in:foo|regex:/^(foo|bar)$/i']
        );

        expect($exploded->rules['items.0.type'][0])->toBe('in:foo');
        expect($exploded->rules['items.0.type'][1])->toBe('regex:/^(foo');
        expect($exploded->rules['items.0.type'][2])->toBe('bar)$/i');
    });

test('explode generates nested rules', function () {
        $parser = (new ValidationRuleParser([
            'users' => [
                ['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com'],
            ],
        ]));

        $results = $parser->explode([
            'users.*.name' => Rule::forEach(function ($value, $attribute, $data, $context) {
                expect($value)->toBe('Taylor Otwell');
                expect($attribute)->toBe('users.0.name');
                expect('Taylor Otwell')->toEqual($data['users.0.name']);
                expect($context)->toEqual(['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']);

                return [Rule::requiredIf(true)];
            }),
        ]);

        expect($results->rules)->toEqual(['users.0.name' => ['required']]);
        expect($results->implicitAttributes)->toEqual(['users.*.name' => ['users.0.name']]);
    });

test('explode generates nested rules for non nested data', function () {
        $parser = (new ValidationRuleParser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
        ]));

        $results = $parser->explode([
            'name' => Rule::forEach(function ($value, $attribute, $data, $context) {
                expect($value)->toBe('Taylor Otwell');
                expect($attribute)->toBe('name');
                expect($data)->toEqual(['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']);
                expect($context)->toEqual(['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']);

                return 'required';
            }),
        ]);

        expect($results->rules)->toEqual(['name' => ['required']]);
        expect($results->implicitAttributes)->toEqual([]);
    });

test('explode handles forward slashes in wildcard rule', function () {
        $parser = (new ValidationRuleParser([
            'redirects' => [
                'directory/subdirectory/file' => [
                    'directory/subdirectory/redirectedfile',
                ],
            ],
        ]));

        $results = $parser->explode([
            'redirects.directory/subdirectory/file.*' => 'string',
        ]);

        expect($results->rules)->toEqual([
            'redirects.directory/subdirectory/file.0' => ['string'],
        ]);
        expect($results->implicitAttributes)->toEqual([
            'redirects.directory/subdirectory/file.*' => ['redirects.directory/subdirectory/file.0'],
        ]);
    });

test('explode handles arrays of nested rules', function () {
        $parser = (new ValidationRuleParser([
            'users' => [
                ['name' => 'Taylor Otwell'],
                ['name' => 'Abigail Otwell'],
            ],
        ]));

        $results = $parser->explode([
            'users.*.name' => Rule::forEach(function ($value, $attribute, $data) {
                expect($data)->toEqual([
                    'users.0.name' => 'Taylor Otwell',
                    'users.1.name' => 'Abigail Otwell',
                ]);

                return [
                    Rule::requiredIf(true),
                    $value === 'Taylor Otwell'
                        ? Rule::in('taylor')
                        : Rule::in('abigail'),
                ];
            }),
        ]);

        expect($results->rules)->toEqual([
            'users.0.name' => ['required', 'in:"taylor"'],
            'users.1.name' => ['required', 'in:"abigail"'],
        ]);

        expect($results->implicitAttributes)->toEqual([
            'users.*.name' => [
                'users.0.name',
                'users.1.name',
            ],
        ]);
    });

test('explode handles recursively nested rules', function () {
        $parser = (new ValidationRuleParser([
            'users' => [['name' => 'Taylor Otwell']],
        ]));

        $results = $parser->explode([
            'users.*.name' => Rule::forEach(function ($value, $attribute, $data) {
                expect($value)->toBe('Taylor Otwell');
                expect($attribute)->toBe('users.0.name');
                expect($data)->toEqual(['users.0.name' => 'Taylor Otwell']);

                return Rule::forEach(function ($value, $attribute, $data) {
                    expect($value)->toBeNull();
                    expect($attribute)->toBe('users.0.name');
                    expect($data)->toEqual(['users.0.name' => 'Taylor Otwell']);

                    return Rule::forEach(function ($value, $attribute, $data) {
                        expect($value)->toBeNull();
                        expect($attribute)->toBe('users.0.name');
                        expect($data)->toEqual(['users.0.name' => 'Taylor Otwell']);

                        return [Rule::requiredIf(true)];
                    });
                });
            }),
        ]);

        expect($results->rules)->toEqual(['users.0.name' => ['required']]);
        expect($results->implicitAttributes)->toEqual(['users.*.name' => ['users.0.name']]);
    });

test('explode handles segmenting nested rules', function () {
        $parser = (new ValidationRuleParser([
            'items' => [
                ['discounts' => [['id' => 1], ['id' => 2]]],
                ['discounts' => [['id' => 1], ['id' => 2]]],
            ],
        ]));

        $rules = [
            'items.*' => Rule::forEach(function () {
                return ['discounts.*.id' => 'distinct'];
            }),
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'items.0.discounts.0.id' => ['distinct'],
            'items.0.discounts.1.id' => ['distinct'],
            'items.1.discounts.0.id' => ['distinct'],
            'items.1.discounts.1.id' => ['distinct'],
        ]);

        expect($results->implicitAttributes)->toEqual([
            'items.1.discounts.*.id' => [
                'items.1.discounts.0.id',
                'items.1.discounts.1.id',
            ],
            'items.0.discounts.*.id' => [
                'items.0.discounts.0.id',
                'items.0.discounts.1.id',
            ],
            'items.*' => [
                'items.0',
                'items.1',
            ],
        ]);
    });

test('explode handles string date rule', function () {
        $parser = (new ValidationRuleParser([
            'date' => '2021-01-01',
        ]));

        $rules = [
            'date' => 'date|date_format:Y-m-d',
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'date' => [
                'date',
                'date_format:Y-m-d',
            ],
        ]);
    });

test('explode handles date rule', function () {
        $parser = (new ValidationRuleParser([
            'date' => '2021-01-01',
        ]));

        $rules = [
            'date' => Rule::date(),
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'date' => [
                'date',
            ],
        ]);
    });

test('explode handles date rule with additional rules', function () {
        $parser = (new ValidationRuleParser([
            'date' => '2021-01-01',
        ]));

        $rules = [
            'date' => Rule::date()->after('today'),
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'date' => [
                'date',
                'after:today',
            ],
        ]);
    });

test('explode handles numeric string rule', function () {
        $parser = (new ValidationRuleParser([
            'number' => 42,
        ]));

        $rules = [
            'number' => 'numeric|max:100',
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'number' => [
                'numeric',
                'max:100',
            ],
        ]);
    });

test('explode handles numeric rule', function () {
        $parser = (new ValidationRuleParser([
            'number' => 42,
        ]));

        $rules = [
            'number' => Rule::numeric(),
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'number' => [
                'numeric',
            ],
        ]);
    });

test('explode handles numeric rule with additional rules', function () {
        $parser = (new ValidationRuleParser([
            'number' => 42,
        ]));

        $rules = [
            'number' => Rule::numeric()->max(100),
        ];

        $results = $parser->explode($rules);

        expect($results->rules)->toEqual([
            'number' => [
                'numeric',
                'max:100',
            ],
        ]);
    });

