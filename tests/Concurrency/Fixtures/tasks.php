<?php

/**
 * Tasks for the concurrency tests. They're written in plain functions, not in the tests or a
 * class: a closure keeps the class it was written in as its scope, and whatever runs it, a pool
 * worker or another app's `php computer`, has to load that class to rebuild it. These have none.
 */

namespace Venusian\Tests\Concurrency\Fixtures\Tasks;

use Closure;
use DomainException;
use LengthException;
use Voyager\Contracts\Core\FrameworkCore;

function returns(mixed $value): Closure
{
    return static fn () => $value;
}

function sum(int $a, int $b): Closure
{
    return static fn () => $a + $b;
}

function pid(): Closure
{
    return static fn () => getmypid();
}

function basePath(): Closure
{
    return static fn (FrameworkCore $app) => $app->basePath();
}

function sleeps(int $ms, string $then): Closure
{
    return static function () use ($ms, $then): string {
        usleep($ms * 1000);

        return $then;
    };
}

/**
 * Checks in under $dir, then waits up to five seconds for $count check-ins: true only if that many
 * tasks were running at once, however long each took to start.
 */
function meets(string $dir, int $count): Closure
{
    return static function () use ($dir, $count): bool {
        touch($dir.'/'.getmypid());
        $until = microtime(true) + 5;

        while (count(glob($dir.'/*')) < $count) {
            if (microtime(true) > $until) {
                return false;
            }

            usleep(10_000);
        }

        return true;
    };
}

function declines(): Closure
{
    return static fn () => throw new DomainException('card declined');
}

function tooLong(): Closure
{
    return static fn () => throw new LengthException('too long');
}

function holds(mixed $value): Closure
{
    return static fn () => $value;
}
