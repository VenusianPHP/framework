<?php

namespace Voyager\Database\Instrument\Concerns;

use Closure;
use Fiber;
use LogicException;
use RuntimeException;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\IOPools\Offload;
use Voyager\IOPools\IOPools;
use WeakMap;

/**
 * save(), update(), delete() and forceDelete() as promises. Each runs the model's own lifecycle
 * in this process, in a loop task: events, observers, timestamps, unique ids and touches, exactly
 * as the blocking call does. Only its SQL statements go to a worker, through the builder's via(),
 * each awaited before the lifecycle goes on, so the task suspends instead of holding the loop.
 */
trait PersistsAsync
{
    /**
     * The loop tasks whose statements go to a worker, and the pool each names.
     *
     * @var \WeakMap<\Fiber, array{pool: ?string}>|null
     */
    private static ?WeakMap $offloading = null;

    /**
     * Whether this model has an async save or delete in flight. Never serialized: a copy made
     * mid-save (a worker's, a queued job's) starts with none in flight.
     */
    protected bool $persisting_async = false;

    /**
     * save(), its statements run in a worker.
     *
     * @param  'thread'|'process'|null  $pool
     * @return \Voyager\Contracts\IOPools\Promise<bool> what save() returns
     */
    public function saveAsync(?string $pool = null): Promise
    {
        return $this->persistAsync($pool, fn () => $this->save());
    }

    /**
     * update(), its statements run in a worker.
     *
     * @param  array<string, mixed>  $attributes
     * @param  'thread'|'process'|null  $pool
     * @return \Voyager\Contracts\IOPools\Promise<bool> what update() returns
     */
    public function updateAsync(array $attributes, ?string $pool = null): Promise
    {
        return $this->persistAsync($pool, fn () => $this->update($attributes));
    }

    /**
     * delete(), its statements run in a worker.
     *
     * @param  'thread'|'process'|null  $pool
     * @return \Voyager\Contracts\IOPools\Promise<bool|null> what delete() returns
     */
    public function deleteAsync(?string $pool = null): Promise
    {
        return $this->persistAsync($pool, fn () => $this->delete());
    }

    /**
     * forceDelete(), its statements run in a worker.
     *
     * @param  'thread'|'process'|null  $pool
     * @return \Voyager\Contracts\IOPools\Promise<bool|null> what forceDelete() returns
     */
    public function forceDeleteAsync(?string $pool = null): Promise
    {
        return $this->persistAsync($pool, fn () => $this->forceDelete());
    }

    /**
     * create(), its statements run in a worker; its events run here, as create()'s do.
     *
     * @param  array<string, mixed>  $attributes
     * @param  'thread'|'process'|null  $pool
     * @return \Voyager\Contracts\IOPools\Promise<static> the model, as create() returns it
     */
    public static function createAsync(array $attributes = [], ?string $pool = null): Promise
    {
        $model = new static($attributes);

        return $model->saveAsync($pool)->then(fn (): static => $model);
    }

    /**
     * Run one statement of a save or delete lifecycle (the model's own, or a relation touching its
     * owners): in a worker, awaited, inside an async save or delete; here otherwise.
     *
     * @internal Called by Model::runStatement() and the relations' touches.
     *
     * @param  \Voyager\Database\Instrument\Builder<*>|\Voyager\Database\Query\Builder  $query
     * @param  array<int, mixed>  $args
     */
    public static function runLifecycleStatement(object $query, string $method, array $args): mixed
    {
        if (is_null($offloading = static::offloadingStatements())) {
            return $query->{$method}(...$args);
        }

        return $query->via($offloading['pool'])->{$method}(...$args)->wait();
    }

    /**
     * The pool the current loop task sends its statements to, or null when they run here.
     *
     * @return array{pool: ?string}|null
     */
    protected static function offloadingStatements(): ?array
    {
        $fiber = Fiber::getCurrent();

        return is_null($fiber) || is_null(self::$offloading) ? null : (self::$offloading[$fiber] ?? null);
    }

    /**
     * Run $lifecycle in a loop task whose statements go to a worker.
     *
     * @throws \LogicException  this model is already being saved or deleted async, or the
     *                          connection can't be reached from a worker as it stands
     * @throws \RuntimeException  there is no loop
     */
    private function persistAsync(?string $pool, Closure $lifecycle): Promise
    {
        if ($this->persisting_async) {
            throw new LogicException('This model is already being saved or deleted asynchronously: wait for that to settle first.');
        }

        // Thrown here, before anything runs: no connection name, an in-memory database, or a
        // transaction open, which an offloaded statement would run outside of.
        Offload::for($this->getConnection(), $pool);

        if (is_null($loop = IOPools::eventLoop())) {
            throw new RuntimeException('Async model operations run on the event loop, and there is none.');
        }

        $this->persisting_async = true;

        return $loop->async(function () use ($pool, $lifecycle) {
            $fiber = Fiber::getCurrent();
            self::$offloading ??= new WeakMap;
            self::$offloading[$fiber] = ['pool' => $pool];

            try {
                return $lifecycle();
            } finally {
                unset(self::$offloading[$fiber]);
                $this->persisting_async = false;
            }
        });
    }
}
