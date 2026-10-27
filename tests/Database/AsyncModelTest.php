<?php

use Venusian\Tests\Database\Fixtures\DatabaseApp;
use Venusian\Tests\Database\Fixtures\PersistedItem;
use Venusian\Tests\Database\Fixtures\PersistedModel;
use Venusian\Tests\Database\Fixtures\PersistedShelf;
use Venusian\Tests\Database\Fixtures\PersistedTrashable;

beforeEach(function () {
    $this->app = DatabaseApp::boot();
    $this->app['config']->set('database.default', 'sqlite');
    $this->loop = $this->app->get('event-loop');
    PersistedModel::$heard = [];

    $stamped = fn ($table) => [$table->id(), $table->foreignId('shelf_id')->nullable(), $table->string('name'), $table->timestamps(), $table->softDeletes()];
    PersistedModel::$tables = [
        PersistedShelf::class => DatabaseApp::table($this->app, 'sqlite', $stamped),
        PersistedItem::class => DatabaseApp::table($this->app, 'sqlite', $stamped),
        PersistedTrashable::class => DatabaseApp::table($this->app, 'sqlite', $stamped),
    ];

    $this->db = $this->app['db']->connection('sqlite');
    $this->db->enableQueryLog();
});
afterEach(function () {
    foreach ([PersistedShelf::class, PersistedItem::class, PersistedTrashable::class] as $model) {
        $model::flushEventListeners();
        $model::clearBootedModels();
    }
    DatabaseApp::tearDown($this->app, $this);
});

/** The writes this process ran itself: an offloaded statement runs in a worker, never here. */
function writesHere($db): array
{
    return array_values(array_filter(array_column($db->getQueryLog(), 'query'), fn ($sql) => preg_match('/^(insert|update|delete)/i', $sql)));
}

/** Whether every lifecycle event fired in this process. */
function eventsRanHere(): bool
{
    return PersistedModel::$heard !== [] && array_unique(array_column(PersistedModel::$heard, 1)) === [getmypid()];
}

it('inserts a new model: the same lifecycle here, the statement in a worker', function () {
    $shelf = new PersistedShelf(['name' => 'fiction']);

    expect($this->loop->await($shelf->saveAsync()))->toBeTrue()
        ->and(PersistedModel::events())->toBe(['saving', 'creating', 'created', 'saved'])
        ->and(eventsRanHere())->toBeTrue()
        ->and($shelf->getKey())->toBe(1)
        ->and([$shelf->exists, $shelf->wasRecentlyCreated, $shelf->isDirty()])->toBe([true, true, false])
        ->and($shelf->created_at)->not->toBeNull()
        ->and(writesHere($this->db))->toBe([])
        ->and(PersistedShelf::find(1)->name)->toBe('fiction');
});

it('creates a model and hands back the model, as create() does', function () {
    $shelf = $this->loop->await(PersistedShelf::createAsync(['name' => 'poetry']));

    expect($shelf)->toBeInstanceOf(PersistedShelf::class)
        ->and($shelf->getKey())->toBe(1)
        ->and(PersistedModel::events())->toBe(['saving', 'creating', 'created', 'saved'])
        ->and(writesHere($this->db))->toBe([]);
});

it('updates a dirty model in a worker, and runs no statement for a clean one', function () {
    $shelf = PersistedShelf::create(['name' => 'fiction']);
    $this->db->flushQueryLog();
    PersistedModel::$heard = [];

    $clean = $this->loop->await($shelf->saveAsync());
    $clean_events = PersistedModel::events();
    PersistedModel::$heard = [];

    expect([$clean, $clean_events])->toBe([true, ['saving', 'saved']])
        ->and($this->loop->await($shelf->updateAsync(['name' => 'novels'])))->toBeTrue()
        ->and(PersistedModel::events())->toBe(['saving', 'updating', 'updated', 'saved'])
        ->and(eventsRanHere())->toBeTrue()
        ->and($shelf->getChanges())->toHaveKey('name')
        ->and(writesHere($this->db))->toBe([])
        ->and(PersistedShelf::find(1)->name)->toBe('novels')
        ->and($this->loop->await((new PersistedShelf)->updateAsync(['name' => 'x'])))->toBeFalse();
});

it('sends the owner\'s touch to a worker too', function () {
    $shelf = PersistedShelf::create(['name' => 'fiction']);
    $this->db->table(PersistedModel::$tables[PersistedShelf::class])->update(['updated_at' => '2000-01-01 00:00:00']);
    $this->db->flushQueryLog();

    $this->loop->await(PersistedItem::createAsync(['name' => 'dune', 'shelf_id' => $shelf->getKey()]));

    expect(PersistedShelf::find($shelf->getKey())->updated_at->year)->not->toBe(2000)
        ->and(writesHere($this->db))->toBe([]);
});

it('resolves false and offloads nothing when saving or deleting is cancelled', function () {
    PersistedShelf::saving(fn () => false);

    expect($this->loop->await((new PersistedShelf(['name' => 'never']))->saveAsync()))->toBeFalse()
        ->and(PersistedShelf::count())->toBe(0);

    PersistedShelf::flushEventListeners();
    $kept = PersistedShelf::create(['name' => 'kept']);
    PersistedShelf::deleting(fn () => false);

    expect($this->loop->await($kept->deleteAsync()))->toBeFalse()
        ->and($kept->exists)->toBeTrue()
        ->and(PersistedShelf::count())->toBe(1);
});

it('deletes in a worker, and resolves null for a model that does not exist', function () {
    $shelf = PersistedShelf::create(['name' => 'gone']);
    $this->db->flushQueryLog();
    PersistedModel::$heard = [];

    expect($this->loop->await($shelf->deleteAsync()))->toBeTrue()
        ->and(PersistedModel::events())->toBe(['deleting', 'deleted'])
        ->and(eventsRanHere())->toBeTrue()
        ->and($shelf->exists)->toBeFalse()
        ->and(writesHere($this->db))->toBe([])
        ->and(PersistedShelf::count())->toBe(0)
        ->and($this->loop->await((new PersistedShelf)->deleteAsync()))->toBeNull();
});

it('soft deletes and force deletes in a worker', function () {
    $item = PersistedTrashable::create(['name' => 'draft']);
    $this->db->flushQueryLog();
    PersistedModel::$heard = [];

    expect($this->loop->await($item->deleteAsync()))->toBeTrue()
        ->and(PersistedModel::events())->toBe(['deleting', 'trashed', 'deleted'])
        ->and($item->trashed())->toBeTrue()
        ->and(PersistedTrashable::count())->toBe(0)
        ->and(PersistedTrashable::withTrashed()->count())->toBe(1);

    PersistedModel::$heard = [];

    expect($this->loop->await($item->forceDeleteAsync()))->toBeTrue()
        ->and(PersistedModel::events())->toBe(['forceDeleting', 'deleting', 'deleted', 'forceDeleted'])
        ->and(PersistedTrashable::withTrashed()->count())->toBe(0)
        ->and(writesHere($this->db))->toBe([]);
});

it('leaves a blocking save elsewhere blocking while an async save is in flight', function () {
    $async = $this->loop->async(fn () => null);
    $pending = (new PersistedShelf(['name' => 'async']))->saveAsync();

    (new PersistedShelf(['name' => 'blocking']))->save();
    $here = writesHere($this->db);
    $this->loop->await($pending);

    expect($here)->toHaveCount(1)
        ->and($here[0])->toContain('insert')
        ->and(PersistedShelf::count())->toBe(2);
});

it('refuses a second async save of the same model while the first is in flight', function () {
    $shelf = new PersistedShelf(['name' => 'once']);
    $first = $shelf->saveAsync();

    expect(fn () => $shelf->saveAsync())->toThrow(LogicException::class, 'already being saved or deleted asynchronously');

    $this->loop->await($first);

    expect($this->loop->await($shelf->updateAsync(['name' => 'twice'])))->toBeTrue();
});

it('refuses to offload inside an open transaction, before running anything', function () {
    $this->db->beginTransaction();
    $shelf = new PersistedShelf(['name' => 'inside']);

    expect(fn () => $shelf->saveAsync())->toThrow(LogicException::class, 'transaction open')
        ->and(PersistedModel::events())->toBe([]);

    $this->db->rollBack();
});
