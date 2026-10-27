<?php

use Carbon\Carbon;
use Voyager\Database\Instrument\Concerns\HasUuids;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsTo;
use Voyager\Database\Instrument\SoftDeletes;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

/** Every model event a lifecycle test listens for, in the order they fire. */
final class Heard
{
    public static array $events = [];
}

abstract class LifecycleModel extends Model
{
    public static array $tables = [];

    protected $guarded = [];

    public function getTable(): string
    {
        return static::$tables[static::class];
    }

    protected static function booted(): void
    {
        foreach (['saving', 'creating', 'created', 'updating', 'updated', 'saved', 'deleting', 'deleted', 'trashed', 'forceDeleting', 'forceDeleted', 'restoring', 'restored'] as $event) {
            static::registerModelEvent($event, function ($model) use ($event) {
                Heard::$events[] = $event;
            });
        }
    }
}

class LifecycleShelf extends LifecycleModel {}

class LifecycleItem extends LifecycleModel
{
    protected $touches = ['shelf'];

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(LifecycleShelf::class, 'shelf_id');
    }
}

class LifecycleNote extends LifecycleModel
{
    use HasUuids;
}

class LifecycleTrashable extends LifecycleModel
{
    use SoftDeletes;
}

beforeEach(function () {
    $this->app = DatabaseApp::boot();
    $this->app['config']->set('database.default', 'sqlite');
    Heard::$events = [];
    Carbon::setTestNow('2026-10-27 12:00:00');

    $stamped = function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    };
    LifecycleModel::$tables = [
        LifecycleShelf::class => DatabaseApp::table($this->app, 'sqlite', $stamped),
        LifecycleItem::class => DatabaseApp::table($this->app, 'sqlite', function ($table) {
            $table->id();
            $table->foreignId('shelf_id')->nullable();
            $table->string('name');
            $table->timestamps();
        }),
        LifecycleNote::class => DatabaseApp::table($this->app, 'sqlite', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        }),
        LifecycleTrashable::class => DatabaseApp::table($this->app, 'sqlite', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        }),
    ];
});
afterEach(function () {
    Carbon::setTestNow();
    foreach ([LifecycleShelf::class, LifecycleItem::class, LifecycleNote::class, LifecycleTrashable::class] as $model) {
        $model::flushEventListeners();
        $model::clearBootedModels();
    }
    DatabaseApp::tearDown($this->app, $this);
});

it('inserts a new model: its events in order, its key, timestamps and state', function () {
    $shelf = new LifecycleShelf(['name' => 'fiction']);

    expect($shelf->save())->toBeTrue()
        ->and(Heard::$events)->toBe(['saving', 'creating', 'created', 'saved'])
        ->and($shelf->getKey())->toBe(1)
        ->and($shelf->exists)->toBeTrue()
        ->and($shelf->wasRecentlyCreated)->toBeTrue()
        ->and($shelf->created_at->toDateTimeString())->toBe('2026-10-27 12:00:00')
        ->and($shelf->isDirty())->toBeFalse()
        ->and($shelf->getOriginal('name'))->toBe('fiction')
        ->and(LifecycleShelf::find(1)->name)->toBe('fiction');
});

it('gives a non-incrementing model its unique id before it is created', function () {
    $note = LifecycleNote::create(['name' => 'remember']);

    expect($note->getKey())->toBeString()->toHaveLength(36)
        ->and(LifecycleNote::find($note->getKey())->name)->toBe('remember')
        ->and(Heard::$events)->toBe(['saving', 'creating', 'created', 'saved']);
});

it('updates only a dirty model, recording its changes', function () {
    $shelf = LifecycleShelf::create(['name' => 'fiction']);
    Heard::$events = [];
    Carbon::setTestNow('2026-10-27 13:00:00');

    $clean = $shelf->save();
    $clean_events = Heard::$events;
    Heard::$events = [];

    $updated = $shelf->update(['name' => 'novels']);

    expect([$clean, $clean_events])->toBe([true, ['saving', 'saved']])
        ->and($updated)->toBeTrue()
        ->and(Heard::$events)->toBe(['saving', 'updating', 'updated', 'saved'])
        ->and($shelf->getChanges())->toHaveKeys(['name', 'updated_at'])
        ->and($shelf->updated_at->toDateTimeString())->toBe('2026-10-27 13:00:00')
        ->and($shelf->isDirty())->toBeFalse()
        ->and(LifecycleShelf::find(1)->name)->toBe('novels')
        ->and((new LifecycleShelf)->update(['name' => 'x']))->toBeFalse();
});

it('touches the owner when a model that names it is saved', function () {
    $shelf = LifecycleShelf::create(['name' => 'fiction']);
    Carbon::setTestNow('2026-10-27 14:00:00');

    LifecycleItem::create(['name' => 'dune', 'shelf_id' => $shelf->getKey()]);

    expect(LifecycleShelf::find($shelf->getKey())->updated_at->toDateTimeString())->toBe('2026-10-27 14:00:00');
});

it('saves and deletes nothing when saving or deleting is cancelled', function () {
    LifecycleShelf::saving(fn () => false);
    $shelf = new LifecycleShelf(['name' => 'never']);

    expect($shelf->save())->toBeFalse()
        ->and($shelf->exists)->toBeFalse()
        ->and(LifecycleShelf::count())->toBe(0);

    LifecycleShelf::flushEventListeners();
    $kept = LifecycleShelf::create(['name' => 'kept']);
    LifecycleShelf::deleting(fn () => false);

    expect($kept->delete())->toBeFalse()
        ->and($kept->exists)->toBeTrue()
        ->and(LifecycleShelf::count())->toBe(1);
});

it('deletes a model, and nothing for one that does not exist', function () {
    $shelf = LifecycleShelf::create(['name' => 'gone']);
    Heard::$events = [];

    expect($shelf->delete())->toBeTrue()
        ->and(Heard::$events)->toBe(['deleting', 'deleted'])
        ->and($shelf->exists)->toBeFalse()
        ->and(LifecycleShelf::count())->toBe(0)
        ->and((new LifecycleShelf)->delete())->toBeNull()
        ->and((new LifecycleShelf)->forceDelete())->toBeNull();
});

it('soft deletes, restores and force deletes a trashable model', function () {
    $item = LifecycleTrashable::create(['name' => 'draft']);
    Heard::$events = [];
    Carbon::setTestNow('2026-10-27 15:00:00');

    expect($item->delete())->toBeTrue()
        ->and(Heard::$events)->toBe(['deleting', 'trashed', 'deleted'])
        ->and($item->trashed())->toBeTrue()
        ->and($item->deleted_at->toDateTimeString())->toBe('2026-10-27 15:00:00')
        ->and($item->exists)->toBeTrue()
        ->and(LifecycleTrashable::count())->toBe(0)
        ->and(LifecycleTrashable::withTrashed()->count())->toBe(1);

    Heard::$events = [];
    $item->restore();
    expect(Heard::$events)->toBe(['restoring', 'saving', 'updating', 'updated', 'saved', 'restored'])
        ->and(LifecycleTrashable::count())->toBe(1);

    Heard::$events = [];
    expect($item->forceDelete())->toBeTrue()
        ->and(Heard::$events)->toBe(['forceDeleting', 'deleting', 'deleted', 'forceDeleted'])
        ->and($item->exists)->toBeFalse()
        ->and(LifecycleTrashable::withTrashed()->count())->toBe(0);
});
