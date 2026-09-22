<?php

namespace Voyager\NutsAndBolts;

use Voyager\NutsAndBolts\Defer\DeferredCallback;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Symfony\Component\Process\PhpExecutableFinder;

if (! function_exists('Voyager\NutsAndBolts\join_paths')) {
    /**
     * Join the given paths together.
     *
     * @param string|null $basePath
     * @param string ...$paths
     * @return string
     */
    function join_paths(?string $basePath, string ...$paths): string
    {
        foreach ($paths as $index => $path) {
            if (empty($path) && $path !== '0') {
                unset($paths[$index]);
            } else {
                $paths[$index] = DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
            }
        }

        return $basePath.implode('', $paths);
    }
}

if (! function_exists('Illuminate\Support\enum_value')) {
    /**
     * Return a scalar value for the given value that might be an enum.
     *
     * @param  TValue  $value
     * @param  callable(TValue): TDefault|null  $default
     * @return ($value is empty ? TDefault : mixed)
          *@internal
     *
     * @template TValue
     * @template TDefault
     *
     */
    function enum_value($value, ?callable $default = null): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,

            default => $value ?? value($default),
        };
    }
}

if (! function_exists('Voyager\\NutsAndBolts\\defer')) {
    /**
     * Defer execution of the given callback.
     *
     * With no callback, hands back the collection the application drains when a command finishes.
     *
     * @return ($callback is null ? DeferredCallbackCollection : DeferredCallback)
     */
    function defer(?callable $callback = null, ?string $name = null, bool $always = false): DeferredCallback|DeferredCallbackCollection
    {
        if ($callback === null) {
            return app(DeferredCallbackCollection::class);
        }

        return tap(
            new DeferredCallback($callback, $name, $always),
            fn ($deferred) => app(DeferredCallbackCollection::class)[] = $deferred
        );
    }
}

if (! function_exists('Voyager\NutsAndBolts\php_binary')) {
    /**
     * Determine the PHP Binary.
     */
    function php_binary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}

if (! function_exists('Voyager\NutsAndBolts\computer_binary')) {
    /**
     * Determine the proper Computer executable.
     */
    function computer_binary(): string
    {
        return defined('COMPUTER_BINARY') ? COMPUTER_BINARY : 'computer';
    }
}

if (! function_exists('Voyager\NutsAndBolts\rocket_binary')) {
    /**
     * Determine the proper Rocket executable.
     */
    function rocket_binary(): string
    {
        return defined('ROCKET_BINARY') ? ROCKET_BINARY : 'rocket';
    }
}
