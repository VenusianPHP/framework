<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Throwable;
use InvalidArgumentException;
use Voyager\Contracts\IOPools\Promise;
use Voyager\NutsAndBolts\Collection;
use Voyager\Database\Query\Builder as QueryBuilder;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;

/**
 * A query's rows as QueryChunk mail, page by page, keyed on a column: each page asks for the rows
 * past the last one's key, so rows written between pages neither repeat nor shift a page. One page
 * is in flight at a time, and each is a read on the connection's lanes, so a write made meanwhile
 * lands between two pages. A full page is held until the next one arrives, so the last chunk is
 * marked last without an empty one after it; a query with no rows sends one empty chunk, marked last.
 */
final class QueryStream
{
    private mixed $last_key = null;

    private int $page = 0;

    private int $count = 0;

    private ?Collection $held = null;

    private readonly Promise $done;

    private readonly string $column;

    private readonly string $table;

    public function __construct(
        private readonly QueryBuilder|InstrumentBuilder $builder,
        private readonly Offload $offload,
        private readonly int $chunk,
        ?string $column,
    ) {
        if ($chunk < 1) {
            throw new InvalidArgumentException("A stream reads at least one row per page, {$chunk} given.");
        }

        $this->column = $column ?? ($builder instanceof InstrumentBuilder ? $builder->getModel()->getQualifiedKeyName() : 'id');
        $this->table = (string) ($builder instanceof InstrumentBuilder ? $builder->getQuery()->from : $builder->from);
        $this->done = $offload->loop->promise();
    }

    /** @return Promise the number of rows streamed */
    public function start(): Promise
    {
        $this->request();

        return $this->done;
    }

    private function request(): void
    {
        $page = (clone $this->builder)->orderBy($this->column)->limit($this->chunk);

        if (! is_null($this->last_key)) {
            $page->where($this->column, '>', $this->last_key);
        }

        $this->offload
            ->send(false, fn (): QueryGig => new QueryGig(
                $this->offload->connection, $this->offload->config, 'get', serialize([$page, []]),
            ))
            ->then(
                function (Collection $rows): null {
                    $this->deliver($rows);

                    return null;
                },
                function (Throwable $e): null {
                    $this->done->reject($e);

                    return null;
                },
            );
    }

    private function deliver(Collection $rows): void
    {
        $n = $rows->count();

        if ($n === 0) {
            $this->post($this->held ?? $rows, $this->held ? $this->page : 1, true);
            $this->done->resolve($this->count);

            return;
        }

        if ($this->held) {
            $this->post($this->held, $this->page, false);
        }

        $this->page++;
        $this->count += $n;
        $this->last_key = data_get($rows->last(), $this->keyName());

        if ($n < $this->chunk) {
            $this->post($rows, $this->page, true);
            $this->held = null;
            $this->done->resolve($this->count);

            return;
        }

        $this->held = $rows;
        $this->request();
    }

    private function post(Collection $rows, int $page, bool $last): void
    {
        $this->offload->loop->post(new QueryChunk($this->offload->connection, $this->table, $page, Arrived::models($rows), $last));
    }

    private function keyName(): string
    {
        return str_contains($this->column, '.') ? substr($this->column, strrpos($this->column, '.') + 1) : $this->column;
    }
}
