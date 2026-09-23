<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Pagination\AbstractCursorPaginator;
use Voyager\Pagination\AbstractPaginator;

/**
 * The step a worker cannot do: the caller's listeners hear `retrieved` for every model that came back.
 */
final class Arrived
{
    public static function models(mixed $value): mixed
    {
        $seen = [];
        self::walk($value, $seen);

        return $value;
    }

    private static function walk(mixed $value, array &$seen): void
    {
        match (true) {
            $value instanceof Model => self::one($value, $seen),
            $value instanceof Collection => $value->each(function ($item) use (&$seen) {
                if ($item instanceof Model) {
                    self::one($item, $seen);
                }
            }),
            $value instanceof AbstractPaginator, $value instanceof AbstractCursorPaginator => self::walk($value->getCollection(), $seen),
            default => null,
        };
    }

    private static function one(Model $model, array &$seen): void
    {
        $id = spl_object_id($model);

        if (isset($seen[$id])) {
            return;
        }

        $seen[$id] = true;
        $model->fireRetrieved();

        foreach ($model->getRelations() as $related) {
            self::walk($related, $seen);
        }
    }
}
