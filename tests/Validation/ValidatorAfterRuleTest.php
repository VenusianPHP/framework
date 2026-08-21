<?php

use Tests\Validation\fixtures\AfterMethodRule;
use Tests\Validation\fixtures\InvokableAfterRule;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Validator;

test('after accepts array of rules', function () {
    $validator = new Validator(new Translator(new ArrayLoader, 'en'), [], []);

    $validator->after([
        fn ($validator) => $validator->errors()->add('closure', 'true'),
        new InvokableAfterRule,
        new AfterMethodRule,
    ])->messages()->messages();

    expect($validator->messages()->messages())->toBe([
        'closure' => ['true'],
        'invokableAfterRule' => ['true'],
        'afterMethodRule' => ['true'],
    ]);
});
