<?php

use Voyager\NutsAndBolts\Lottery;

afterEach(function () {
    Lottery::determineResultNormally();
});

test('it can win', function () {
    $wins = false;

    Lottery::odds(1, 1)
        ->winner(function () use (&$wins) {
            $wins = true;
        })->choose();

    expect($wins)->toBeTrue();
});

test('it can lose', function () {
    $wins = false;
    $loses = false;

    Lottery::odds(0, 1)
        ->winner(function () use (&$wins) {
            $wins = true;
        })->loser(function () use (&$loses) {
            $loses = true;
        })->choose();

    expect($wins)->toBeFalse()
        ->and($loses)->toBeTrue();
});

test('the chosen callback returns its value', function () {
    expect(Lottery::odds(1, 1)->winner(fn () => 'win')->choose())->toBe('win')
        ->and(Lottery::odds(0, 1)->loser(fn () => 'lose')->choose())->toBe('lose');
});

test('choose may run several times', function () {
    expect(Lottery::odds(1, 1)->winner(fn () => 'win')->choose(2))->toBe(['win', 'win'])
        ->and(Lottery::odds(0, 1)->loser(fn () => 'lose')->choose(2))->toBe(['lose', 'lose']);
});

test('a lottery can be passed as a callable', function () {
    // Example...
    // DB::whenQueryingForLongerThan(Interval::seconds(5), Lottery::odds(1, 5)->winner(function ($connection) {
    //     Alert the team
    // }));
    $result = (fn (callable $callable) => $callable('winner-chicken', '-dinner'))(
        Lottery::odds(1, 1)->winner(fn ($first, $second) => 'winner-'.$first.$second)
    );

    expect($result)->toBe('winner-winner-chicken-dinner');
});

test('booleans are returned when no closures are given', function () {
    expect(Lottery::odds(1, 1)->choose())->toBeTrue()
        ->and(Lottery::odds(0, 1)->choose())->toBeFalse();
});

test('alwaysWin forces a winning result', function () {
    $result = null;

    Lottery::alwaysWin(function () use (&$result) {
        $result = Lottery::odds(1, 2)->winner(fn () => 'winner')->choose(10);
    });

    expect($result)->toBe([
        'winner', 'winner', 'winner', 'winner', 'winner',
        'winner', 'winner', 'winner', 'winner', 'winner',
    ]);
});

test('alwaysLose forces a losing result', function () {
    $result = null;

    Lottery::alwaysLose(function () use (&$result) {
        $result = Lottery::odds(1, 2)->loser(fn () => 'loser')->choose(10);
    });

    expect($result)->toBe([
        'loser', 'loser', 'loser', 'loser', 'loser',
        'loser', 'loser', 'loser', 'loser', 'loser',
    ]);
});

test('the result may be forced with a sequence', function () {
    Lottery::forceResultWithSequence([
        true, false, true, false, true,
        false, true, false, true, false,
    ]);

    $result = Lottery::odds(1, 100)->winner(fn () => 'winner')->loser(fn () => 'loser')->choose(10);

    expect($result)->toBe([
        'winner', 'loser', 'winner', 'loser', 'winner',
        'loser', 'winner', 'loser', 'winner', 'loser',
    ]);
});

test('a missing sequence item falls through to the given callback', function () {
    Lottery::forceResultWithSequence([
        0 => true,
        1 => true,
        // 2 => ...
        3 => true,
    ], fn () => throw new RuntimeException('Missing key in sequence.'));

    $draw = fn () => Lottery::odds(1, 10000)->winner(fn () => 'winner')->loser(fn () => 'loser')->choose();

    expect($draw())->toBe('winner')
        ->and($draw())->toBe('winner')
        ->and($draw)->toThrow(RuntimeException::class, 'Missing key in sequence.');
});

test('a float over one is rejected', function () {
    new Lottery(1.1);
})->throws(RuntimeException::class, 'Float must not be greater than 1.');

test('an out-of value under one is rejected', function () {
    new Lottery(1, 0);
})->throws(RuntimeException::class);

test('it can win with a float', function () {
    $wins = false;

    Lottery::odds(1.0)
        ->winner(function () use (&$wins) {
            $wins = true;
        })->choose();

    expect($wins)->toBeTrue();
});

test('it can lose with a float', function () {
    $wins = false;
    $loses = false;

    Lottery::odds(0.0)
        ->winner(function () use (&$wins) {
            $wins = true;
        })->loser(function () use (&$loses) {
            $loses = true;
        })->choose();

    expect($wins)->toBeFalse()
        ->and($loses)->toBeTrue();
});
