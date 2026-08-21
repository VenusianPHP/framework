<?php

use Tests\System\Stubs\TestCaseWithTrait;

test('the setUp and tearDown hooks of a trait are called', function () {
    $testCase = new TestCaseWithTrait('foo');

    (new ReflectionMethod($testCase, 'setUpTraits'))->invoke($testCase);

    expect($testCase->setUp)->toBeTrue();

    (new ReflectionMethod($testCase, 'callBeforeApplicationDestroyedCallbacks'))->invoke($testCase);

    expect($testCase->tearDown)->toBeTrue();
});
