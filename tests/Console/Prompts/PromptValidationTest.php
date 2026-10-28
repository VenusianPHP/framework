<?php

use Voyager\Console\Concerns\ConfiguresPrompts;

function promptValidator(): object
{
    return new class
    {
        use ConfiguresPrompts;

        public function check(mixed $value, mixed $rules): ?string
        {
            return $this->validatePrompt($value, $rules);
        }
    };
}

it('validates a prompt with a closure validator', function () {
    $closure = fn ($value) => $value === '' ? 'Required.' : null;

    expect(promptValidator()->check('', $closure))->toBe('Required.')
        ->and(promptValidator()->check('ok', $closure))->toBeNull();
});

it('says validation rules need a closure validator, instead of building a missing Validator', function (mixed $rules) {
    expect(fn () => promptValidator()->check('x', $rules))
        ->toThrow(InvalidArgumentException::class, 'need a closure validator in Venusian');
})->with([
    'rule string' => ['required|min:3'],
    'rule list' => [['required', 'min:3']],
    'named rule list' => [['name' => ['required']]],
]);
