<?php

use Voyager\NutsAndBolts\DataObjects\Number;

test('formats numbers for the default locale', function () {
    expect(Number::format(1234.5))->toBe('1,234.5')
        ->and(Number::format(1234.5678, precision: 2))->toBe('1,234.57');
});

test('formats currency', function () {
    expect(Number::currency(1234.5))->toBe('$1,234.50')
        ->and(Number::currency(1234.5, 'EUR', 'de'))->toContain('1.234,50');
});

test('formats percentages', function () {
    expect(Number::percentage(10))->toBe('10%');
});

test('formats file sizes', function (int|float $bytes, string $expected) {
    expect(Number::fileSize($bytes))->toBe($expected);
})->with([
    'bytes'     => [512, '512 B'],
    'kilobytes' => [1024, '1 KB'],
    'megabytes' => [1024 * 1024, '1 MB'],
]);

test('formats ordinals', function () {
    expect(Number::ordinal(1))->toBe('1st')
        ->and(Number::ordinal(2))->toBe('2nd')
        ->and(Number::ordinal(11))->toBe('11th');
});

test('clamps to a range', function () {
    expect(Number::clamp(15, 1, 10))->toBe(10)
        ->and(Number::clamp(0, 1, 10))->toBe(1)
        ->and(Number::clamp(5, 1, 10))->toBe(5);
});

test('parses a formatted string back to a number', function () {
    expect(Number::parse('1,234.5'))->toBe(1234.5);
});
