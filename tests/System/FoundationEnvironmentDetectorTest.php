<?php

use Voyager\System\EnvironmentDetector;

test('a closure detects the environment when the console arguments do not', function () {
    $result = (new EnvironmentDetector)->detect(fn () => 'foobar');

    expect($result)->toBe('foobar');
});

test('the console arguments are consulted before the closure', function ($arguments, $expected) {
    $result = (new EnvironmentDetector)->detect(fn () => 'foobar', $arguments);

    expect($result)->toBe($expected);
})->with([
    '--env=local' => [['--env=local'], 'local'],
    '--env local' => [['--env', 'local'], 'local'],
    '--env with no value' => [['--env'], 'foobar'],
    '--envelope=mail is not --env' => [['--envelope=mail'], 'foobar'],
    '--envelope mail is not --env' => [['--envelope', 'mail'], 'foobar'],
    '--envelope with no value is not --env' => [['--envelope'], 'foobar'],
]);
