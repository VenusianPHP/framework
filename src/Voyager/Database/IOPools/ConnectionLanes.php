<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Closure;
use Throwable;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;

/**
 * Orders offloaded calls per connection, as a reader-writer lock would: reads run side by side,
 * a write waits for every earlier call on its connection and holds back every later one. Waiting
 * calls count as well as running ones, so nothing overtakes an earlier call it conflicts with.
 * Calls on different connections never wait on each other.
 *
 * One set per loop: blocking queries find it through current() and settle() before they run.
 */
final class ConnectionLanes
{
    private static ?self $current = null;

    /**
     * @var list<array{connection: string, write: bool, start: Closure(): Promise, promise: Promise}> oldest first
     */
    private array $waiting = [];

    /**
     * @var array<int, array{connection: string, write: bool}>
     */
    private array $running = [];

    private int $next_id = 0;

    private function __construct(private readonly Loop $loop) {}

    /** The lanes of this loop, made the first time a call is offloaded on it. */
    public static function for(Loop $loop): self
    {
        if (is_null(self::$current) || self::$current->loop !== $loop) {
            self::$current = new self($loop);
        }

        return self::$current;
    }

    /** The lanes of the loop calls were last offloaded on, if any. */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * @param Closure(): Promise $start sends the call; its promise settles the one run() returns
     */
    public function run(string $connection, bool $write, Closure $start): Promise
    {
        $promise = $this->loop->promise();
        $this->waiting[] = ['connection' => $connection, 'write' => $write, 'start' => $start, 'promise' => $promise];
        $this->dispatch();

        return $promise;
    }

    /**
     * Blocks until a blocking call on $connection may run: a read waits for the offloaded writes,
     * a write for every offloaded call.
     */
    public function settle(string $connection, bool $write): void
    {
        if ($this->holds($connection, $write)) {
            $this->loop->until(fn (): bool => ! $this->holds($connection, $write));
        }
    }

    private function holds(string $connection, bool $write): bool
    {
        foreach ([...$this->waiting, ...array_values($this->running)] as $call) {
            if ($call['connection'] === $connection && ($write || $call['write'])) {
                return true;
            }
        }

        return false;
    }

    private function dispatch(): void
    {
        $held = array_values($this->running);
        [$ready, $still] = [[], []];

        foreach ($this->waiting as $call) {
            $blocked = false;

            foreach ($held as $other) {
                if ($other['connection'] === $call['connection'] && ($call['write'] || $other['write'])) {
                    $blocked = true;
                    break;
                }
            }

            $blocked ? $still[] = $call : $ready[] = $call;
            $held[] = $call;
        }

        $this->waiting = $still;
        $released = false;

        foreach ($ready as $call) {
            $id = $this->next_id++;
            $this->running[$id] = ['connection' => $call['connection'], 'write' => $call['write']];

            try {
                $sent = ($call['start'])();
            } catch (Throwable $e) {
                unset($this->running[$id]);
                $call['promise']->reject($e);
                $released = true;
                continue;
            }

            $sent->then(
                function (mixed $value) use ($id, $call): mixed {
                    $call['promise']->resolve($value);
                    $this->release($id);

                    return $value;
                },
                function (Throwable $e) use ($id, $call): null {
                    $call['promise']->reject($e);
                    $this->release($id);

                    return null;
                },
            );
        }

        if ($released) {
            $this->dispatch();
        }
    }

    private function release(int $id): void
    {
        unset($this->running[$id]);
        $this->dispatch();
    }
}
