<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The framework has no application container yet, so these are plain unit
| tests against the Support foundation. Bind a base TestCase here once the
| System layer exists.
|
*/

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/**
 * Assert that a callable both returns the expected value for a plain array of
 * needles and for the same needles wrapped in a Collection.
 *
 * Guards the port hazard where an `array|string` hint silently coerced a
 * Collection to its JSON form instead of iterating it.
 */
expect()->extend('toAcceptIterables', function (callable $call, mixed $expected) {
    expect($call($this->value))->toBe($expected)
        ->and($call(collect($this->value)))->toBe($expected);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Datasets
|--------------------------------------------------------------------------
*/

/**
 * Upstream's SupportCollectionTest runs almost every case twice: once over
 * the eager Collection, once over the lazy LazyCollection. This dataset
 * preserves that — every ported test that is valid for both classes is
 * declared with ->with('collections') so it still runs twice.
 */
dataset('collections', [
    'Collection' => [\Voyager\NutsAndBolts\Collection::class],
    'LazyCollection' => [\Voyager\NutsAndBolts\LazyCollection::class],
]);

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

afterEach(function () {
    \Voyager\NutsAndBolts\Sleep::fake(false);
    \Voyager\NutsAndBolts\DataObjects\Str::createUuidsNormally();
});

/**
 * An object implementing PHP's native \Stringable and nothing else.
 *
 * Distinct from Voyager's Stringable, which is a much richer class. Several
 * Collection paths must treat any native \Stringable alike.
 */
function nativeStringable(string $value): \Stringable
{
    return new class($value) implements \Stringable
    {
        public function __construct(private string $value) {}

        public function __toString(): string
        {
            return $this->value;
        }
    };
}
