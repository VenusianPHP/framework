<?php

use PHPUnit\Framework\ExpectationFailedException;
use Venusian\Tests\Database\Fixtures\TestingHost;
use Venusian\Tests\Database\Fixtures\Widget;
use Voyager\Database\Testing\Concerns\InteractsWithDatabase;
use Voyager\Database\Testing\DatabaseMigrations;
use Voyager\Database\Testing\DatabaseTransactions;
use Voyager\Database\Testing\DatabaseTruncation;
use Voyager\Database\Testing\RefreshDatabase;
use Voyager\Database\Testing\RefreshDatabaseState;

beforeEach(function () {
    $this->file = sys_get_temp_dir().'/venusian-dbtesting-'.bin2hex(random_bytes(5)).'.sqlite';
    touch($this->file);
    TestingHost::$migrated = 0;
    RefreshDatabaseState::$migrated = false;
    RefreshDatabaseState::$inMemoryConnections = [];
});

afterEach(function () {
    @unlink($this->file);
    RefreshDatabaseState::$migrated = false;
    RefreshDatabaseState::$inMemoryConnections = [];
});

/** A host using RefreshDatabase on the given database. */
function refreshing(string $database): TestingHost
{
    return new class($database) extends TestingHost {
        use RefreshDatabase;
    };
}

it('migrates once per process and rolls each test back', function () {
    $first = refreshing($this->file)->begin();
    $first->db()->table('widgets')->insert(['name' => 'kept only in this test']);
    expect($first->db()->table('widgets')->count())->toBe(1);
    $first->end($this);

    $second = refreshing($this->file)->begin();
    expect(TestingHost::$migrated)->toBe(1)
        ->and($second->db()->table('widgets')->count())->toBe(0);
    $second->end($this);
});

it('migrates again after a test that committed its transaction', function () {
    $first = refreshing($this->file)->begin();
    $first->db()->table('widgets')->insert(['name' => 'committed']);
    $first->db()->commit();
    $first->end($this);

    expect(RefreshDatabaseState::$migrated)->toBeFalse();

    $second = refreshing($this->file)->begin();
    expect(TestingHost::$migrated)->toBe(2)
        ->and($second->db()->table('widgets')->count())->toBe(0);
    $second->end($this);
});

it('keeps an in-memory database migrated across the tests\' apps', function () {
    $first = refreshing(':memory:')->begin();
    $first->db()->table('widgets')->insert(['name' => 'rolled back']);
    $first->end($this);

    $second = refreshing(':memory:')->begin();
    expect(TestingHost::$migrated)->toBe(1)
        ->and($second->db()->getSchemaBuilder()->hasTable('widgets'))->toBeTrue()
        ->and($second->db()->table('widgets')->count())->toBe(0);
    $second->end($this);
});

it('runs after-commit callbacks as if the test\'s own transaction were not there', function () {
    $host = refreshing($this->file)->begin();
    $ran = [];

    $host->db()->afterCommit(function () use (&$ran) {
        $ran[] = 'outside';
    });

    $host->db()->transaction(function ($db) use (&$ran) {
        $db->afterCommit(function () use (&$ran) {
            $ran[] = 'inside';
        });

        expect($ran)->toBe(['outside']);
    });

    expect($ran)->toBe(['outside', 'inside']);
    $host->end($this);
});

it('wraps tests in transactions without migrating', function () {
    $make = fn () => new class($this->file) extends TestingHost {
        use DatabaseTransactions;
    };
    $setup = $make()->begin();
    $setup->db()->getSchemaBuilder()->create('notes', fn ($table) => [$table->id(), $table->string('body')]);
    $setup->db()->commit();
    $setup->end($this);

    $first = $make()->begin();
    $first->db()->table('notes')->insert(['body' => 'rolled back']);
    expect($first->db()->table('notes')->count())->toBe(1);
    $first->end($this);

    $second = $make()->begin();
    expect(TestingHost::$migrated)->toBe(0)
        ->and($second->db()->table('notes')->count())->toBe(0);
    $second->end($this);
});

it('migrates before each test and rolls back after it', function () {
    $make = fn () => new class($this->file) extends TestingHost {
        use DatabaseMigrations;
    };

    $first = $make()->begin();
    $first->db()->table('widgets')->insert(['name' => 'gone with the rollback']);
    $first->end($this);

    expect($make()->begin()->db()->getSchemaBuilder()->hasTable('widgets'))->toBeTrue();
    $probe = (new class($this->file) extends TestingHost {})->begin();
    expect(TestingHost::$migrated)->toBe(2);
    $probe->end($this);
});

it('empties the tables before every test after the first, keeping the migrations table', function () {
    $make = fn () => new class($this->file) extends TestingHost {
        use DatabaseTruncation;
    };

    $first = $make()->begin();
    $first->db()->table('widgets')->insert(['name' => 'truncated next time']);
    $first->end($this);

    $second = $make()->begin();
    expect(TestingHost::$migrated)->toBe(1)
        ->and($second->db()->table('widgets')->count())->toBe(0)
        ->and($second->db()->table('migrations')->count())->toBe(1);
    $second->end($this);
});

it('lets offloaded queries and async saves see the test\'s rows under DatabaseMigrations', function () {
    $host = (new class($this->file) extends TestingHost {
        use DatabaseMigrations;
    })->begin();
    $loop = $host->app['event-loop'];

    $widget = $loop->await(Widget::createAsync(['name' => 'saved in a worker']));

    expect($widget->exists)->toBeTrue()
        ->and($loop->await(Widget::query()->via()->count()))->toBe(1)
        ->and(Widget::first()->name)->toBe('saved in a worker');
    $host->end($this);
});

it('asserts what the database holds', function () {
    $host = (new class($this->file) extends TestingHost {
        use InteractsWithDatabase, RefreshDatabase;
    })->begin();

    $kept = Widget::create(['name' => 'kept']);
    $trashed = tap(Widget::create(['name' => 'trashed']))->delete();
    $missing = new Widget(['id' => 99]);

    $host->assertDatabaseHas('widgets', ['name' => 'kept'])
        ->assertDatabaseMissing('widgets', ['name' => 'never'])
        ->assertDatabaseCount('widgets', 2)
        ->assertModelExists($kept)
        ->assertModelMissing($missing)
        ->assertSoftDeleted($trashed)
        ->assertNotSoftDeleted($kept);

    expect(fn () => $host->assertDatabaseHas('widgets', ['name' => 'never']))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $host->assertDatabaseMissing('widgets', ['name' => 'kept']))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $host->assertDatabaseCount('widgets', 5))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $host->assertDatabaseEmpty('widgets'))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $host->assertSoftDeleted($kept))->toThrow(ExpectationFailedException::class);

    $host->end($this);
});

it('checks the expected query count once the test is done', function () {
    $host = (new class($this->file) extends TestingHost {
        use InteractsWithDatabase, RefreshDatabase;
    })->begin();

    $host->expectsDatabaseQueryCount(2);
    Widget::count();

    expect(fn () => $host->end($this))->toThrow(ExpectationFailedException::class, 'Expected 2 database queries');
});
