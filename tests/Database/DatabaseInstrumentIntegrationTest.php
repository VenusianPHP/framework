<?php

namespace Tests\Database;

use DateTimeInterface;
use Exception;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Concerns\HasUuids;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\Instrument\Relations\MorphPivot;
use Voyager\Database\Instrument\Relations\Pivot;
use Voyager\Database\Instrument\Relations\Relation;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\Database\Instrument\SoftDeletingScope;
use Voyager\Database\QueryException;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\UniqueConstraintViolationException;
use Voyager\Pagination\AbstractPaginator as Paginator;
use Voyager\Pagination\Cursor;
use Voyager\Pagination\CursorPaginator;
use Voyager\Pagination\LengthAwarePaginator;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\MagicAliases\Date;
use Voyager\NutsAndBolts\DataObjects\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseInstrumentIntegrationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Setup the database schema.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ], 'second_connection');

        $db->bootInstrument();
        $db->setAsGlobal();

        $this->createSchema();
    }

    protected function createSchema()
    {
        $this->schema('default')->create('test_orders', function ($table) {
            $table->increments('id');
            $table->string('item_type');
            $table->integer('item_id');
            $table->timestamps();
        });

        $this->schema('default')->create('with_json', function ($table) {
            $table->increments('id');
            $table->text('json')->default(json_encode([]));
        });

        $this->schema('second_connection')->create('test_items', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        $this->schema('default')->create('users_with_space_in_column_name', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email address');
            $table->timestamps();
        });

        $this->schema()->create('users_having_uuids', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->tinyInteger('role');
            $table->string('role_string');
        });

        foreach (['default', 'second_connection'] as $connection) {
            $this->schema($connection)->create('users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email');
                $table->timestamp('birthday', 6)->nullable();
                $table->timestamps();
            });

            $this->schema($connection)->create('unique_users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                // Unique constraint will be applied only for non-null values
                $table->string('screen_name')->nullable()->unique();
                $table->string('email')->unique();
                $table->timestamp('birthday', 6)->nullable();
                $table->timestamps();
            });

            $this->schema($connection)->create('friends', function ($table) {
                $table->integer('user_id');
                $table->integer('friend_id');
                $table->integer('friend_level_id')->nullable();
            });

            $this->schema($connection)->create('posts', function ($table) {
                $table->increments('id');
                $table->integer('user_id');
                $table->integer('parent_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });

            $this->schema($connection)->create('comments', function ($table) {
                $table->increments('id');
                $table->integer('post_id');
                $table->string('content');
                $table->timestamps();
            });

            $this->schema($connection)->create('friend_levels', function ($table) {
                $table->increments('id');
                $table->string('level');
                $table->timestamps();
            });

            $this->schema($connection)->create('photos', function ($table) {
                $table->increments('id');
                $table->morphs('imageable');
                $table->string('name');
                $table->timestamps();
            });

            $this->schema($connection)->create('soft_deleted_users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email');
                $table->timestamps();
                $table->softDeletes();
            });

            $this->schema($connection)->create('tags', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });

            $this->schema($connection)->create('taggables', function ($table) {
                $table->integer('tag_id');
                $table->morphs('taggable');
                $table->string('taxonomy')->nullable();
            });

            $this->schema($connection)->create('categories', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('parent_id')->nullable();
                $table->timestamps();
            });

            $this->schema($connection)->create('achievements', function ($table) {
                $table->increments('id');
                $table->integer('status')->nullable();
            });

            $this->schema($connection)->create('instrument_test_achievement_instrument_test_user', function ($table) {
                $table->integer('instrument_test_achievement_id');
                $table->integer('instrument_test_user_id');
            });
        }

        $this->schema($connection)->create('non_incrementing_users', function ($table) {
            $table->string('name')->nullable();
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (['default', 'second_connection'] as $connection) {
            $this->schema($connection)->drop('users');
            $this->schema($connection)->drop('friends');
            $this->schema($connection)->drop('posts');
            $this->schema($connection)->drop('friend_levels');
            $this->schema($connection)->drop('photos');
        }

        Relation::morphMap([], false);
        Instrument::unsetConnectionResolver();

        Carbon::setTestNow(null);
        Str::createUuidsNormally();
        DB::flushQueryLog();

        parent::tearDown();
    }

    /**
     * Tests...
     */
    public function testBasicModelRetrieval()
    {
        InstrumentTestUser::insert([['id' => 1, 'email' => 'taylorotwell@gmail.com'], ['id' => 2, 'email' => 'abigailotwell@gmail.com']]);

        $this->assertEquals(2, InstrumentTestUser::count());

        $this->assertFalse(InstrumentTestUser::where('email', 'taylorotwell@gmail.com')->doesntExist());
        $this->assertTrue(InstrumentTestUser::where('email', 'mohamed@laravel.com')->doesntExist());

        $model = InstrumentTestUser::where('email', 'taylorotwell@gmail.com')->first();
        $this->assertSame('taylorotwell@gmail.com', $model->email);
        $this->assertTrue(isset($model->email));
        $this->assertTrue(isset($model->friends));

        $model = InstrumentTestUser::find(1);
        $this->assertInstanceOf(InstrumentTestUser::class, $model);
        $this->assertEquals(1, $model->id);

        $model = InstrumentTestUser::find(2);
        $this->assertInstanceOf(InstrumentTestUser::class, $model);
        $this->assertEquals(2, $model->id);

        $missing = InstrumentTestUser::find(3);
        $this->assertNull($missing);

        $collection = InstrumentTestUser::find([]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(0, $collection);

        $collection = InstrumentTestUser::find([1, 2, 3]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);

        $models = InstrumentTestUser::where('id', 1)->cursor();
        foreach ($models as $model) {
            $this->assertEquals(1, $model->id);
            $this->assertSame('default', $model->getConnectionName());
        }

        $records = DB::table('users')->where('id', 1)->cursor();
        foreach ($records as $record) {
            $this->assertEquals(1, $record->id);
        }

        $records = DB::cursor('select * from users where id = ?', [1]);
        foreach ($records as $record) {
            $this->assertEquals(1, $record->id);
        }
    }

    public function testBasicModelCollectionRetrieval()
    {
        InstrumentTestUser::insert([['id' => 1, 'email' => 'taylorotwell@gmail.com'], ['id' => 2, 'email' => 'abigailotwell@gmail.com']]);

        $models = InstrumentTestUser::oldest('id')->get();

        $this->assertCount(2, $models);
        $this->assertInstanceOf(Collection::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
    }

    public function testPaginatedModelCollectionRetrieval()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(2);

        $this->assertCount(2, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(2);

        $this->assertCount(1, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertSame('foo@gmail.com', $models[0]->email);
    }

    public function testPaginatedModelCollectionRetrievalUsingCallablePerPage()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(3, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[2]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
        $this->assertSame('foo@gmail.com', $models[2]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(0, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);

        InstrumentTestUser::create(['id' => 4, 'email' => 'bar@gmail.com']);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(2, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(2, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertSame('foo@gmail.com', $models[0]->email);
        $this->assertSame('bar@gmail.com', $models[1]->email);
    }

    public function testPaginatedModelCollectionRetrievalWhenNoElements()
    {
        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(2);

        $this->assertCount(0, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = InstrumentTestUser::oldest('id')->paginate(2);

        $this->assertCount(0, $models);
    }

    public function testPaginatedModelCollectionRetrievalWhenNoElementsAndDefaultPerPage()
    {
        $models = InstrumentTestUser::oldest('id')->paginate();

        $this->assertCount(0, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
    }

    public function testCountForPaginationWithGrouping()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
            ['id' => 4, 'email' => 'foo@gmail.com'],
        ]);

        $query = InstrumentTestUser::groupBy('email')->getQuery();

        $this->assertEquals(3, $query->getCountForPagination());
    }

    public function testCountForPaginationWithGroupingAndSubSelects()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
            ['id' => 4, 'email' => 'foo@gmail.com'],
        ]);
        $user1 = InstrumentTestUser::find(1);

        $user1->friends()->create(['id' => 5, 'email' => 'friend@gmail.com']);

        $query = InstrumentTestUser::select([
            'id',
            'friends_count' => InstrumentTestUser::whereColumn('friend_id', 'user_id')->count(),
        ])->groupBy('email')->getQuery();

        $this->assertEquals(4, $query->getCountForPagination());
    }

    public function testCursorPaginatedModelCollectionRetrieval()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            $secondParams = ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        CursorPaginator::currentCursorResolver(function () {
            return null;
        });
        $models = InstrumentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(2, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
        $this->assertTrue($models->hasMorePages());
        $this->assertTrue($models->hasPages());

        CursorPaginator::currentCursorResolver(function () use ($secondParams) {
            return new Cursor($secondParams);
        });
        $models = InstrumentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(1, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertSame('foo@gmail.com', $models[0]->email);
        $this->assertFalse($models->hasMorePages());
        $this->assertTrue($models->hasPages());
    }

    public function testPreviousCursorPaginatedModelCollectionRetrieval()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            $thirdParams = ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        CursorPaginator::currentCursorResolver(function () use ($thirdParams) {
            return new Cursor($thirdParams, false);
        });
        $models = InstrumentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(2, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
        $this->assertTrue($models->hasMorePages());
        $this->assertTrue($models->hasPages());
    }

    public function testCursorPaginatedModelCollectionRetrievalWhenNoElements()
    {
        CursorPaginator::currentCursorResolver(function () {
            return null;
        });
        $models = InstrumentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(0, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);

        Paginator::currentPageResolver(function () {
            return new Cursor(['id' => 1]);
        });
        $models = InstrumentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(0, $models);
    }

    public function testCursorPaginatedModelCollectionRetrievalWhenNoElementsAndDefaultPerPage()
    {
        $models = InstrumentTestUser::oldest('id')->cursorPaginate();

        $this->assertCount(0, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
    }

    public function testFirstOrNew()
    {
        $user1 = InstrumentTestUser::firstOrNew(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro']
        );

        $this->assertSame('Nuno Maduro', $user1->name);
    }

    public function testFirstOrCreate()
    {
        $user1 = InstrumentTestUser::firstOrCreate(['email' => 'taylorotwell@gmail.com']);

        $this->assertSame('taylorotwell@gmail.com', $user1->email);
        $this->assertNull($user1->name);

        $user2 = InstrumentTestUser::firstOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertNull($user2->name);

        $user3 = InstrumentTestUser::firstOrCreate(
            ['email' => 'abigailotwell@gmail.com'],
            ['name' => 'Abigail Otwell']
        );

        $this->assertNotEquals($user3->id, $user1->id);
        $this->assertSame('abigailotwell@gmail.com', $user3->email);
        $this->assertSame('Abigail Otwell', $user3->name);

        $user4 = InstrumentTestUser::firstOrCreate(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro', 'email' => 'nuno@laravel.com']
        );

        $this->assertSame('Nuno Maduro', $user4->name);
    }

    public function testCreateOrFirst()
    {
        $user1 = InstrumentTestUniqueUser::createOrFirst(['email' => 'taylorotwell@gmail.com']);

        $this->assertSame('taylorotwell@gmail.com', $user1->email);
        $this->assertNull($user1->name);

        $user2 = InstrumentTestUniqueUser::createOrFirst(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertNull($user2->name);

        $user3 = InstrumentTestUniqueUser::createOrFirst(
            ['email' => 'abigailotwell@gmail.com'],
            ['name' => 'Abigail Otwell']
        );

        $this->assertNotEquals($user3->id, $user1->id);
        $this->assertSame('abigailotwell@gmail.com', $user3->email);
        $this->assertSame('Abigail Otwell', $user3->name);

        $user4 = InstrumentTestUniqueUser::createOrFirst(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro', 'email' => 'nuno@laravel.com']
        );

        $this->assertSame('Nuno Maduro', $user4->name);
    }

    public function testCreateOrFirstNonAttributeFieldViolation()
    {
        // 'email' and 'screen_name' are unique and independent of each other.
        InstrumentTestUniqueUser::create([
            'email' => 'taylorotwell+foo@gmail.com',
            'screen_name' => '@taylorotwell',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Although 'email' is expected to be unique and is passed as $attributes,
        // if the 'screen_name' attribute listed in non-unique $values causes a violation,
        // a UniqueConstraintViolationException should be thrown.
        InstrumentTestUniqueUser::createOrFirst(
            ['email' => 'taylorotwell+bar@gmail.com'],
            [
                'screen_name' => '@taylorotwell',
            ]
        );
    }

    public function testCreateOrFirstWithinTransaction()
    {
        $user1 = InstrumentTestUniqueUser::create(['email' => 'taylorotwell@gmail.com']);

        DB::transaction(function () use ($user1) {
            $user2 = InstrumentTestUniqueUser::createOrFirst(
                ['email' => 'taylorotwell@gmail.com'],
                ['name' => 'Taylor Otwell']
            );

            $this->assertEquals($user1->id, $user2->id);
            $this->assertSame('taylorotwell@gmail.com', $user2->email);
            $this->assertNull($user2->name);
        });
    }

    public function testUpdateOrCreate()
    {
        $user1 = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        $user2 = InstrumentTestUser::updateOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertSame('Taylor Otwell', $user2->name);

        $user3 = InstrumentTestUser::updateOrCreate(
            ['email' => 'themsaid@gmail.com'],
            ['name' => 'Mohamed Said']
        );

        $this->assertSame('Mohamed Said', $user3->name);
        $this->assertEquals(2, InstrumentTestUser::count());
    }

    public function testUpdateOrCreateOnDifferentConnection()
    {
        InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        InstrumentTestUser::on('second_connection')->updateOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        InstrumentTestUser::on('second_connection')->updateOrCreate(
            ['email' => 'themsaid@gmail.com'],
            ['name' => 'Mohamed Said']
        );

        $this->assertEquals(1, InstrumentTestUser::count());
        $this->assertEquals(2, InstrumentTestUser::on('second_connection')->count());
    }

    public function testCheckAndCreateMethodsOnMultiConnections()
    {
        InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        InstrumentTestUser::on('second_connection')->find(
            InstrumentTestUser::on('second_connection')->insert(['id' => 2, 'email' => 'themsaid@gmail.com'])
        );

        $user1 = InstrumentTestUser::on('second_connection')->findOrNew(1);
        $user2 = InstrumentTestUser::on('second_connection')->findOrNew(2);
        $this->assertFalse($user1->exists);
        $this->assertTrue($user2->exists);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());

        $user1 = InstrumentTestUser::on('second_connection')->firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $user2 = InstrumentTestUser::on('second_connection')->firstOrNew(['email' => 'themsaid@gmail.com']);
        $this->assertFalse($user1->exists);
        $this->assertTrue($user2->exists);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());

        $this->assertEquals(1, InstrumentTestUser::on('second_connection')->count());
        $user1 = InstrumentTestUser::on('second_connection')->firstOrCreate(['email' => 'taylorotwell@gmail.com']);
        $user2 = InstrumentTestUser::on('second_connection')->firstOrCreate(['email' => 'themsaid@gmail.com']);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());
        $this->assertEquals(2, InstrumentTestUser::on('second_connection')->count());
    }

    public function testCreatingModelWithEmptyAttributes()
    {
        $model = InstrumentTestNonIncrementing::create([]);

        $this->assertFalse($model->exists);
        $this->assertFalse($model->wasRecentlyCreated);
    }

    public function testChunk()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->orderBy('id', 'asc')->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } else {
                $this->assertCount(1, $users);
                $this->assertSame('Third', $users[0]->name);
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function testChunksWithLimitsWhereLimitIsLessThanTotal()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->orderBy('id', 'asc')->limit(2)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function testChunksWithLimitsWhereLimitIsMoreThanTotal()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->orderBy('id', 'asc')->limit(10)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } elseif ($page === 2) {
                $this->assertCount(1, $users);
                $this->assertSame('Third', $users[0]->name);
            } else {
                $this->fail('Should have had two pages.');
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function testChunksWithOffset()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->orderBy('id', 'asc')->offset(1)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Second', $users[0]->name);
                $this->assertSame('Third', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function testChunksWithOffsetWhereMoreThanTotal()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->orderBy('id', 'asc')->offset(3)->chunk(2, function () use (&$chunks) {
            $chunks++;
        });

        $this->assertEquals(0, $chunks);
    }

    public function testChunksWithLimitsAndOffsets()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
            ['name' => 'Fourth', 'email' => 'fourth@example.com'],
            ['name' => 'Fifth', 'email' => 'fifth@example.com'],
            ['name' => 'Sixth', 'email' => 'sixth@example.com'],
            ['name' => 'Seventh', 'email' => 'seventh@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->orderBy('id', 'asc')->offset(2)->limit(3)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Third', $users[0]->name);
                $this->assertSame('Fourth', $users[1]->name);
            } elseif ($page == 2) {
                $this->assertCount(1, $users);
                $this->assertSame('Fifth', $users[0]->name);
            } else {
                $this->fail('Should only have had two pages.');
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function testChunkByIdWithLimits()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->limit(2)->chunkById(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function testChunkByIdWithOffsets()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->offset(1)->chunkById(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Second', $users[0]->name);
                $this->assertSame('Third', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function testChunkByIdWithLimitsAndOffsets()
    {
        InstrumentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
            ['name' => 'Fourth', 'email' => 'fourth@example.com'],
            ['name' => 'Fifth', 'email' => 'fifth@example.com'],
            ['name' => 'Sixth', 'email' => 'sixth@example.com'],
            ['name' => 'Seventh', 'email' => 'seventh@example.com'],
        ]);

        $chunks = 0;

        InstrumentTestUser::query()->offset(2)->limit(3)->chunkById(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Third', $users[0]->name);
                $this->assertSame('Fourth', $users[1]->name);
            } elseif ($page == 2) {
                $this->assertCount(1, $users);
                $this->assertSame('Fifth', $users[0]->name);
            } else {
                $this->fail('Should only have had two pages.');
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function testChunkByIdWithNonIncrementingKey()
    {
        InstrumentTestNonIncrementingSecond::insert([
            ['name' => ' First'],
            ['name' => ' Second'],
            ['name' => ' Third'],
        ]);

        $i = 0;
        InstrumentTestNonIncrementingSecond::query()->chunkById(2, function (Collection $users) use (&$i) {
            if (! $i) {
                $this->assertSame(' First', $users[0]->name);
                $this->assertSame(' Second', $users[1]->name);
            } else {
                $this->assertSame(' Third', $users[0]->name);
            }
            $i++;
        }, 'name');
        $this->assertEquals(2, $i);
    }

    public function testEachByIdWithNonIncrementingKey()
    {
        InstrumentTestNonIncrementingSecond::insert([
            ['name' => ' First'],
            ['name' => ' Second'],
            ['name' => ' Third'],
        ]);

        $users = [];
        InstrumentTestNonIncrementingSecond::query()->eachById(
            function (InstrumentTestNonIncrementingSecond $user, $i) use (&$users) {
                $users[] = [$user->name, $i];
            }, 2, 'name');
        $this->assertSame([[' First', 0], [' Second', 1], [' Third', 2]], $users);
    }

    public function testPluck()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $simple = InstrumentTestUser::oldest('id')->pluck('users.email')->all();
        $keyed = InstrumentTestUser::oldest('id')->pluck('users.email', 'users.id')->all();

        $this->assertEquals(['taylorotwell@gmail.com', 'abigailotwell@gmail.com'], $simple);
        $this->assertEquals([1 => 'taylorotwell@gmail.com', 2 => 'abigailotwell@gmail.com'], $keyed);
    }

    public function testPluckWithJoin()
    {
        $user1 = InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user2 = InstrumentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $user2->posts()->create(['id' => 1, 'name' => 'First post']);
        $user1->posts()->create(['id' => 2, 'name' => 'Second post']);

        $query = InstrumentTestUser::join('posts', 'users.id', '=', 'posts.user_id');

        $this->assertEquals([1 => 'First post', 2 => 'Second post'], $query->pluck('posts.name', 'posts.id')->all());
        $this->assertEquals([2 => 'First post', 1 => 'Second post'], $query->pluck('posts.name', 'users.id')->all());
        $this->assertEquals(['abigailotwell@gmail.com' => 'First post', 'taylorotwell@gmail.com' => 'Second post'], $query->pluck('posts.name', 'users.email AS user_email')->all());
    }

    public function testPluckWithColumnNameContainingASpace()
    {
        InstrumentTestUserWithSpaceInColumnName::insert([
            ['id' => 1, 'email address' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email address' => 'abigailotwell@gmail.com'],
        ]);

        $simple = InstrumentTestUserWithSpaceInColumnName::oldest('id')->pluck('users_with_space_in_column_name.email address')->all();
        $keyed = InstrumentTestUserWithSpaceInColumnName::oldest('id')->pluck('email address', 'id')->all();

        $this->assertEquals(['taylorotwell@gmail.com', 'abigailotwell@gmail.com'], $simple);
        $this->assertEquals([1 => 'taylorotwell@gmail.com', 2 => 'abigailotwell@gmail.com'], $keyed);
    }

    public function testFindOrFail()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $single = InstrumentTestUser::findOrFail(1);
        $multiple = InstrumentTestUser::findOrFail([1, 2]);

        $this->assertInstanceOf(InstrumentTestUser::class, $single);
        $this->assertSame('taylorotwell@gmail.com', $single->email);
        $this->assertInstanceOf(Collection::class, $multiple);
        $this->assertInstanceOf(InstrumentTestUser::class, $multiple[0]);
        $this->assertInstanceOf(InstrumentTestUser::class, $multiple[1]);
    }

    public function testFindOrFailWithSingleIdThrowsModelNotFoundException()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [Tests\Database\InstrumentTestUser] 1');
        $this->expectExceptionObject(
            (new ModelNotFoundException())->setModel(InstrumentTestUser::class, [1]),
        );

        InstrumentTestUser::findOrFail(1);
    }

    public function testFindOrFailWithMultipleIdsThrowsModelNotFoundException()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [Tests\Database\InstrumentTestUser] 2, 3');
        $this->expectExceptionObject(
            (new ModelNotFoundException())->setModel(InstrumentTestUser::class, [2, 3]),
        );

        InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        InstrumentTestUser::findOrFail([1, 2, 3]);
    }

    public function testFindOrFailWithMultipleIdsUsingCollectionThrowsModelNotFoundException()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [Tests\Database\InstrumentTestUser] 2, 3');
        $this->expectExceptionObject(
            (new ModelNotFoundException())->setModel(InstrumentTestUser::class, [2, 3]),
        );

        InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        InstrumentTestUser::findOrFail(new Collection([1, 1, 2, 3]));
    }

    public function testOneToOneRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->post()->create(['name' => 'First Post']);

        $post = $user->post;
        $user = $post->user;

        $this->assertTrue(isset($user->post->name));
        $this->assertInstanceOf(InstrumentTestUser::class, $user);
        $this->assertInstanceOf(InstrumentTestPost::class, $post);
        $this->assertSame('taylorotwell@gmail.com', $user->email);
        $this->assertSame('First Post', $post->name);
    }

    public function testIssetLoadsInRelationshipIfItIsntLoadedAlready()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->post()->create(['name' => 'First Post']);

        $this->assertTrue(isset($user->post->name));
    }

    public function testOneToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'First Post']);
        $user->posts()->create(['name' => 'Second Post']);

        $posts = $user->posts;
        $post2 = $user->posts()->where('name', 'Second Post')->first();

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);
        $this->assertInstanceOf(InstrumentTestPost::class, $posts[0]);
        $this->assertInstanceOf(InstrumentTestPost::class, $posts[1]);
        $this->assertInstanceOf(InstrumentTestPost::class, $post2);
        $this->assertSame('Second Post', $post2->name);
        $this->assertInstanceOf(InstrumentTestUser::class, $post2->user);
        $this->assertSame('taylorotwell@gmail.com', $post2->user->email);
    }

    public function testBasicModelHydration()
    {
        $user = new InstrumentTestUser(['email' => 'taylorotwell@gmail.com']);
        $user->setConnection('second_connection');
        $user->save();

        $user = new InstrumentTestUser(['email' => 'abigailotwell@gmail.com']);
        $user->setConnection('second_connection');
        $user->save();

        $models = InstrumentTestUser::on('second_connection')->fromQuery('SELECT * FROM users WHERE email = ?', ['abigailotwell@gmail.com']);

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
        $this->assertSame('abigailotwell@gmail.com', $models[0]->email);
        $this->assertSame('second_connection', $models[0]->getConnectionName());
        $this->assertCount(1, $models);
    }

    public function testFirstOrNewOnHasOneRelationShip()
    {
        $user1 = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post1 = $user1->post()->firstOrNew(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('New Post', $post1->name);

        $user2 = InstrumentTestUser::create(['email' => 'abigailotwell@gmail.com']);
        $post = $user2->post()->create(['name' => 'First Post']);
        $post2 = $user2->post()->firstOrNew(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('First Post', $post2->name);
        $this->assertSame($post->id, $post2->id);
    }

    public function testFirstOrCreateOnHasOneRelationShip()
    {
        $user1 = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post1 = $user1->post()->firstOrCreate(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('New Post', $post1->name);

        $user2 = InstrumentTestUser::create(['email' => 'abigailotwell@gmail.com']);
        $post = $user2->post()->create(['name' => 'First Post']);
        $post2 = $user2->post()->firstOrCreate(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('First Post', $post2->name);
        $this->assertSame($post->id, $post2->id);
    }

    public function testHasOnSelfReferencingBelongsToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $this->assertTrue(isset($user->friends[0]->id));

        $results = InstrumentTestUser::has('friends')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testWhereHasOnSelfReferencingBelongsToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = InstrumentTestUser::whereHas('friends', function ($query) {
            $query->where('email', 'abigailotwell@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testWithWhereHasOnSelfReferencingBelongsToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = InstrumentTestUser::withWhereHas('friends', function ($query) {
            $query->where('email', 'abigailotwell@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
        $this->assertTrue($results->first()->relationLoaded('friends'));
        $this->assertSame($results->first()->friends->pluck('email')->unique()->toArray(), ['abigailotwell@gmail.com']);
    }

    public function testHasOnNestedSelfReferencingBelongsToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = InstrumentTestUser::has('friends.friends')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testWhereHasOnNestedSelfReferencingBelongsToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = InstrumentTestUser::whereHas('friends.friends', function ($query) {
            $query->where('email', 'foo@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testWithWhereHasOnNestedSelfReferencingBelongsToManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = InstrumentTestUser::withWhereHas('friends.friends', function ($query) {
            $query->where('email', 'foo@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
        $this->assertTrue($results->first()->relationLoaded('friends'));
        $this->assertSame($results->first()->friends->pluck('email')->unique()->toArray(), ['abigailotwell@gmail.com']);
        $this->assertSame($results->first()->friends->pluck('friends')->flatten()->pluck('email')->unique()->toArray(), ['foo@gmail.com']);
    }

    public function testHasOnSelfReferencingBelongsToManyRelationshipWithWherePivot()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = InstrumentTestUser::has('friendsOne')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testHasOnNestedSelfReferencingBelongsToManyRelationshipWithWherePivot()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = InstrumentTestUser::has('friendsOne.friendsTwo')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testHasOnSelfReferencingBelongsToRelationship()
    {
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = InstrumentTestPost::has('parentPost')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function testAggregatedValuesOfDatetimeField()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'test1@test.test', 'created_at' => '2016-08-10 09:21:00', 'updated_at' => Carbon::now()],
            ['id' => 2, 'email' => 'test2@test.test', 'created_at' => '2016-08-01 12:00:00', 'updated_at' => Carbon::now()],
        ]);

        $this->assertSame('2016-08-10 09:21:00', InstrumentTestUser::max('created_at'));
        $this->assertSame('2016-08-01 12:00:00', InstrumentTestUser::min('created_at'));
    }

    public function testWhereHasOnSelfReferencingBelongsToRelationship()
    {
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = InstrumentTestPost::whereHas('parentPost', function ($query) {
            $query->where('name', 'Parent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function testWithWhereHasOnSelfReferencingBelongsToRelationship()
    {
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = InstrumentTestPost::withWhereHas('parentPost', function ($query) {
            $query->where('name', 'Parent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->name, 'Parent Post');
    }

    public function testHasOnNestedSelfReferencingBelongsToRelationship()
    {
        $grandParentPost = InstrumentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = InstrumentTestPost::has('parentPost.parentPost')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function testWhereHasOnNestedSelfReferencingBelongsToRelationship()
    {
        $grandParentPost = InstrumentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = InstrumentTestPost::whereHas('parentPost.parentPost', function ($query) {
            $query->where('name', 'Grandparent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function testWithWhereHasOnNestedSelfReferencingBelongsToRelationship()
    {
        $grandParentPost = InstrumentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = InstrumentTestPost::withWhereHas('parentPost.parentPost', function ($query) {
            $query->where('name', 'Grandparent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->name, 'Parent Post');
        $this->assertTrue($results->first()->parentPost->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->parentPost->name, 'Grandparent Post');
    }

    public function testHasOnSelfReferencingHasManyRelationship()
    {
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = InstrumentTestPost::has('childPosts')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
    }

    public function testWhereHasOnSelfReferencingHasManyRelationship()
    {
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = InstrumentTestPost::whereHas('childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
    }

    public function testWithWhereHasOnSelfReferencingHasManyRelationship()
    {
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = InstrumentTestPost::withWhereHas('childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('childPosts'));
        $this->assertSame($results->first()->childPosts->pluck('name')->unique()->toArray(), ['Child Post']);
    }

    public function testHasOnNestedSelfReferencingHasManyRelationship()
    {
        $grandParentPost = InstrumentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = InstrumentTestPost::has('childPosts.childPosts')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
    }

    public function testWhereHasOnNestedSelfReferencingHasManyRelationship()
    {
        $grandParentPost = InstrumentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = InstrumentTestPost::whereHas('childPosts.childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
    }

    public function testWithWhereHasOnNestedSelfReferencingHasManyRelationship()
    {
        $grandParentPost = InstrumentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = InstrumentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        InstrumentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = InstrumentTestPost::withWhereHas('childPosts.childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('childPosts'));
        $this->assertSame($results->first()->childPosts->pluck('name')->unique()->toArray(), ['Parent Post']);
        $this->assertSame($results->first()->childPosts->pluck('childPosts')->flatten()->pluck('name')->unique()->toArray(), ['Child Post']);
    }

    public function testHasWithNonWhereBindings()
    {
        $user = InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $user->posts()->create(['name' => 'Post 2'])
            ->photos()->create(['name' => 'photo.jpg']);

        $query = InstrumentTestUser::has('postWithPhotos');

        $bindingsCount = count($query->getBindings());
        $questionMarksCount = substr_count($query->toSql(), '?');

        $this->assertEquals($questionMarksCount, $bindingsCount);
    }

    public function testHasOnMorphToRelationship()
    {
        $post = InstrumentTestPost::create(['name' => 'Morph Post', 'user_id' => 1]);
        (new InstrumentTestPhoto)->imageable()->associate($post)->fill(['name' => 'Morph Photo'])->save();

        $photos = InstrumentTestPhoto::has('imageable')->get();

        $this->assertEquals(1, $photos->count());
    }

    public function testBelongsToManyRelationshipModelsAreProperlyHydratedWithSoleQuery()
    {
        $user = InstrumentTestUserWithCustomFriendPivot::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $user->friends()->get()->each(function ($friend) {
            $this->assertInstanceOf(InstrumentTestFriendPivot::class, $friend->pivot);
        });

        $soleFriend = $user->friends()->where('email', 'abigailotwell@gmail.com')->sole();

        $this->assertInstanceOf(InstrumentTestFriendPivot::class, $soleFriend->pivot);
    }

    public function testBelongsToManyRelationshipMissingModelExceptionWithSoleQueryWorks()
    {
        $this->expectException(ModelNotFoundException::class);
        $user = InstrumentTestUserWithCustomFriendPivot::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->where('email', 'abigailotwell@gmail.com')->sole();
    }

    public function testBelongsToManyRelationshipModelsAreProperlyHydratedOverChunkedRequest()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        InstrumentTestUser::first()->friends()->chunk(2, function ($friends) use ($user, $friend) {
            $this->assertCount(1, $friends);
            $this->assertSame('abigailotwell@gmail.com', $friends->first()->email);
            $this->assertEquals($user->id, $friends->first()->pivot->user_id);
            $this->assertEquals($friend->id, $friends->first()->pivot->friend_id);
        });
    }

    public function testBelongsToManyRelationshipModelsAreProperlyHydratedOverEachRequest()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        InstrumentTestUser::first()->friends()->each(function ($result) use ($user, $friend) {
            $this->assertSame('abigailotwell@gmail.com', $result->email);
            $this->assertEquals($user->id, $result->pivot->user_id);
            $this->assertEquals($friend->id, $result->pivot->friend_id);
        });
    }

    public function testBelongsToManyRelationshipModelsAreProperlyHydratedOverCursorRequest()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        foreach (InstrumentTestUser::first()->friends()->cursor() as $result) {
            $this->assertSame('abigailotwell@gmail.com', $result->email);
            $this->assertEquals($user->id, $result->pivot->user_id);
            $this->assertEquals($friend->id, $result->pivot->friend_id);
        }
    }

    public function testWhereAttachedTo()
    {
        InstrumentTestUser::insert([
            ['email' => 'user1@gmail.com'],
            ['email' => 'user2@gmail.com'],
            ['email' => 'user3@gmail.com'],
        ]);

        [$user1, $user2, $user3] = InstrumentTestUser::get();

        InstrumentTestAchievement::fillAndInsert([['status' => 3], [], []]);
        [$achievement1, $achievement2, $achievement3] = InstrumentTestAchievement::get();

        $user1->instrumentTestAchievements()->attach([$achievement1]);
        $user2->instrumentTestAchievements()->attach([$achievement1, $achievement3]);
        $user3->instrumentTestAchievements()->attach([$achievement2, $achievement3]);

        $achievedAchievement1 = InstrumentTestUser::whereAttachedTo($achievement1)->get();

        $this->assertSame(2, $achievedAchievement1->count());
        $this->assertTrue($achievedAchievement1->contains($user1));
        $this->assertTrue($achievedAchievement1->contains($user2));

        $achievedByUser1or2 = InstrumentTestAchievement::whereAttachedTo(
            new Collection([$user1, $user2])
        )->get();

        $this->assertSame(2, $achievedByUser1or2->count());
        $this->assertTrue($achievedByUser1or2->contains($achievement1));
        $this->assertTrue($achievedByUser1or2->contains($achievement3));
    }

    public function testBasicHasManyEagerLoading()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'First Post']);
        $user = InstrumentTestUser::with('posts')->where('email', 'taylorotwell@gmail.com')->first();

        $this->assertSame('First Post', $user->posts->first()->name);

        $post = InstrumentTestPost::with('user')->where('name', 'First Post')->get();
        $this->assertSame('taylorotwell@gmail.com', $post->first()->user->email);
    }

    public function testBasicNestedSelfReferencingHasManyEagerLoading()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->childPosts()->create(['name' => 'Child Post', 'user_id' => $user->id]);

        $user = InstrumentTestUser::with('posts.childPosts')->where('email', 'taylorotwell@gmail.com')->first();

        $this->assertNotNull($user->posts->first());
        $this->assertSame('First Post', $user->posts->first()->name);

        $this->assertNotNull($user->posts->first()->childPosts->first());
        $this->assertSame('Child Post', $user->posts->first()->childPosts->first()->name);

        $post = InstrumentTestPost::with('parentPost.user')->where('name', 'Child Post')->get();
        $this->assertNotNull($post->first()->parentPost);
        $this->assertNotNull($post->first()->parentPost->user);
        $this->assertSame('taylorotwell@gmail.com', $post->first()->parentPost->user->email);
    }

    public function testBasicMorphManyRelationship()
    {
        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);
        $user->photos()->create(['name' => 'Avatar 2']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->photos()->create(['name' => 'Hero 1']);
        $post->photos()->create(['name' => 'Hero 2']);

        $this->assertInstanceOf(Collection::class, $user->photos);
        $this->assertInstanceOf(InstrumentTestPhoto::class, $user->photos[0]);
        $this->assertInstanceOf(Collection::class, $post->photos);
        $this->assertInstanceOf(InstrumentTestPhoto::class, $post->photos[0]);
        $this->assertCount(2, $user->photos);
        $this->assertCount(2, $post->photos);
        $this->assertSame('Avatar 1', $user->photos[0]->name);
        $this->assertSame('Avatar 2', $user->photos[1]->name);
        $this->assertSame('Hero 1', $post->photos[0]->name);
        $this->assertSame('Hero 2', $post->photos[1]->name);

        $photos = InstrumentTestPhoto::orderBy('name')->get();

        $this->assertInstanceOf(Collection::class, $photos);
        $this->assertCount(4, $photos);
        $this->assertInstanceOf(InstrumentTestUser::class, $photos[0]->imageable);
        $this->assertInstanceOf(InstrumentTestPost::class, $photos[2]->imageable);
        $this->assertSame('taylorotwell@gmail.com', $photos[1]->imageable->email);
        $this->assertSame('First Post', $photos[3]->imageable->name);
    }

    public function testMorphMapIsUsedForCreatingAndFetchingThroughRelation()
    {
        Relation::morphMap([
            'user' => InstrumentTestUser::class,
            'post' => InstrumentTestPost::class,
        ]);

        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);
        $user->photos()->create(['name' => 'Avatar 2']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->photos()->create(['name' => 'Hero 1']);
        $post->photos()->create(['name' => 'Hero 2']);

        $this->assertInstanceOf(Collection::class, $user->photos);
        $this->assertInstanceOf(InstrumentTestPhoto::class, $user->photos[0]);
        $this->assertInstanceOf(Collection::class, $post->photos);
        $this->assertInstanceOf(InstrumentTestPhoto::class, $post->photos[0]);
        $this->assertCount(2, $user->photos);
        $this->assertCount(2, $post->photos);
        $this->assertSame('Avatar 1', $user->photos[0]->name);
        $this->assertSame('Avatar 2', $user->photos[1]->name);
        $this->assertSame('Hero 1', $post->photos[0]->name);
        $this->assertSame('Hero 2', $post->photos[1]->name);

        $this->assertSame('user', $user->photos[0]->imageable_type);
        $this->assertSame('user', $user->photos[1]->imageable_type);
        $this->assertSame('post', $post->photos[0]->imageable_type);
        $this->assertSame('post', $post->photos[1]->imageable_type);
    }

    public function testMorphMapIsUsedWhenFetchingParent()
    {
        Relation::morphMap([
            'user' => InstrumentTestUser::class,
            'post' => InstrumentTestPost::class,
        ]);

        $user = InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);

        $photo = InstrumentTestPhoto::first();
        $this->assertSame('user', $photo->imageable_type);
        $this->assertInstanceOf(InstrumentTestUser::class, $photo->imageable);
    }

    public function testMorphMapIsMergedByDefault()
    {
        $map1 = [
            'user' => InstrumentTestUser::class,
        ];
        $map2 = [
            'post' => InstrumentTestPost::class,
        ];

        Relation::morphMap($map1);
        Relation::morphMap($map2);

        $this->assertEquals(array_merge($map1, $map2), Relation::morphMap());
    }

    public function testMorphMapOverwritesCurrentMap()
    {
        $map1 = [
            'user' => InstrumentTestUser::class,
        ];
        $map2 = [
            'post' => InstrumentTestPost::class,
        ];

        Relation::morphMap($map1, false);
        $this->assertEquals($map1, Relation::morphMap());
        Relation::morphMap($map2, false);
        $this->assertEquals($map2, Relation::morphMap());
    }

    public function testEmptyMorphToRelationship()
    {
        $photo = new InstrumentTestPhoto;

        $this->assertNull($photo->imageable);
    }

    public function testSaveOrFail()
    {
        $date = '1970-01-01';
        $post = new InstrumentTestPost([
            'user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date,
        ]);

        $this->assertTrue($post->saveOrFail());
        $this->assertEquals(1, InstrumentTestPost::count());
    }

    public function testSavingJSONFields()
    {
        $model = InstrumentTestWithJSON::create(['json' => ['x' => 0]]);
        $this->assertEquals(['x' => 0], $model->json);

        $model->fillable(['json->y', 'json->a->b']);

        $model->update(['json->y' => '1']);
        $this->assertArrayNotHasKey('json->y', $model->toArray());
        $this->assertEquals(['x' => 0, 'y' => 1], $model->json);

        $model->update(['json->a->b' => '3']);
        $this->assertArrayNotHasKey('json->a->b', $model->toArray());
        $this->assertEquals(['x' => 0, 'y' => 1, 'a' => ['b' => 3]], $model->json);
    }

    public function testSaveOrFailWithDuplicatedEntry()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23000]:');

        $date = '1970-01-01';
        InstrumentTestPost::create([
            'id' => 1, 'user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date,
        ]);

        $post = new InstrumentTestPost([
            'id' => 1, 'user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date,
        ]);

        $post->saveOrFail();
    }

    public function testMultiInsertsWithDifferentValues()
    {
        $date = '1970-01-01';
        $result = InstrumentTestPost::insert([
            ['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
            ['user_id' => 2, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
        ]);

        $this->assertTrue($result);
        $this->assertEquals(2, InstrumentTestPost::count());
    }

    public function testMultiInsertsWithSameValues()
    {
        $date = '1970-01-01';
        $result = InstrumentTestPost::insert([
            ['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
            ['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
        ]);

        $this->assertTrue($result);
        $this->assertEquals(2, InstrumentTestPost::count());
    }

    public function testNestedTransactions()
    {
        $user = InstrumentTestUser::create(['email' => 'taylor@laravel.com']);
        $this->connection()->transaction(function () use ($user) {
            try {
                $this->connection()->transaction(function () use ($user) {
                    $user->email = 'otwell@laravel.com';
                    $user->save();
                    throw new Exception;
                });
            } catch (Exception) {
                // ignore the exception
            }
            $user = InstrumentTestUser::first();
            $this->assertSame('taylor@laravel.com', $user->email);
        });
    }

    public function testNestedTransactionsUsingSaveOrFailWillSucceed()
    {
        $user = InstrumentTestUser::create(['email' => 'taylor@laravel.com']);
        $this->connection()->transaction(function () use ($user) {
            try {
                $user->email = 'otwell@laravel.com';
                $user->saveOrFail();
            } catch (Exception) {
                // ignore the exception
            }

            $user = InstrumentTestUser::first();
            $this->assertSame('otwell@laravel.com', $user->email);
            $this->assertEquals(1, $user->id);
        });
    }

    public function testNestedTransactionsUsingSaveOrFailWillFails()
    {
        $user = InstrumentTestUser::create(['email' => 'taylor@laravel.com']);
        $this->connection()->transaction(function () use ($user) {
            try {
                $user->id = 'invalid';
                $user->email = 'otwell@laravel.com';
                $user->saveOrFail();
            } catch (Exception) {
                // ignore the exception
            }

            $user = InstrumentTestUser::first();
            $this->assertSame('taylor@laravel.com', $user->email);
            $this->assertEquals(1, $user->id);
        });
    }

    public function testToArrayIncludesDefaultFormattedTimestamps()
    {
        $model = new InstrumentTestUser;

        $model->setRawAttributes([
            'created_at' => '2012-12-04',
            'updated_at' => '2012-12-05',
        ]);

        $array = $model->toArray();

        $this->assertSame('2012-12-04T00:00:00.000000Z', $array['created_at']);
        $this->assertSame('2012-12-05T00:00:00.000000Z', $array['updated_at']);
    }

    public function testToArrayIncludesCustomFormattedTimestamps()
    {
        $model = new InstrumentTestUserWithCustomDateSerialization;

        $model->setRawAttributes([
            'created_at' => '2012-12-04',
            'updated_at' => '2012-12-05',
        ]);

        $array = $model->toArray();

        $this->assertSame('04-12-12', $array['created_at']);
        $this->assertSame('05-12-12', $array['updated_at']);
    }

    public function testIncrementingPrimaryKeysAreCastToIntegersByDefault()
    {
        InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        $user = InstrumentTestUser::first();
        $this->assertIsInt($user->id);
    }

    public function testDefaultIncrementingPrimaryKeyIntegerCastCanBeOverwritten()
    {
        InstrumentTestUserWithStringCastId::create(['email' => 'taylorotwell@gmail.com']);

        $user = InstrumentTestUserWithStringCastId::first();
        $this->assertIsString($user->id);
    }

    public function testRelationsArePreloadedInGlobalScope()
    {
        $user = InstrumentTestUserWithGlobalScope::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'My Post']);

        $result = InstrumentTestUserWithGlobalScope::first();

        $this->assertCount(1, $result->getRelations());
    }

    public function testModelIgnoredByGlobalScopeCanBeRefreshed()
    {
        $user = InstrumentTestUserWithOmittingGlobalScope::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $this->assertNotNull($user->fresh());
    }

    public function testGlobalScopeCanBeRemovedByOtherGlobalScope()
    {
        $user = InstrumentTestUserWithGlobalScopeRemovingOtherScope::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user->delete();

        $this->assertNotNull(InstrumentTestUserWithGlobalScopeRemovingOtherScope::find($user->id));
    }

    public function testForPageBeforeIdCorrectlyPaginates()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $results = InstrumentTestUser::forPageBeforeId(15, 2);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(1, $results->first()->id);

        $results = InstrumentTestUser::orderBy('id', 'desc')->forPageBeforeId(15, 2);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(1, $results->first()->id);
    }

    public function testForPageAfterIdCorrectlyPaginates()
    {
        InstrumentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $results = InstrumentTestUser::forPageAfterId(15, 1);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(2, $results->first()->id);

        $results = InstrumentTestUser::orderBy('id', 'desc')->forPageAfterId(15, 1);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(2, $results->first()->id);
    }

    public function testMorphToRelationsAcrossDatabaseConnections()
    {
        $item = null;

        InstrumentTestItem::create(['id' => 1]);
        InstrumentTestOrder::create(['id' => 1, 'item_type' => InstrumentTestItem::class, 'item_id' => 1]);
        try {
            $item = InstrumentTestOrder::first()->item;
        } catch (Exception) {
            // ignore the exception
        }

        $this->assertInstanceOf(InstrumentTestItem::class, $item);
    }

    public function testEagerLoadedMorphToRelationsOnAnotherDatabaseConnection()
    {
        InstrumentTestPost::create(['id' => 1, 'name' => 'Default Connection Post', 'user_id' => 1]);
        InstrumentTestPhoto::create(['id' => 1, 'imageable_type' => InstrumentTestPost::class, 'imageable_id' => 1, 'name' => 'Photo']);

        InstrumentTestPost::on('second_connection')
            ->create(['id' => 1, 'name' => 'Second Connection Post', 'user_id' => 1]);
        InstrumentTestPhoto::on('second_connection')
            ->create(['id' => 1, 'imageable_type' => InstrumentTestPost::class, 'imageable_id' => 1, 'name' => 'Photo']);

        $defaultConnectionPost = InstrumentTestPhoto::with('imageable')->first()->imageable;
        $secondConnectionPost = InstrumentTestPhoto::on('second_connection')->with('imageable')->first()->imageable;

        $this->assertSame('Default Connection Post', $defaultConnectionPost->name);
        $this->assertSame('Second Connection Post', $secondConnectionPost->name);
    }

    public function testBelongsToManyCustomPivot()
    {
        $john = InstrumentTestUserWithCustomFriendPivot::create(['id' => 1, 'name' => 'John Doe', 'email' => 'johndoe@example.com']);
        $jane = InstrumentTestUserWithCustomFriendPivot::create(['id' => 2, 'name' => 'Jane Doe', 'email' => 'janedoe@example.com']);
        $jack = InstrumentTestUserWithCustomFriendPivot::create(['id' => 3, 'name' => 'Jack Doe', 'email' => 'jackdoe@example.com']);
        $jule = InstrumentTestUserWithCustomFriendPivot::create(['id' => 4, 'name' => 'Jule Doe', 'email' => 'juledoe@example.com']);

        InstrumentTestFriendLevel::insert([
            ['id' => 1, 'level' => 'acquaintance'],
            ['id' => 2, 'level' => 'friend'],
            ['id' => 3, 'level' => 'bff'],
        ]);

        $john->friends()->attach($jane, ['friend_level_id' => 1]);
        $john->friends()->attach($jack, ['friend_level_id' => 2]);
        $john->friends()->attach($jule, ['friend_level_id' => 3]);

        $johnWithFriends = InstrumentTestUserWithCustomFriendPivot::with('friends')->find(1);

        $this->assertCount(3, $johnWithFriends->friends);
        $this->assertSame('friend', $johnWithFriends->friends->find(3)->pivot->level->level);
        $this->assertSame('Jule Doe', $johnWithFriends->friends->find(4)->pivot->friend->name);
    }

    public function testIsAfterRetrievingTheSameModel()
    {
        $saved = InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $retrieved = InstrumentTestUser::find(1);

        $this->assertTrue($saved->is($retrieved));
    }

    public function testFreshMethodOnModel()
    {
        $now = Carbon::now()->startOfSecond();
        $nowSerialized = $now->toJSON();
        $nowWithFractionsSerialized = $now->toJSON();
        Carbon::setTestNow($now);

        $storedUser1 = InstrumentTestUser::create([
            'id' => 1,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $now,
        ]);
        $storedUser1->newQuery()->update([
            'email' => 'dev@mathieutu.ovh',
            'name' => 'Mathieu TUDISCO',
        ]);
        $freshStoredUser1 = $storedUser1->fresh();

        $storedUser2 = InstrumentTestUser::create([
            'id' => 2,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $now,
        ]);
        $storedUser2->newQuery()->update(['email' => 'dev@mathieutu.ovh']);
        $freshStoredUser2 = $storedUser2->fresh();

        $notStoredUser = new InstrumentTestUser([
            'id' => 3,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $now,
        ]);
        $freshNotStoredUser = $notStoredUser->fresh();

        $this->assertEquals([
            'id' => 1,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $storedUser1->toArray());
        $this->assertEquals([
            'id' => 1,
            'name' => 'Mathieu TUDISCO',
            'email' => 'dev@mathieutu.ovh',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $freshStoredUser1->toArray());
        $this->assertInstanceOf(InstrumentTestUser::class, $storedUser1);

        $this->assertEquals([
            'id' => 2,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $storedUser2->toArray());
        $this->assertEquals([
            'id' => 2,
            'name' => null,
            'email' => 'dev@mathieutu.ovh',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $freshStoredUser2->toArray());
        $this->assertInstanceOf(InstrumentTestUser::class, $storedUser2);

        $this->assertEquals([
            'id' => 3,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $nowWithFractionsSerialized,
        ], $notStoredUser->toArray());
        $this->assertNull($freshNotStoredUser);
    }

    public function testFreshMethodOnCollection()
    {
        InstrumentTestUser::insert([['id' => 1, 'email' => 'taylorotwell@gmail.com'], ['id' => 2, 'email' => 'taylorotwell@gmail.com']]);

        $users = InstrumentTestUser::all()
            ->add(new InstrumentTestUser(['id' => 3, 'email' => 'taylorotwell@gmail.com']));

        InstrumentTestUser::find(1)->update(['name' => 'Mathieu TUDISCO']);
        InstrumentTestUser::find(2)->update(['email' => 'dev@mathieutu.ovh']);

        $this->assertCount(3, $users);
        $this->assertNotSame('Mathieu TUDISCO', $users[0]->name);
        $this->assertNotSame('dev@mathieutu.ovh', $users[1]->email);

        $refreshedUsers = $users->fresh();

        $this->assertCount(2, $refreshedUsers);
        $this->assertSame('Mathieu TUDISCO', $refreshedUsers[0]->name);
        $this->assertSame('dev@mathieutu.ovh', $refreshedUsers[1]->email);
    }

    public function testTimestampsUsingDefaultDateFormat()
    {
        $model = new InstrumentTestUser;
        $model->setDateFormat('Y-m-d H:i:s'); // Default MySQL/PostgreSQL/SQLite date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19',
        ]);

        $this->assertSame('2017-11-14 08:23:19', $model->fromDateTime($model->getAttribute('created_at')));
    }

    public function testTimestampsUsingDefaultSqlServerDateFormat()
    {
        $model = new InstrumentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.v'); // Default SQL Server date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19.000',
            'updated_at' => '2017-11-14 08:23:19.734',
        ]);

        $this->assertSame('2017-11-14 08:23:19.000', $model->fromDateTime($model->getAttribute('created_at')));
        $this->assertSame('2017-11-14 08:23:19.734', $model->fromDateTime($model->getAttribute('updated_at')));
    }

    public function testTimestampsUsingCustomDateFormat()
    {
        // Simulating using custom precisions with timestamps(4)
        $model = new InstrumentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.u'); // Custom date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19.0000',
            'updated_at' => '2017-11-14 08:23:19.7348',
        ]);

        // Note: when storing databases would truncate the value to the given precision
        $this->assertSame('2017-11-14 08:23:19.000000', $model->fromDateTime($model->getAttribute('created_at')));
        $this->assertSame('2017-11-14 08:23:19.734800', $model->fromDateTime($model->getAttribute('updated_at')));
    }

    public function testTimestampsUsingOldSqlServerDateFormat()
    {
        $model = new InstrumentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.000'); // Old SQL Server date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19.000',
        ]);

        $this->assertSame('2017-11-14 08:23:19.000', $model->fromDateTime($model->getAttribute('created_at')));
    }

    public function testTimestampsUsingOldSqlServerDateFormatFallbackToDefaultParsing()
    {
        $model = new InstrumentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.000'); // Old SQL Server date format
        $model->setRawAttributes([
            'updated_at' => '2017-11-14 08:23:19.734',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2017-11-14 08:23:19.734', $date->format('Y-m-d H:i:s.v'), 'the date should contains the precision');
        $this->assertSame('2017-11-14 08:23:19.000', $model->fromDateTime($date), 'the format should trims it');
        // No longer throwing exception since Laravel 7,
        // but Date::hasFormat() can be used instead to check date formatting:
        $this->assertTrue(Date::hasFormat('2017-11-14 08:23:19.000', $model->getDateFormat()));
        $this->assertFalse(Date::hasFormat('2017-11-14 08:23:19.734', $model->getDateFormat()));
    }

    public function testSpecialFormats()
    {
        $model = new InstrumentTestUser;
        $model->setDateFormat('!Y-d-m \\Y');
        $model->setRawAttributes([
            'updated_at' => '2017-05-11 Y',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2017-11-05 00:00:00.000000', $date->format('Y-m-d H:i:s.u'), 'the date should respect the whole format');

        $model->setDateFormat('Y d m|');
        $model->setRawAttributes([
            'updated_at' => '2020 11 09',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2020-09-11 00:00:00.000000', $date->format('Y-m-d H:i:s.u'), 'the date should respect the whole format');

        $model->setDateFormat('Y d m|*');
        $model->setRawAttributes([
            'updated_at' => '2020 11 09 foo',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2020-09-11 00:00:00.000000', $date->format('Y-m-d H:i:s.u'), 'the date should respect the whole format');
    }

    public function testUpdatingChildModelTouchesParent()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $post->update(['name' => 'Updated']);

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching model own timestamps.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function testMultiLevelTouchingWorks()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching models related timestamps.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function testDeletingChildModelTouchesParentTimestamps()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $post->delete();

        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function testTouchingChildModelUpdatesParentsTimestamps()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $post->touch();

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching model own timestamps.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function testTouchingChildModelRespectsParentNoTouching()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingUser::withoutTouching(function () use ($post) {
            $post->touch();
        });

        $this->assertTrue(
            $future->isSameDay($post->fresh()->updated_at),
            'It is not touching model own timestamps in withoutTouching scope.'
        );

        $this->assertTrue(
            $before->isSameDay($user->fresh()->updated_at),
            'It is touching model own timestamps in withoutTouching scope, when it should not.'
        );
    }

    public function testUpdatingChildPostRespectsNoTouchingDefinition()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingUser::withoutTouching(function () use ($post) {
            $post->update(['name' => 'Updated']);
        });

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching model own timestamps when it should.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models relationships when it should be disabled.');
    }

    public function testUpdatingModelInTheDisabledScopeTouchesItsOwnTimestamps()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        Model::withoutTouching(function () use ($post) {
            $post->update(['name' => 'Updated']);
        });

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function testDeletingChildModelRespectsTheNoTouchingRule()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingUser::withoutTouching(function () use ($post) {
            $post->delete();
        });

        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function testRespectedMultiLevelTouchingChain()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingUser::withoutTouching(function () {
            InstrumentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
        });

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function testTouchesGreatParentEvenWhenParentIsInNoTouchScope()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingPost::withoutTouching(function () {
            InstrumentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
        });

        $this->assertTrue($before->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function testCanNestCallsOfNoTouching()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        InstrumentTouchingUser::withoutTouching(function () {
            InstrumentTouchingPost::withoutTouching(function () {
                InstrumentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
            });
        });

        $this->assertTrue($before->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function testCanPassArrayOfModelsToIgnore()
    {
        $before = Carbon::now();

        $user = InstrumentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = InstrumentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        Model::withoutTouchingOn([InstrumentTouchingUser::class, InstrumentTouchingPost::class], function () {
            InstrumentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
        });

        $this->assertTrue($before->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function testWhenBaseModelIsIgnoredAllChildModelsAreIgnored()
    {
        $this->assertFalse(Model::isIgnoringTouch());
        $this->assertFalse(InstrumentTestUser::isIgnoringTouch());

        Model::withoutTouching(function () {
            $this->assertTrue(Model::isIgnoringTouch());
            $this->assertTrue(InstrumentTestUser::isIgnoringTouch());
        });

        $this->assertFalse(InstrumentTestUser::isIgnoringTouch());
        $this->assertFalse(Model::isIgnoringTouch());
    }

    public function testChildModelsAreIgnored()
    {
        $this->assertFalse(Model::isIgnoringTouch());
        $this->assertFalse(InstrumentTestUser::isIgnoringTouch());
        $this->assertFalse(InstrumentTestPost::isIgnoringTouch());

        InstrumentTestUser::withoutTouching(function () {
            $this->assertFalse(Model::isIgnoringTouch());
            $this->assertFalse(InstrumentTestPost::isIgnoringTouch());
            $this->assertTrue(InstrumentTestUser::isIgnoringTouch());
        });

        $this->assertFalse(InstrumentTestPost::isIgnoringTouch());
        $this->assertFalse(InstrumentTestUser::isIgnoringTouch());
        $this->assertFalse(Model::isIgnoringTouch());
    }

    public function testPivotsCanBeRefreshed()
    {
        InstrumentTestFriendLevel::create(['id' => 1, 'level' => 'acquaintance']);
        InstrumentTestFriendLevel::create(['id' => 2, 'level' => 'friend']);

        $user = InstrumentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['id' => 2, 'email' => 'abigailotwell@gmail.com'], ['friend_level_id' => 1]);

        $pivot = $user->friends[0]->pivot;

        // Simulate a change that happened externally
        DB::table('friends')->where('user_id', 1)->where('friend_id', 2)->update([
            'friend_level_id' => 2,
        ]);

        $this->assertInstanceOf(Pivot::class, $freshPivot = $pivot->fresh());
        $this->assertEquals(2, $freshPivot->friend_level_id);

        $this->assertSame($pivot, $pivot->refresh());
        $this->assertEquals(2, $pivot->friend_level_id);
    }

    public function testMorphPivotsCanBeRefreshed()
    {
        $post = InstrumentTestPost::create(['name' => 'MorphToMany Post', 'user_id' => 1]);
        $post->tags()->create(['id' => 1, 'name' => 'News']);

        $pivot = $post->tags[0]->pivot;

        // Simulate a change that happened externally
        DB::table('taggables')
            ->where([
                'taggable_type' => InstrumentTestPost::class,
                'taggable_id' => 1,
                'tag_id' => 1,
            ])
            ->update([
                'taxonomy' => 'primary',
            ]);

        $this->assertInstanceOf(MorphPivot::class, $freshPivot = $pivot->fresh());
        $this->assertSame('primary', $freshPivot->taxonomy);

        $this->assertSame($pivot, $pivot->refresh());
        $this->assertSame('primary', $pivot->taxonomy);
    }

    public function testTouchingChaperonedChildModelUpdatesParentTimestamps()
    {
        $before = Carbon::now();

        $one = InstrumentTouchingCategory::create(['id' => 1, 'name' => 'One']);
        $two = $one->children()->create(['id' => 2, 'name' => 'Two']);

        $this->assertTrue($before->isSameDay($one->updated_at));
        $this->assertTrue($before->isSameDay($two->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $two->touch();

        $this->assertTrue($future->isSameDay($two->fresh()->updated_at), 'It is not touching model own timestamps.');
        $this->assertTrue($future->isSameDay($one->fresh()->updated_at), 'It is not touching chaperoned models related timestamps.');
    }

    public function testTouchingBiDirectionalChaperonedModelUpdatesAllRelatedTimestamps()
    {
        $before = Carbon::now();

        InstrumentTouchingCategory::insert([
            ['id' => 1, 'name' => 'One', 'parent_id' => null, 'created_at' => $before, 'updated_at' => $before],
            ['id' => 2, 'name' => 'Two', 'parent_id' => 1, 'created_at' => $before, 'updated_at' => $before],
            ['id' => 3, 'name' => 'Three', 'parent_id' => 1, 'created_at' => $before, 'updated_at' => $before],
            ['id' => 4, 'name' => 'Four', 'parent_id' => 2, 'created_at' => $before, 'updated_at' => $before],
        ]);

        $one = InstrumentTouchingCategory::find(1);
        [$two, $three] = $one->children;
        [$four] = $two->children;

        $this->assertTrue($before->isSameDay($one->updated_at));
        $this->assertTrue($before->isSameDay($two->updated_at));
        $this->assertTrue($before->isSameDay($three->updated_at));
        $this->assertTrue($before->isSameDay($four->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        // Touch a random model and check that all of the others have been updated
        $models = tap([$one, $two, $three, $four], shuffle(...));
        $target = array_shift($models);
        $target->touch();

        $this->assertTrue($future->isSameDay($target->fresh()->updated_at), 'It is not touching model own timestamps.');

        while ($next = array_shift($models)) {
            $this->assertTrue(
                $future->isSameDay($next->fresh()->updated_at),
                'It is not touching related models timestamps.'
            );
        }
    }

    public function testCanFillAndInsert()
    {
        DB::enableQueryLog();
        Carbon::setTestNow('2025-03-15T07:32:00Z');

        $this->assertTrue(InstrumentTestUser::fillAndInsert([
            ['email' => 'taylor@laravel.com', 'birthday' => null],
            ['email' => 'nuno@laravel.com', 'birthday' => new Carbon('1980-01-01')],
            ['email' => 'tim@laravel.com', 'birthday' => '1987-11-01', 'created_at' => '2025-01-02T02:00:55', 'updated_at' => Carbon::parse('2025-02-19T11:41:13')],
        ]));

        $this->assertCount(1, DB::getQueryLog());

        $this->assertCount(3, $users = InstrumentTestUser::get());

        $users->take(2)->each(function (InstrumentTestUser $user) {
            $this->assertEquals(Carbon::parse('2025-03-15T07:32:00Z'), $user->created_at);
            $this->assertEquals(Carbon::parse('2025-03-15T07:32:00Z'), $user->updated_at);
        });

        $tim = $users->firstWhere('email', 'tim@laravel.com');
        $this->assertEquals(Carbon::parse('2025-01-02T02:00:55'), $tim->created_at);
        $this->assertEquals(Carbon::parse('2025-02-19T11:41:13'), $tim->updated_at);

        $this->assertNull($users[0]->birthday);
        $this->assertInstanceOf(\DateTime::class, $users[1]->birthday);
        $this->assertInstanceOf(\DateTime::class, $users[2]->birthday);
        $this->assertEquals('1987-11-01', $users[2]->birthday->format('Y-m-d'));

        DB::flushQueryLog();

        $this->assertTrue(InstrumentTestWithJSON::fillAndInsert([
            ['id' => 1, 'json' => ['album' => 'Keep It Like a Secret', 'release_date' => '1999-02-02']],
            ['id' => 2, 'json' => (object) ['album' => 'You In Reverse', 'release_date' => '2006-04-11']],
        ]));

        $this->assertCount(1, DB::getQueryLog());

        $this->assertCount(2, $testsWithJson = InstrumentTestWithJSON::get());

        $testsWithJson->each(function (InstrumentTestWithJSON $testWithJson) {
            $this->assertIsArray($testWithJson->json);
            $this->assertArrayHasKey('album', $testWithJson->json);
        });
    }

    public function testCanFillAndInsertWithUniqueStringIds()
    {
        Str::createUuidsUsingSequence([
            '00000000-0000-7000-0000-000000000000',
            '11111111-0000-7000-0000-000000000000',
            '22222222-0000-7000-0000-000000000000',
        ]);

        $this->assertTrue(ModelWithUniqueStringIds::fillAndInsert([
            [
                'name' => 'Taylor', 'role' => IntBackedRole::Admin, 'role_string' => StringBackedRole::Admin,
            ],
            [
                'name' => 'Nuno', 'role' => 3, 'role_string' => 'admin',
            ],
            [
                'name' => 'Dries', 'uuid' => 'bbbb0000-0000-7000-0000-000000000000',
            ],
            [
                'name' => 'Chris',
            ],
        ]));

        $models = ModelWithUniqueStringIds::get();

        $taylor = $models->firstWhere('name', 'Taylor');
        $nuno = $models->firstWhere('name', 'Nuno');
        $dries = $models->firstWhere('name', 'Dries');
        $chris = $models->firstWhere('name', 'Chris');

        $this->assertEquals(IntBackedRole::Admin, $taylor->role);
        $this->assertEquals(StringBackedRole::Admin, $taylor->role_string);
        $this->assertSame('00000000-0000-7000-0000-000000000000', $taylor->uuid);

        $this->assertEquals(IntBackedRole::Admin, $nuno->role);
        $this->assertEquals(StringBackedRole::Admin, $nuno->role_string);
        $this->assertSame('11111111-0000-7000-0000-000000000000', $nuno->uuid);

        $this->assertEquals(IntBackedRole::User, $dries->role);
        $this->assertEquals(StringBackedRole::User, $dries->role_string);
        $this->assertSame('bbbb0000-0000-7000-0000-000000000000', $dries->uuid);

        $this->assertEquals(IntBackedRole::User, $chris->role);
        $this->assertEquals(StringBackedRole::User, $chris->role_string);
        $this->assertSame('22222222-0000-7000-0000-000000000000', $chris->uuid);
    }

    public function testFillAndInsertOrIgnore()
    {
        Str::createUuidsUsingSequence([
            '00000000-0000-7000-0000-000000000000',
            '11111111-0000-7000-0000-000000000000',
            '22222222-0000-7000-0000-000000000000',
        ]);

        $this->assertEquals(1, ModelWithUniqueStringIds::fillAndInsertOrIgnore([
            [
                'id' => 1, 'name' => 'Taylor', 'role' => IntBackedRole::Admin, 'role_string' => StringBackedRole::Admin,
            ],
        ]));

        $this->assertSame(1, ModelWithUniqueStringIds::fillAndInsertOrIgnore([
            [
                'id' => 1, 'name' => 'Taylor', 'role' => IntBackedRole::Admin, 'role_string' => StringBackedRole::Admin,
            ],
            [
                'id' => 2, 'name' => 'Nuno',
            ],
        ]));

        $models = ModelWithUniqueStringIds::get();
        $this->assertSame('00000000-0000-7000-0000-000000000000', $models->firstWhere('name', 'Taylor')->uuid);
        $this->assertSame(
            ['uuid' => '22222222-0000-7000-0000-000000000000', 'role' => IntBackedRole::User],
            $models->firstWhere('name', 'Nuno')->only('uuid', 'role')
        );
    }

    public function testFillAndInsertGetId()
    {
        Str::createUuidsUsingSequence([
            '00000000-0000-7000-0000-000000000000',
        ]);

        DB::enableQueryLog();

        $this->assertIsInt($newId = ModelWithUniqueStringIds::fillAndInsertGetId([
            'name' => 'Taylor',
            'role' => IntBackedRole::Admin,
            'role_string' => StringBackedRole::Admin,
        ]));
        $this->assertCount(1, DB::getRawQueryLog());
        $this->assertSame($newId, ModelWithUniqueStringIds::sole()->id);
    }

    /**
     * Helpers...
     */

    /**
     * Get a database connection instance.
     *
     * @return \Voyager\Database\Connection
     */
    protected function connection($connection = 'default')
    {
        return Instrument::getConnectionResolver()->connection($connection);
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Voyager\Database\Schema\Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

/**
 * Instrument Models...
 */
class InstrumentTestUser extends Instrument
{
    protected $table = 'users';
    protected $casts = ['birthday' => 'datetime'];
    protected $guarded = [];

    public function friends()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id');
    }

    public function friendsOne()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id')->wherePivot('user_id', 1);
    }

    public function friendsTwo()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id')->wherePivot('user_id', 2);
    }

    public function posts()
    {
        return $this->hasMany(InstrumentTestPost::class, 'user_id');
    }

    public function post()
    {
        return $this->hasOne(InstrumentTestPost::class, 'user_id');
    }

    public function photos()
    {
        return $this->morphMany(InstrumentTestPhoto::class, 'imageable');
    }

    public function postWithPhotos()
    {
        return $this->post()->join('photo', function ($join) {
            $join->on('photo.imageable_id', 'post.id');
            $join->where('photo.imageable_type', 'InstrumentTestPost');
        });
    }

    public function instrumentTestAchievements()
    {
        return $this->belongsToMany(InstrumentTestAchievement::class);
    }
}

class InstrumentTestUserWithCustomFriendPivot extends InstrumentTestUser
{
    public function friends()
    {
        return $this->belongsToMany(InstrumentTestUser::class, 'friends', 'user_id', 'friend_id')
            ->using(InstrumentTestFriendPivot::class)->withPivot('user_id', 'friend_id', 'friend_level_id');
    }
}

class InstrumentTestUserWithSpaceInColumnName extends InstrumentTestUser
{
    protected $table = 'users_with_space_in_column_name';
}

class InstrumentTestNonIncrementing extends Instrument
{
    protected $table = 'non_incrementing_users';
    protected $guarded = [];
    public $incrementing = false;
    public $timestamps = false;
}

class InstrumentTestNonIncrementingSecond extends InstrumentTestNonIncrementing
{
    protected $connection = 'second_connection';
}

class InstrumentTestUserWithGlobalScope extends InstrumentTestUser
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($builder) {
            $builder->with('posts');
        });
    }
}

class InstrumentTestUserWithOmittingGlobalScope extends InstrumentTestUser
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($builder) {
            $builder->where('email', '!=', 'taylorotwell@gmail.com');
        });
    }
}

class InstrumentTestUserWithGlobalScopeRemovingOtherScope extends Instrument
{
    use SoftDeletes;

    protected $table = 'soft_deleted_users';

    protected $guarded = [];

    public static function boot()
    {
        static::addGlobalScope(function ($builder) {
            $builder->withoutGlobalScope(SoftDeletingScope::class);
        });

        parent::boot();
    }
}

class InstrumentTestUniqueUser extends Instrument
{
    protected $table = 'unique_users';
    protected $casts = ['birthday' => 'datetime'];
    protected $guarded = [];
}

class InstrumentTestPost extends Instrument
{
    protected $table = 'posts';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(InstrumentTestUser::class, 'user_id');
    }

    public function photos()
    {
        return $this->morphMany(InstrumentTestPhoto::class, 'imageable');
    }

    public function childPosts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parentPost()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function tags()
    {
        return $this->morphToMany(InstrumentTestTag::class, 'taggable', null, null, 'tag_id')->withPivot('taxonomy');
    }
}

class InstrumentTestTag extends Instrument
{
    protected $table = 'tags';
    protected $guarded = [];
}

class InstrumentTestFriendLevel extends Instrument
{
    protected $table = 'friend_levels';
    protected $guarded = [];
}

class InstrumentTestPhoto extends Instrument
{
    protected $table = 'photos';
    protected $guarded = [];

    public function imageable()
    {
        return $this->morphTo();
    }
}

class InstrumentTestUserWithStringCastId extends InstrumentTestUser
{
    protected $casts = [
        'id' => 'string',
    ];
}

class InstrumentTestUserWithCustomDateSerialization extends InstrumentTestUser
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('d-m-y');
    }
}

class InstrumentTestOrder extends Instrument
{
    protected $guarded = [];
    protected $table = 'test_orders';
    protected $with = ['item'];

    public function item()
    {
        return $this->morphTo();
    }
}

class InstrumentTestItem extends Instrument
{
    protected $guarded = [];
    protected $table = 'test_items';
    protected $connection = 'second_connection';
}

class InstrumentTestWithJSON extends Instrument
{
    protected $guarded = [];
    protected $table = 'with_json';
    public $timestamps = false;
    protected $casts = [
        'json' => 'array',
    ];
}

class InstrumentTestFriendPivot extends Pivot
{
    protected $table = 'friends';
    protected $guarded = [];
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(InstrumentTestUser::class);
    }

    public function friend()
    {
        return $this->belongsTo(InstrumentTestUser::class);
    }

    public function level()
    {
        return $this->belongsTo(InstrumentTestFriendLevel::class, 'friend_level_id');
    }
}

class InstrumentTouchingUser extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];
}

class InstrumentTouchingPost extends Instrument
{
    protected $table = 'posts';
    protected $guarded = [];

    protected $touches = [
        'user',
    ];

    public function user()
    {
        return $this->belongsTo(InstrumentTouchingUser::class, 'user_id');
    }
}

class InstrumentTouchingComment extends Instrument
{
    protected $table = 'comments';
    protected $guarded = [];

    protected $touches = [
        'post',
    ];

    public function post()
    {
        return $this->belongsTo(InstrumentTouchingPost::class, 'post_id');
    }
}

class InstrumentTouchingCategory extends Instrument
{
    protected $table = 'categories';
    protected $guarded = [];

    protected $touches = [
        'parent',
        'children',
    ];

    public function parent()
    {
        return $this->belongsTo(InstrumentTouchingCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(InstrumentTouchingCategory::class, 'parent_id')->chaperone();
    }
}

class InstrumentTestAchievement extends Instrument
{
    public $timestamps = false;

    protected $table = 'achievements';
    protected $guarded = [];
    protected $attributes = ['status' => null];

    public function instrumentTestUsers()
    {
        return $this->belongsToMany(InstrumentTestUser::class);
    }
}

class ModelWithUniqueStringIds extends Instrument
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'users_having_uuids';

    protected function casts()
    {
        return [
            'role' => IntBackedRole::class,
            'role_string' => StringBackedRole::class,
        ];
    }

    protected $attributes = [
        'role' => IntBackedRole::User,
        'role_string' => StringBackedRole::User,
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }
}

enum IntBackedRole: int
{
    case User = 1;
    case Admin = 3;
}

enum StringBackedRole: string
{
    case User = 'user';
    case Admin = 'admin';
}
