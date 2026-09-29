<?php

namespace Venusian\Tests\Log\Fixtures;

use Closure;
use DomainException;
use RuntimeException;

final class Caught
{
    /**
     * An exception whose trace holds a closure argument: the kind serialize() refuses. A
     * production ini (zend.exception_ignore_args=On) leaves arguments out of traces, so the
     * fixture turns them on for the throw.
     */
    public static function underClosure(): DomainException
    {
        $charge = function (Closure $on_declined): never {
            throw new DomainException('card declined', 402, new RuntimeException('gateway said no'));
        };

        $ignore_args = ini_set('zend.exception_ignore_args', '0');

        try {
            $charge(fn () => null);
        } catch (DomainException $e) {
            return $e;
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignore_args);
        }
    }
}
