<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Ramsey\Uuid\Uuid;
use Throwable;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\Pumpable;
use Voyager\Contracts\IOPools\Tickable;
use Voyager\Contracts\IOPools\WorkTarget;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;
use Voyager\Database\Query\Builder as QueryBuilder;
use Voyager\NutsAndBolts\Collection;

/**
 * A query as ModelChunk mail: keyed pages, one gig in flight, each page's last id keys the next.
 * A full page is held until the next page arrives, so an exact multiple is marked last without an empty chunk.
 * Registered on the loop for its lifetime; forgets itself on the last page or the first failure.
 */
final class QueryStreamResource implements Tickable, Pumpable
{
    private mixed $last_id = null;
    private int $page = 0;
    private int $count = 0;
    private ?Promise $pending = null;
    private ?Collection $held = null;
    private int $held_page = 0;
    private array $mail = [];
    private Promise $done;
    private bool $finished = false;
    private readonly string $uuid;
    private readonly string $column;

    public function __construct(
        private readonly Loop $loop,
        private readonly WorkTarget $target,
        private readonly QueryBuilder|InstrumentBuilder $builder,
        private readonly int $chunk = 1000,
        ?string $column = null,
    ) {
        $this->uuid = Uuid::uuid4()->toString();
        $this->column = $column ?? ($builder instanceof InstrumentBuilder ? $builder->getModel()->getQualifiedKeyName() : 'id');
        $this->done = $this->loop->promise();
        $this->loop->resource($this->name(), $this);
        $this->request();
    }

    public function done(): Promise
    {
        return $this->done;
    }

    public function tick(): void
    {
        // gigs settle through the promise engine; tick only keeps a page in flight
        $this->request();
    }

    public function pump(): array
    {
        [$mail, $this->mail] = [$this->mail, []];

        return $mail;
    }

    private function request(): void
    {
        if ($this->finished || ! is_null($this->pending)) {
            return;
        }

        $page = (clone $this->builder)->orderBy($this->column)->limit($this->chunk);

        if (! is_null($this->last_id)) {
            $page->where($this->column, '>', $this->last_id);
        }

        $this->pending = $this->target->run(new QueryGig($page, 'get', []))
            ->then(function (Collection $rows) { $this->pending = null; $this->deliver($rows); })
            ->error(fn (Throwable $e) => $this->fail($e));
    }

    private function deliver(Collection $rows): void
    {
        $n = $rows->count();

        if ($n === 0) {
            $this->releaseHeld(true);
            $this->finish();
            $this->done->resolve($this->count);

            return;
        }

        $this->releaseHeld(false);

        $this->page++;
        $this->count += $n;
        $this->last_id = data_get($rows->last(), $this->keyName());

        if ($n < $this->chunk) {
            $this->mail[] = new ModelChunk($this->connectionName(), $this->table(), $this->page, Arrived::models($rows), true);
            $this->finish();
            $this->done->resolve($this->count);

            return;
        }

        $this->held = $rows;
        $this->held_page = $this->page;
        $this->request();
    }

    private function releaseHeld(bool $last): void
    {
        if (is_null($this->held)) {
            return;
        }

        $this->mail[] = new ModelChunk($this->connectionName(), $this->table(), $this->held_page, Arrived::models($this->held), $last);
        $this->held = null;
    }

    private function fail(Throwable $e): void
    {
        if (! $this->finished) {
            $this->finish();
            $this->done->reject($e);
        }
    }

    private function finish(): void
    {
        $this->finished = true;
        $this->pending = null;
        $this->loop->forget($this->name());
    }

    public function name(): string
    {
        return 'query-stream:'.$this->connectionName().':'.$this->table().':'.$this->uuid;
    }

    private function keyName(): string
    {
        return str_contains($this->column, '.') ? substr($this->column, strrpos($this->column, '.') + 1) : $this->column;
    }

    private function query(): QueryBuilder
    {
        return $this->builder instanceof InstrumentBuilder ? $this->builder->getQuery() : $this->builder;
    }

    private function connectionName(): string
    {
        return (string) $this->query()->getConnection()->getName();
    }

    private function table(): string
    {
        return (string) $this->query()->from;
    }
}
