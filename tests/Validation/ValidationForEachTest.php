<?php

use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Validator;

function foreachTranslator()
{
    return new Translator(new ArrayLoader, 'en');
}

test('for each callbacks can properly segment rules', function () {
        $data = [
            'items' => [
                // Contains duplicate ID.
                ['discounts' => [['id' => 1], ['id' => 1], ['id' => 2]]],
                ['discounts' => [['id' => 1], ['id' => 2]]],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return ['discounts.*.id' => 'distinct'];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.discounts.0.id' => ['validation.distinct'],
            'items.0.discounts.1.id' => ['validation.distinct'],
        ]);
    });

test('for each callbacks can be recursively nested', function () {
        $data = [
            'items' => [
                // Contains duplicate ID.
                ['discounts' => [['id' => 1], ['id' => 1], ['id' => 2]]],
                ['discounts' => [['id' => 1], ['id' => 2]]],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return [
                    'discounts.*.id' => Rule::forEach(function () {
                        return 'distinct';
                    }),
                ];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.discounts.0.id' => ['validation.distinct'],
            'items.0.discounts.1.id' => ['validation.distinct'],
        ]);
    });

test('for each callbacks can return multiple validation rules', function () {
        $data = [
            'items' => [
                [
                    'discounts' => [
                        ['id' => 1, 'percent' => 30, 'discount' => 1400],
                        ['id' => 1, 'percent' => -1, 'discount' => 12300],
                        ['id' => 2, 'percent' => 120, 'discount' => 1200],
                    ],
                ],
                [
                    'discounts' => [
                        ['id' => 1, 'percent' => 30, 'discount' => 'invalid'],
                        ['id' => 2, 'percent' => 'invalid', 'discount' => 1250],
                        ['id' => 3, 'percent' => 'invalid', 'discount' => 'invalid'],
                    ],
                ],
            ],
        ];
        $rules = [
            'items.*' => Rule::forEach(function () {
                return [
                    'discounts.*.id' => 'distinct',
                    'discounts.*' => Rule::forEach(function () {
                        return [
                            'id' => 'distinct',
                            'percent' => 'numeric|min:0|max:100',
                            'discount' => 'numeric',
                        ];
                    }),
                ];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.discounts.0.id' => ['validation.distinct'],
            'items.0.discounts.1.id' => ['validation.distinct'],
            'items.0.discounts.1.percent' => ['validation.min.numeric'],
            'items.0.discounts.2.percent' => ['validation.max.numeric'],
            'items.1.discounts.0.discount' => ['validation.numeric'],
            'items.1.discounts.1.percent' => ['validation.numeric'],
            'items.1.discounts.2.percent' => ['validation.numeric'],
            'items.1.discounts.2.discount' => ['validation.numeric'],
        ]);
    });

test('for each callbacks can return arrays of validation rules', function () {
        $data = [
            'items' => [
                // Contains duplicate ID.
                ['discounts' => [['id' => 1], ['id' => 1], ['id' => 2]]],
                ['discounts' => [['id' => 1], ['id' => 'invalid']]],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return ['discounts.*.id' => ['distinct', 'numeric']];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.discounts.0.id' => ['validation.distinct'],
            'items.0.discounts.1.id' => ['validation.distinct'],
            'items.1.discounts.1.id' => ['validation.numeric'],
        ]);
    });

test('for each callbacks can return different rules', function () {
        $data = [
            'items' => [
                [
                    'discounts' => [
                        ['id' => 1, 'type' => 'percent', 'discount' => 120],
                        ['id' => 1, 'type' => 'absolute', 'discount' => 100],
                        ['id' => 2, 'type' => 'percent', 'discount' => 50],
                    ],
                ],
                [
                    'discounts' => [
                        ['id' => 2, 'type' => 'percent', 'discount' => 'invalid'],
                        ['id' => 3, 'type' => 'absolute', 'discount' => 2000],
                    ],
                ],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return [
                    'discounts.*.id' => 'distinct',
                    'discounts.*.type' => 'in:percent,absolute',
                    'discounts.*' => Rule::forEach(function ($value) {
                        return $value['type'] === 'percent'
                            ? ['discount' => 'numeric|min:0|max:100']
                            : ['discount' => 'numeric'];
                    }),
                ];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.discounts.0.id' => ['validation.distinct'],
            'items.0.discounts.1.id' => ['validation.distinct'],
            'items.0.discounts.0.discount' => ['validation.max.numeric'],
            'items.1.discounts.0.discount' => ['validation.numeric'],
        ]);
    });

test('for each callbacks do not break regex rules', function () {
        $data = [
            'items' => [
                ['users' => [['type' => 'super'], ['type' => 'invalid']]],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return ['users.*.type' => 'regex:/^(super)$/i'];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.users.1.type' => ['validation.regex'],
        ]);
    });

test('for each callbacks can contain multiple regex rules', function () {
        $data = [
            'items' => [
                ['users' => [['type' => 'super'], ['type' => 'invalid']]],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return ['users.*.type' => [
                    'regex:/^(super)$/i',
                    'notregex:/^(invalid)$/i',
                ]];
            }),
        ];

        $trans = foreachTranslator();

        $v = new Validator($trans, $data, $rules);

        expect($v->passes())->toBeFalse();

        expect($v->getMessageBag()->toArray())->toEqual([
            'items.0.users.1.type' => [
                'validation.regex',
                'validation.notregex',
            ],
        ]);
    });

test('conditional rules can be added to for each with associative array', function () {
        $v = new Validator(
            foreachTranslator(),
            [
                'foo' => [
                    ['bar' => true],
                    ['bar' => false],
                ],
            ],
            [
                'foo.*' => Rule::forEach(fn (mixed $value, string $attribute) => [
                    'bar' => Rule::when(true, ['accepted'], ['declined']),
                ]),
            ]
        );

        expect($v->getMessageBag()->toArray())->toEqual([
            'foo.1.bar' => ['validation.accepted'],
        ]);
    });

test('conditional rules can be added to for each with list', function () {
        $v = new Validator(
            foreachTranslator(),
            [
                'foo' => [
                    ['bar' => true],
                    ['bar' => false],
                ],
            ],
            [
                'foo.*.bar' => Rule::forEach(fn (mixed $value, string $attribute) => [
                    Rule::when(true, ['accepted'], ['declined']),
                ]),
            ]);

        expect($v->getMessageBag()->toArray())->toEqual([
            'foo.1.bar' => ['validation.accepted'],
        ]);
    });

test('conditional rules can be added to for each with object', function () {
        $v = new Validator(
            foreachTranslator(),
            [
                'foo' => [
                    ['bar' => true],
                    ['bar' => false],
                ],
            ],
            [
                'foo.*.bar' => Rule::forEach(fn (mixed $value, string $attribute) => Rule::when(true, ['accepted'], ['declined']),
                ),
            ]);

        expect($v->getMessageBag()->toArray())->toEqual([
            'foo.1.bar' => ['validation.accepted'],
        ]);
    });

test('for each with empty and null values', function () {
        $data = [
            'items' => [
                ['discounts' => null],
                ['discounts' => []],
                ['discounts' => [null]],
            ],
        ];

        $rules = [
            'items.*' => Rule::forEach(function () {
                return [
                    'discounts' => 'required|array',
                    'discounts.*' => 'required|array',
                ];
            }),
        ];

        $v = new Validator(foreachTranslator(), $data, $rules);
        expect($v->passes())->toBeFalse();
        expect($v->getMessageBag()->toArray())->toEqual([
                'items.0.discounts' => ['validation.required'],
                'items.1.discounts' => ['validation.required'],
                'items.2.discounts.0' => ['validation.required'],
            ]);
    });

