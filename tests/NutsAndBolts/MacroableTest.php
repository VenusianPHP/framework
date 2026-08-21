<?php

use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Str;

afterEach(function () {
    Collection::flushMacros();
    Str::flushMacros();
});

test('registers and invokes an instance macro', function () {
    Collection::macro('double', fn () => $this->map(fn (int $v) => $v * 2));

    expect(collect([1, 2])->double()->all())->toBe([2, 4])
        ->and(Collection::hasMacro('double'))->toBeTrue();
});

test('registers and invokes a static macro', function () {
    Str::macro('appName', fn () => 'Venusian');

    expect(Str::appName())->toBe('Venusian')
        ->and(Str::hasMacro('appName'))->toBeTrue();
});

test('flush removes registered macros', function () {
    Str::macro('temporary', fn () => true);

    Str::flushMacros();

    expect(Str::hasMacro('temporary'))->toBeFalse();
});

test('mixin registers every method of an object', function () {
    Str::mixin(new class
    {
        public function shout(): Closure
        {
            return fn (string $value) => strtoupper($value).'!';
        }
    });

    expect(Str::hasMacro('shout'))->toBeTrue()
        ->and(Str::shout('hey'))->toBe('HEY!');
});

test('an unknown method still throws', function () {
    Str::definitelyNotAMacro();
})->throws(BadMethodCallException::class);
