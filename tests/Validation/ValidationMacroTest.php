<?php

use Voyager\Validation\Rule;

test('macroable', function () {
    // Define a phone validation macro
    Rule::macro('phone', function () {
        return 'regex:/^([0-9\s\-\+\(\)]*)$/';
    });

    $actualRule = Rule::phone();

    expect($actualRule)->toBe('regex:/^([0-9\s\-\+\(\)]*)$/');
});

test('macro arguments', function () {
    Rule::macro('maxLength', function (int $length) {
        return "max:{$length}";
    });

    $actualRule = Rule::maxLength(10);

    expect($actualRule)->toBe('max:10');
});

test('macro default arguments', function () {
    Rule::macro('maxLength', function ($length = 255) {
        return "max:{$length}";
    });

    $actualRule = Rule::maxLength();  // No argument provided, should use default value

    expect($actualRule)->toBe('max:255');
});
