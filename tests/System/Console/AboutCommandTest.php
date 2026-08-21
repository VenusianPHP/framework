<?php

use Voyager\System\Console\AboutCommand;

test('a formatter can render for the cli interface', function ($format, $expected) {
    expect(value($format, false))->toBe($expected);
})->with([
    'true' => [AboutCommand::format(true, console: fn ($value) => $value === true ? 'YES' : 'NO'), 'YES'],
    'false' => [AboutCommand::format(false, console: fn ($value) => $value === true ? 'YES' : 'NO'), 'NO'],
]);

test('a formatter can render for the json interface', function ($format, $expected) {
    expect(value($format, true))->toBe($expected);
})->with([
    'true' => [AboutCommand::format(true, json: fn ($value) => $value === true ? 'YES' : 'NO'), 'YES'],
    'false' => [AboutCommand::format(false, json: fn ($value) => $value === true ? 'YES' : 'NO'), 'NO'],
]);
