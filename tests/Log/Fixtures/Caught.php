<?php

namespace Venusian\Tests\Log\Fixtures;

use Closure;
use DomainException;
use RuntimeException;

final class Caught
{
    /** An exception whose trace holds a closure argument: the kind serialize() refuses. */
    public static function underClosure(): DomainException
    {
        $charge = function (Closure $on_declined): never {
            throw new DomainException('card declined', 402, new RuntimeException('gateway said no'));
        };

        try {
            $charge(fn () => null);
        } catch (DomainException $e) {
            return $e;
        }
    }
}
