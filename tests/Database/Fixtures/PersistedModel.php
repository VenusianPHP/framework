<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\Model;

/**
 * A model for the async persistence tests: autoloadable, so a worker can unserialize builders
 * that name it. Each lifecycle event it fires is recorded with the process it fired in.
 */
abstract class PersistedModel extends Model
{
    /** @var array<class-string, string> */
    public static array $tables = [];

    /** @var list<array{0: string, 1: int}> event, process id */
    public static array $heard = [];

    protected $guarded = [];

    public function getTable(): string
    {
        return static::$tables[static::class];
    }

    protected static function booted(): void
    {
        foreach (['saving', 'creating', 'created', 'updating', 'updated', 'saved', 'deleting', 'deleted', 'trashed', 'forceDeleting', 'forceDeleted'] as $event) {
            static::registerModelEvent($event, function () use ($event) {
                PersistedModel::$heard[] = [$event, getmypid()];
            });
        }
    }

    /** @return list<string> */
    public static function events(): array
    {
        return array_column(self::$heard, 0);
    }
}
