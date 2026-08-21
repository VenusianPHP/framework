<?php

namespace Tests\Database;

use BadMethodCallException;
use Exception;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\Database\Instrument\SoftDeletingScope;
use Voyager\Database\Query\Builder;
use Voyager\Pagination\CursorPaginator;
use Voyager\Pagination\Paginator;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mockery as m;
use Mockery\MockInterface;

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbSoftDeletesCreateSchema()
{
    dbSoftDeletesSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->integer('user_id')->nullable(); // circular reference to parent User
        $table->integer('group_id')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
        $table->softDeletes();
    });

    dbSoftDeletesSchema()->create('posts', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('title');
        $table->integer('priority')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    dbSoftDeletesSchema()->create('comments', function ($table) {
        $table->increments('id');
        $table->integer('owner_id')->nullable();
        $table->string('owner_type')->nullable();
        $table->integer('post_id');
        $table->string('body');
        $table->timestamps();
        $table->softDeletes();
    });

    dbSoftDeletesSchema()->create('addresses', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('address');
        $table->timestamps();
        $table->softDeletes();
    });

    dbSoftDeletesSchema()->create('groups', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });
}

/**
 * Helpers...
 *
 * @return \Tests\Database\SoftDeletesTestUser[]
 */
function dbSoftDeletesCreateUsers()
{
    $taylor = SoftDeletesTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'user_id' => 2]);
    $abigail = SoftDeletesTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

    $taylor->delete();

    return [$taylor, $abigail];
}

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function dbSoftDeletesConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbSoftDeletesSchema()
{
    return dbSoftDeletesConnection()->getSchemaBuilder();
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbSoftDeletesCreateSchema();
});

afterEach(function () {
    Carbon::setTestNow(null);

    dbSoftDeletesSchema()->drop('users');
    dbSoftDeletesSchema()->drop('posts');
    dbSoftDeletesSchema()->drop('comments');
});

test('soft deletes are not retrieved', function () {
    dbSoftDeletesCreateUsers();

    $users = SoftDeletesTestUser::all();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(2)
        ->and(SoftDeletesTestUser::find(1))->toBeNull();
});

test('soft deletes are not retrieved from base query', function () {
    dbSoftDeletesCreateUsers();

    $query = SoftDeletesTestUser::query()->toBase();

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->get())->toHaveCount(1);
});

test('soft deletes are not retrieved from relationship base query', function () {
    [, $abigail] = dbSoftDeletesCreateUsers();

    $abigail->posts()->create(['title' => 'Foo']);
    $abigail->posts()->create(['title' => 'Bar'])->delete();

    $query = $abigail->posts()->toBase();

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->get())->toHaveCount(1);
});

test('soft deletes are not retrieved from builder helpers', function () {
    dbSoftDeletesCreateUsers();

    $count = 0;
    $query = SoftDeletesTestUser::query();
    $query->chunk(2, function ($user) use (&$count) {
        $count += count($user);
    });
    expect($count)->toEqual(1);

    $query = SoftDeletesTestUser::query();
    expect($query->pluck('email')->all())->toHaveCount(1);

    Paginator::currentPageResolver(function () {
        return 1;
    });

    CursorPaginator::currentCursorResolver(function () {
        return null;
    });

    $query = SoftDeletesTestUser::query();
    expect($query->paginate(2)->all())->toHaveCount(1);

    $query = SoftDeletesTestUser::query();
    expect($query->simplePaginate(2)->all())->toHaveCount(1);

    $query = SoftDeletesTestUser::query();
    expect($query->cursorPaginate(2)->all())->toHaveCount(1);

    expect(SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->increment('id'))->toEqual(0)
        ->and(SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->decrement('id'))->toEqual(0);
});

test('with trashed returns all records', function () {
    dbSoftDeletesCreateUsers();

    expect(SoftDeletesTestUser::withTrashed()->get())->toHaveCount(2)
        ->and(SoftDeletesTestUser::withTrashed()->find(1))->toBeInstanceOf(Instrument::class);
});

test('with trashed accepts an argument', function () {
    dbSoftDeletesCreateUsers();

    expect(SoftDeletesTestUser::withTrashed(false)->get())->toHaveCount(1)
        ->and(SoftDeletesTestUser::withTrashed(true)->get())->toHaveCount(2);
});

test('delete sets deleted column', function () {
    dbSoftDeletesCreateUsers();

    expect(SoftDeletesTestUser::withTrashed()->find(1)->deleted_at)->toBeInstanceOf(Carbon::class)
        ->and(SoftDeletesTestUser::find(2)->deleted_at)->toBeNull();
});

test('force delete actually deletes records', function () {
    dbSoftDeletesCreateUsers();
    SoftDeletesTestUser::find(2)->forceDelete();

    $users = SoftDeletesTestUser::withTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(1);
});

test('force delete update exists property', function () {
    dbSoftDeletesCreateUsers();
    $user = SoftDeletesTestUser::find(2);

    expect($user->exists)->toBeTrue();

    $user->forceDelete();

    expect($user->exists)->toBeFalse();
});

test('force delete doesnt update exists property if failed', function () {
    $user = new class() extends SoftDeletesTestUser
    {
        public $exists = true;

        public function newModelQuery()
        {
            return m::spy(parent::newModelQuery(), function (MockInterface $mock) {
                $mock->shouldReceive('forceDelete')->andThrow(new Exception());
            });
        }
    };

    expect($user->exists)->toBeTrue();

    try {
        $user->forceDelete();
    } catch (Exception) {
    }

    expect($user->exists)->toBeTrue();
});

test('force destroy fully deletes record', function () {
    dbSoftDeletesCreateUsers();
    $deleted = SoftDeletesTestUser::forceDestroy(2);

    expect($deleted)->toBe(1);

    $users = SoftDeletesTestUser::withTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(1)
        ->and(SoftDeletesTestUser::find(2))->toBeNull();
});

test('force destroy deletes already deleted record', function () {
    dbSoftDeletesCreateUsers();
    $deleted = SoftDeletesTestUser::forceDestroy(1);

    expect($deleted)->toBe(1);

    $users = SoftDeletesTestUser::withTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(2)
        ->and(SoftDeletesTestUser::find(1))->toBeNull();
});

test('force destroy deletes multiple records', function () {
    dbSoftDeletesCreateUsers();
    $deleted = SoftDeletesTestUser::forceDestroy([1, 2]);

    expect($deleted)->toBe(2)
        ->and(SoftDeletesTestUser::withTrashed()->get()->isEmpty())->toBeTrue();
});

test('force destroy deletes records from collection', function () {
    dbSoftDeletesCreateUsers();
    $deleted = SoftDeletesTestUser::forceDestroy(collect([1, 2]));

    expect($deleted)->toBe(2)
        ->and(SoftDeletesTestUser::withTrashed()->get()->isEmpty())->toBeTrue();
});

test('force destroy deletes records from instrument collection', function () {
    dbSoftDeletesCreateUsers();
    $deleted = SoftDeletesTestUser::forceDestroy(SoftDeletesTestUser::all());

    expect($deleted)->toBe(1);

    $users = SoftDeletesTestUser::withTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(1)
        ->and(SoftDeletesTestUser::find(2))->toBeNull();
});

test('restore restores records', function () {
    dbSoftDeletesCreateUsers();
    $taylor = SoftDeletesTestUser::withTrashed()->find(1);

    expect($taylor->trashed())->toBeTrue();

    $taylor->restore();

    $users = SoftDeletesTestUser::all();

    expect($users)->toHaveCount(2)
        ->and($users->find(1)->deleted_at)->toBeNull()
        ->and($users->find(2)->deleted_at)->toBeNull();
});

test('only trashed only returns trashed records', function () {
    dbSoftDeletesCreateUsers();

    $users = SoftDeletesTestUser::onlyTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(1);
});

test('only without trashed only returns trashed records', function () {
    dbSoftDeletesCreateUsers();

    $users = SoftDeletesTestUser::withoutTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(2);

    $users = SoftDeletesTestUser::withTrashed()->withoutTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toEqual(2);
});

test('first or new', function () {
    dbSoftDeletesCreateUsers();

    $result = SoftDeletesTestUser::firstOrNew(['email' => 'taylorotwell@gmail.com']);
    expect($result->id)->toBeNull();

    $result = SoftDeletesTestUser::withTrashed()->firstOrNew(['email' => 'taylorotwell@gmail.com']);
    expect($result->id)->toEqual(1);
});

test('find or new', function () {
    dbSoftDeletesCreateUsers();

    $result = SoftDeletesTestUser::findOrNew(1);
    expect($result->id)->toBeNull();

    $result = SoftDeletesTestUser::withTrashed()->findOrNew(1);
    expect($result->id)->toEqual(1);
});

test('first or create', function () {
    dbSoftDeletesCreateUsers();

    $result = SoftDeletesTestUser::withTrashed()->firstOrCreate(['email' => 'taylorotwell@gmail.com']);
    expect($result->email)->toBe('taylorotwell@gmail.com')
        ->and(SoftDeletesTestUser::all())->toHaveCount(1);

    $result = SoftDeletesTestUser::firstOrCreate(['email' => 'foo@bar.com']);
    expect($result->email)->toBe('foo@bar.com')
        ->and(SoftDeletesTestUser::all())->toHaveCount(2)
        ->and(SoftDeletesTestUser::withTrashed()->get())->toHaveCount(3);
});

test('create or first', function () {
    dbSoftDeletesCreateUsers();

    $result = SoftDeletesTestUser::withTrashed()->createOrFirst(['email' => 'taylorotwell@gmail.com']);
    expect($result->email)->toBe('taylorotwell@gmail.com')
        ->and(SoftDeletesTestUser::all())->toHaveCount(1);

    $result = SoftDeletesTestUser::createOrFirst(['email' => 'foo@bar.com']);
    expect($result->email)->toBe('foo@bar.com')
        ->and(SoftDeletesTestUser::all())->toHaveCount(2)
        ->and(SoftDeletesTestUser::withTrashed()->get())->toHaveCount(3);
});

/**
 * @throws \Exception
 */
test('update model after soft deleting', function () {
    Carbon::setTestNow($now = Carbon::now());
    dbSoftDeletesCreateUsers();

    /** @var \Tests\Database\SoftDeletesTestUser $userModel */
    $userModel = SoftDeletesTestUser::find(2);
    $userModel->delete();
    expect($userModel->getOriginal('deleted_at'))->toEqual($now->toDateTimeString())
        ->and(SoftDeletesTestUser::find(2))->toBeNull()
        ->and(SoftDeletesTestUser::withTrashed()->find(2))->toEqual($userModel);
});

/**
 * @throws \Exception
 */
test('restore after soft delete', function () {
    dbSoftDeletesCreateUsers();

    /** @var \Tests\Database\SoftDeletesTestUser $userModel */
    $userModel = SoftDeletesTestUser::find(2);
    $userModel->delete();
    $userModel->restore();

    expect(SoftDeletesTestUser::find(2)->id)->toEqual($userModel->id);
});

/**
 * @throws \Exception
 */
test('soft delete after restoring', function () {
    dbSoftDeletesCreateUsers();

    /** @var \Tests\Database\SoftDeletesTestUser $userModel */
    $userModel = SoftDeletesTestUser::withTrashed()->find(1);
    $userModel->restore();
    expect(SoftDeletesTestUser::find(1)->deleted_at)->toEqual($userModel->deleted_at)
        ->and(SoftDeletesTestUser::find(1)->deleted_at)->toEqual($userModel->getOriginal('deleted_at'));
    $userModel->delete();
    expect(SoftDeletesTestUser::find(1))->toBeNull()
        ->and(SoftDeletesTestUser::withTrashed()->find(1)->deleted_at)->toEqual($userModel->deleted_at)
        ->and(SoftDeletesTestUser::withTrashed()->find(1)->deleted_at)->toEqual($userModel->getOriginal('deleted_at'));
});

test('modifying before soft deleting and restoring', function () {
    dbSoftDeletesCreateUsers();

    /** @var \Tests\Database\SoftDeletesTestUser $userModel */
    $userModel = SoftDeletesTestUser::find(2);
    $userModel->email = 'foo@bar.com';
    $userModel->delete();
    $userModel->restore();

    expect(SoftDeletesTestUser::find(2)->id)->toEqual($userModel->id)
        ->and(SoftDeletesTestUser::find(2)->email)->toBe('foo@bar.com');
});

test('update or create', function () {
    dbSoftDeletesCreateUsers();

    $result = SoftDeletesTestUser::updateOrCreate(['email' => 'foo@bar.com'], ['email' => 'bar@baz.com']);
    expect($result->email)->toBe('bar@baz.com')
        ->and(SoftDeletesTestUser::all())->toHaveCount(2);

    $result = SoftDeletesTestUser::withTrashed()->updateOrCreate(['email' => 'taylorotwell@gmail.com'], ['email' => 'foo@bar.com']);
    expect($result->email)->toBe('foo@bar.com')
        ->and(SoftDeletesTestUser::all())->toHaveCount(2)
        ->and(SoftDeletesTestUser::withTrashed()->get())->toHaveCount(3);
});

test('has one relationship can be soft deleted', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $abigail->address()->create(['address' => 'Laravel avenue 43']);

    // delete on builder
    $abigail->address()->delete();

    $abigail = $abigail->fresh();

    expect($abigail->address)->toBeNull()
        ->and($abigail->address()->withTrashed()->first()->address)->toBe('Laravel avenue 43');

    // restore
    $abigail->address()->withTrashed()->restore();

    $abigail = $abigail->fresh();

    expect($abigail->address->address)->toBe('Laravel avenue 43');

    // delete on model
    $abigail->address->delete();

    $abigail = $abigail->fresh();

    expect($abigail->address)->toBeNull()
        ->and($abigail->address()->withTrashed()->first()->address)->toBe('Laravel avenue 43');

    // force delete
    $abigail->address()->withTrashed()->forceDelete();

    $abigail = $abigail->fresh();

    expect($abigail->address)->toBeNull();
});

test('belongs to relationship can be soft deleted', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $group = SoftDeletesTestGroup::create(['name' => 'admin']);
    $abigail->group()->associate($group);
    $abigail->save();

    // delete on builder
    $abigail->group()->delete();

    $abigail = $abigail->fresh();

    expect($abigail->group)->toBeNull()
        ->and($abigail->group()->withTrashed()->first()->name)->toBe('admin');

    // restore
    $abigail->group()->withTrashed()->restore();

    $abigail = $abigail->fresh();

    expect($abigail->group->name)->toBe('admin');

    // delete on model
    $abigail->group->delete();

    $abigail = $abigail->fresh();

    expect($abigail->group)->toBeNull()
        ->and($abigail->group()->withTrashed()->first()->name)->toBe('admin');

    // force delete
    $abigail->group()->withTrashed()->forceDelete();

    $abigail = $abigail->fresh();

    expect($abigail->group()->withTrashed()->first())->toBeNull();
});

test('has many relationship can be soft deleted', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $abigail->posts()->create(['title' => 'First Title']);
    $abigail->posts()->create(['title' => 'Second Title']);

    // delete on builder
    $abigail->posts()->where('title', 'Second Title')->delete();

    $abigail = $abigail->fresh();

    expect($abigail->posts)->toHaveCount(1)
        ->and($abigail->posts->first()->title)->toBe('First Title')
        ->and($abigail->posts()->withTrashed()->get())->toHaveCount(2);

    // restore
    $abigail->posts()->withTrashed()->restore();

    $abigail = $abigail->fresh();

    expect($abigail->posts)->toHaveCount(2);

    // force delete
    $abigail->posts()->where('title', 'Second Title')->forceDelete();

    $abigail = $abigail->fresh();

    expect($abigail->posts)->toHaveCount(1)
        ->and($abigail->posts()->withTrashed()->get())->toHaveCount(1);
});

test('relation to sql applies soft delete', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

    expect($abigail->posts()->toSql())->toBe(
        'select * from "posts" where "posts"."user_id" = ? and "posts"."user_id" is not null and "posts"."deleted_at" is null'
    );
});

test('relation exists and doesnt exist honors soft delete', function () {
    dbSoftDeletesCreateUsers();
    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

    // 'exists' should return true before soft delete
    $abigail->posts()->create(['title' => 'First Title']);
    expect($abigail->posts()->exists())->toBeTrue()
        ->and($abigail->posts()->doesntExist())->toBeFalse();

    // 'exists' should return false after soft delete
    $abigail->posts()->first()->delete();
    expect($abigail->posts()->exists())->toBeFalse()
        ->and($abigail->posts()->doesntExist())->toBeTrue();

    // 'exists' should return true after restore
    $abigail->posts()->withTrashed()->restore();
    expect($abigail->posts()->exists())->toBeTrue()
        ->and($abigail->posts()->doesntExist())->toBeFalse();

    // 'exists' should return false after a force delete
    $abigail->posts()->first()->forceDelete();
    expect($abigail->posts()->exists())->toBeFalse()
        ->and($abigail->posts()->doesntExist())->toBeTrue();
});

test('relation count honors soft delete', function () {
    dbSoftDeletesCreateUsers();
    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

    // check count before soft delete
    $abigail->posts()->create(['title' => 'First Title']);
    $abigail->posts()->create(['title' => 'Second Title']);
    expect($abigail->posts()->count())->toEqual(2);

    // check count after soft delete
    $abigail->posts()->where('title', 'Second Title')->delete();
    expect($abigail->posts()->count())->toEqual(1);

    // check count after restore
    $abigail->posts()->withTrashed()->restore();
    expect($abigail->posts()->count())->toEqual(2);

    // check count after a force delete
    $abigail->posts()->where('title', 'Second Title')->forceDelete();
    expect($abigail->posts()->count())->toEqual(1);
});

test('relation aggregates honors soft delete', function () {
    dbSoftDeletesCreateUsers();
    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

    // check aggregates before soft delete
    $abigail->posts()->create(['title' => 'First Title', 'priority' => 2]);
    $abigail->posts()->create(['title' => 'Second Title', 'priority' => 4]);
    $abigail->posts()->create(['title' => 'Third Title', 'priority' => 6]);
    expect($abigail->posts()->min('priority'))->toEqual(2)
        ->and($abigail->posts()->max('priority'))->toEqual(6)
        ->and($abigail->posts()->sum('priority'))->toEqual(12)
        ->and($abigail->posts()->avg('priority'))->toEqual(4);

    // check aggregates after soft delete
    $abigail->posts()->where('title', 'First Title')->delete();
    expect($abigail->posts()->min('priority'))->toEqual(4)
        ->and($abigail->posts()->max('priority'))->toEqual(6)
        ->and($abigail->posts()->sum('priority'))->toEqual(10)
        ->and($abigail->posts()->avg('priority'))->toEqual(5);

    // check aggregates after restore
    $abigail->posts()->withTrashed()->restore();
    expect($abigail->posts()->min('priority'))->toEqual(2)
        ->and($abigail->posts()->max('priority'))->toEqual(6)
        ->and($abigail->posts()->sum('priority'))->toEqual(12)
        ->and($abigail->posts()->avg('priority'))->toEqual(4);

    // check aggregates after a force delete
    $abigail->posts()->where('title', 'Third Title')->forceDelete();
    expect($abigail->posts()->min('priority'))->toEqual(2)
        ->and($abigail->posts()->max('priority'))->toEqual(4)
        ->and($abigail->posts()->sum('priority'))->toEqual(6)
        ->and($abigail->posts()->avg('priority'))->toEqual(3);
});

test('soft delete is applied to new query', function () {
    $query = (new SoftDeletesTestUser)->newQuery();
    expect($query->toSql())->toBe('select * from "users" where "users"."deleted_at" is null');
});

test('second level relationship can be soft deleted', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post = $abigail->posts()->create(['title' => 'First Title']);
    $post->comments()->create(['body' => 'Comment Body']);

    $abigail->posts()->first()->comments()->delete();

    $abigail = $abigail->fresh();

    expect($abigail->posts()->first()->comments)->toHaveCount(0)
        ->and($abigail->posts()->first()->comments()->withTrashed()->get())->toHaveCount(1);
});

test('where has with deleted relationship', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post = $abigail->posts()->create(['title' => 'First Title']);

    $users = SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->has('posts')->get();
    expect($users)->toHaveCount(0);

    $users = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->has('posts')->get();
    expect($users)->toHaveCount(1);

    $users = SoftDeletesTestUser::where('email', 'doesnt@exist.com')->orHas('posts')->get();
    expect($users)->toHaveCount(1);

    $users = SoftDeletesTestUser::whereHas('posts', function ($query) {
        $query->where('title', 'First Title');
    })->get();
    expect($users)->toHaveCount(1);

    $users = SoftDeletesTestUser::whereHas('posts', function ($query) {
        $query->where('title', 'Another Title');
    })->get();
    expect($users)->toHaveCount(0);

    $users = SoftDeletesTestUser::where('email', 'doesnt@exist.com')->orWhereHas('posts', function ($query) {
        $query->where('title', 'First Title');
    })->get();
    expect($users)->toHaveCount(1);

    // With Post Deleted...

    $post->delete();
    $users = SoftDeletesTestUser::has('posts')->get();
    expect($users)->toHaveCount(0);
});

test('where has with nested deleted relationship and only trashed condition', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post = $abigail->posts()->create(['title' => 'First Title']);
    $post->delete();

    $users = SoftDeletesTestUser::has('posts')->get();
    expect($users)->toHaveCount(0);

    $users = SoftDeletesTestUser::whereHas('posts', function ($q) {
        $q->onlyTrashed();
    })->get();
    expect($users)->toHaveCount(1);

    $users = SoftDeletesTestUser::whereHas('posts', function ($q) {
        $q->withTrashed();
    })->get();
    expect($users)->toHaveCount(1);
});

test('where has with nested deleted relationship', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post = $abigail->posts()->create(['title' => 'First Title']);
    $comment = $post->comments()->create(['body' => 'Comment Body']);
    $comment->delete();

    $users = SoftDeletesTestUser::has('posts.comments')->get();
    expect($users)->toHaveCount(0);

    $users = SoftDeletesTestUser::doesntHave('posts.comments')->get();
    expect($users)->toHaveCount(1);
});

test('where doesnt have with nested deleted relationship', function () {
    dbSoftDeletesCreateUsers();

    $users = SoftDeletesTestUser::doesntHave('posts.comments')->get();
    expect($users)->toHaveCount(1);
});

test('where has with nested deleted relationship and with trashed condition', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUserWithTrashedPosts::where('email', 'abigailotwell@gmail.com')->first();
    $post = $abigail->posts()->create(['title' => 'First Title']);
    $post->delete();

    $users = SoftDeletesTestUserWithTrashedPosts::has('posts')->get();
    expect($users)->toHaveCount(1);
});

test('with count with nested deleted relationship and only trashed condition', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post1 = $abigail->posts()->create(['title' => 'First Title']);
    $post1->delete();
    $abigail->posts()->create(['title' => 'Second Title']);
    $abigail->posts()->create(['title' => 'Third Title']);

    $user = SoftDeletesTestUser::withCount('posts')->orderBy('postsCount', 'desc')->first();
    expect($user->posts_count)->toEqual(2);

    $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
        $q->onlyTrashed();
    }])->orderBy('postsCount', 'desc')->first();
    expect($user->posts_count)->toEqual(1);

    $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
        $q->withTrashed();
    }])->orderBy('postsCount', 'desc')->first();
    expect($user->posts_count)->toEqual(3);

    $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
        $q->withTrashed()->where('title', 'First Title');
    }])->orderBy('postsCount', 'desc')->first();
    expect($user->posts_count)->toEqual(1);

    $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
        $q->where('title', 'First Title');
    }])->orderBy('postsCount', 'desc')->first();
    expect($user->posts_count)->toEqual(0);
});

test('or where with soft delete constraint', function () {
    dbSoftDeletesCreateUsers();

    $users = SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->orWhere('email', 'abigailotwell@gmail.com');
    expect($users->pluck('email')->all())->toEqual(['abigailotwell@gmail.com']);
});

test('morph to with trashed', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post1 = $abigail->posts()->create(['title' => 'First Title']);
    $post1->comments()->create([
        'body' => 'Comment Body',
        'owner_type' => SoftDeletesTestUser::class,
        'owner_id' => $abigail->id,
    ]);

    $abigail->delete();

    $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
        $q->withoutGlobalScope(SoftDeletingScope::class);
    }])->first();

    expect($comment->owner->email)->toEqual($abigail->email);

    $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
        $q->withTrashed();
    }])->first();

    expect($comment->owner->email)->toEqual($abigail->email);

    $comment = TestCommentWithoutSoftDelete::with(['owner' => function ($q) {
        $q->withTrashed();
    }])->first();

    expect($comment->owner->email)->toEqual($abigail->email);
});

test('morph to with bad method call', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post1 = $abigail->posts()->create(['title' => 'First Title']);

    $post1->comments()->create([
        'body' => 'Comment Body',
        'owner_type' => SoftDeletesTestUser::class,
        'owner_id' => $abigail->id,
    ]);

    TestCommentWithoutSoftDelete::with(['owner' => function ($q) {
        $q->thisMethodDoesNotExist();
    }])->first();
})->throws(BadMethodCallException::class);

test('morph to with constraints', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post1 = $abigail->posts()->create(['title' => 'First Title']);
    $post1->comments()->create([
        'body' => 'Comment Body',
        'owner_type' => SoftDeletesTestUser::class,
        'owner_id' => $abigail->id,
    ]);

    $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
        $q->where('email', 'taylorotwell@gmail.com');
    }])->first();

    expect($comment->owner)->toBeNull();
});

test('morph to without constraints', function () {
    dbSoftDeletesCreateUsers();

    $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    $post1 = $abigail->posts()->create(['title' => 'First Title']);
    $post1->comments()->create([
        'body' => 'Comment Body',
        'owner_type' => SoftDeletesTestUser::class,
        'owner_id' => $abigail->id,
    ]);

    $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

    expect($comment->owner->email)->toEqual($abigail->email);

    $abigail->delete();
    $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

    expect($comment->owner)->toBeNull();
});

test('morph to non soft deleting model', function () {
    $taylor = TestUserWithoutSoftDelete::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
    $post1 = $taylor->posts()->create(['title' => 'First Title']);
    $post1->comments()->create([
        'body' => 'Comment Body',
        'owner_type' => TestUserWithoutSoftDelete::class,
        'owner_id' => $taylor->id,
    ]);

    $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

    expect($comment->owner->email)->toEqual($taylor->email);

    $taylor->delete();
    $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

    expect($comment->owner)->toBeNull();
});

test('self referencing relationship with soft deletes', function () {
    // https://github.com/laravel/framework/issues/42075
    [$taylor, $abigail] = dbSoftDeletesCreateUsers();

    expect($abigail->self_referencing)->toHaveCount(1)
        ->and($abigail->self_referencing->first()->is($taylor))->toBeTrue()
        ->and($taylor->self_referencing)->toHaveCount(0)
        ->and(SoftDeletesTestUser::whereHas('self_referencing')->count())->toEqual(1);
});

/**
 * Instrument Models...
 */
class TestUserWithoutSoftDelete extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }
}

/**
 * Instrument Models...
 */
class SoftDeletesTestUser extends Instrument
{
    use SoftDeletes;

    protected $table = 'users';
    protected $guarded = [];

    public function self_referencing()
    {
        return $this->hasMany(SoftDeletesTestUser::class, 'user_id')->onlyTrashed();
    }

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }

    public function address()
    {
        return $this->hasOne(SoftDeletesTestAddress::class, 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(SoftDeletesTestGroup::class, 'group_id');
    }
}

class SoftDeletesTestUserWithTrashedPosts extends Instrument
{
    use SoftDeletes;

    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id')->withTrashed();
    }
}

/**
 * Instrument Models...
 */
class SoftDeletesTestPost extends Instrument
{
    use SoftDeletes;

    protected $table = 'posts';
    protected $guarded = [];

    public function comments()
    {
        return $this->hasMany(SoftDeletesTestComment::class, 'post_id');
    }
}

/**
 * Instrument Models...
 */
class TestCommentWithoutSoftDelete extends Instrument
{
    protected $table = 'comments';
    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

/**
 * Instrument Models...
 */
class SoftDeletesTestComment extends Instrument
{
    use SoftDeletes;

    protected $table = 'comments';
    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

class SoftDeletesTestCommentWithTrashed extends Instrument
{
    use SoftDeletes;

    protected $table = 'comments';
    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

/**
 * Instrument Models...
 */
class SoftDeletesTestAddress extends Instrument
{
    use SoftDeletes;

    protected $table = 'addresses';
    protected $guarded = [];
}

/**
 * Instrument Models...
 */
class SoftDeletesTestGroup extends Instrument
{
    use SoftDeletes;

    protected $table = 'groups';
    protected $guarded = [];

    public function users()
    {
        $this->hasMany(SoftDeletesTestUser::class);
    }
}
