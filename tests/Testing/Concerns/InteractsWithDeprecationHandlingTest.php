<?php

use Voyager\System\Bootstrap\HandleExceptions;
use Voyager\System\Testing\Concerns\InteractsWithDeprecationHandling;

uses(InteractsWithDeprecationHandling::class);

beforeEach(function () {
    $this->deprecationsFound = false;

    set_error_handler(function () {
        $this->deprecationsFound = true;
    });
});

afterEach(function () {
    $this->deprecationsFound = false;

    HandleExceptions::flushHandlersState($this);
});

test('with deprecation handling', function () {
    $this->withDeprecationHandling();

    trigger_error('Something is deprecated', E_USER_DEPRECATED);

    expect($this->deprecationsFound)->toBeTrue();
});

test('without deprecation handling', function () {
    $this->withoutDeprecationHandling();

    trigger_error('Something is deprecated', E_USER_DEPRECATED);
})->throws(ErrorException::class, 'Something is deprecated');
