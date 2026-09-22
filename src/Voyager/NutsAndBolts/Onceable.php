<?php

namespace Voyager\NutsAndBolts;

use Closure;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use Voyager\Contracts\NutsAndBolts\HasOnceHash;

class Onceable
{
    /**
     * Create a new onceable instance.
     *
     * @param  string  $hash
     * @param  object|null  $object
     * @param callable $callable
     */
    public function __construct(
        public string   $hash,
        public ?object  $object,
        public Closure $callable,
    ) {
        //
    }

    /**
     * Tries to create a new onceable instance from the given trace.
     *
     * @param array<int, array<string, mixed>> $trace
     * @param callable $callable
     * @return static|null
     */
    public static function tryFromTrace(array $trace, callable $callable): null|static
    {
        if (! is_null($hash = static::hashFromTrace($trace, $callable))) {
            $object = static::objectFromTrace($trace);

            return new static($hash, $object, $callable);
        }

        return null;
    }

    /**
     * Computes the object of the onceable from the given trace, if any.
     *
     * @param  array<int, array<string, mixed>>  $trace
     * @return object|null
     */
    protected static function objectFromTrace(array $trace): ?object
    {
        return $trace[1]['object'] ?? null;
    }

    /**
     * Computes the hash of the onceable from the given trace.
     *
     * @param  array<int, array<string, mixed>>  $trace
     */
    protected static function hashFromTrace(array $trace, callable $callable): ?string
    {
        if (str_contains($trace[0]['file'] ?? '', 'eval()\'d code')) {
            return null;
        }

        $uses = array_map(
            static function (mixed $argument) {
                if ($argument instanceof HasOnceHash) {
                    return $argument->onceHash();
                }

                if (is_object($argument)) {
                    return spl_object_hash($argument);
                }

                return $argument;
            },
            $callable instanceof Closure ? (new ReflectionClosure($callable))->getClosureUsedVariables() : [],
        );

        $class = $callable instanceof Closure ? (new ReflectionClosure($callable))->getClosureCalledClass()?->getName() : null;

        $class ??= isset($trace[1]['class']) ? $trace[1]['class'] : null;

        return hash('xxh128', sprintf(
            '%s@%s%s:%s (%s)',
            $trace[0]['file'],
            $class ? $class.'@' : '',
            $trace[1]['function'],
            $trace[0]['line'],
            serialize($uses),
        ));
    }

}
