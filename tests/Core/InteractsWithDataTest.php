<?php

use Carbon\CarbonInterval;
use Voyager\NutsAndBolts\Fluent;

enum DataSuit: string
{
    case Hearts = 'hearts';
    case Spades = 'spades';
}

it('applies the callback to a valid enum value, else the default, else returns itself', function () {
    $data = new Fluent(['suit' => 'hearts', 'bad' => 'clubs']);

    expect($data->whenEnum('suit', DataSuit::class, fn (DataSuit $suit) => $suit))->toBe(DataSuit::Hearts)
        ->and($data->whenEnum('bad', DataSuit::class, fn () => 'called', fn () => 'default'))->toBe('default')
        ->and($data->whenEnum('missing', DataSuit::class, fn () => 'called'))->toBe($data)
        ->and($data->whenEnum('suit', stdClass::class, fn () => 'called'))->toBe($data);
});

it('reads an interval written in scientific notation', function () {
    $interval = (new Fluent(['wait' => '5.3E-5']))->interval('wait', 'second');

    expect($interval)->toBeInstanceOf(CarbonInterval::class)
        ->and($interval->totalMicroseconds)->toEqualWithDelta(53, 0.5);
});

it('clamps numeric data and falls back to the default for anything else', function () {
    $data = new Fluent(['high' => '20', 'low' => -4, 'word' => 'abc']);

    expect($data->clamp('high', 1, 10))->toBe(10)
        ->and($data->clamp('low', 1, 10))->toBe(1)
        ->and($data->clamp('word', 1, 10, 5))->toBe(5)
        ->and($data->clamp('missing', 1, 10, 7))->toBe(7);
});
