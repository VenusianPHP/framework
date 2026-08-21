<?php

namespace Tests\Database;

use BadMethodCallException;
use Carbon\Carbon;
use Faker\Generator;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\System\Application;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Attributes\UseFactory;
use Voyager\Database\Instrument\Casts\Attribute;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Factories\CrossJoinSequence;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Factories\Sequence;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\NutsAndBolts\DataObjects\Str;
use Tests\Database\Fixtures\Models\Money\Price;
use Mockery as m;
use ReflectionClass;

/**
 * Set up the database schema used by the factory tests.
 */
function dbFactoryCreateSchema(): void
{
    dbFactorySchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('options')->nullable();
        $table->timestamps();
    });

    dbFactorySchema()->create('posts', function ($table) {
        $table->increments('id');
        $table->foreignId('user_id');
        $table->string('title');
        $table->softDeletes();
        $table->timestamps();
    });

    dbFactorySchema()->create('comments', function ($table) {
        $table->increments('id');
        $table->foreignId('commentable_id');
        $table->string('commentable_type');
        $table->foreignId('user_id');
        $table->string('body');
        $table->softDeletes();
        $table->timestamps();
    });

    dbFactorySchema()->create('roles', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    dbFactorySchema()->create('role_user', function ($table) {
        $table->foreignId('role_id');
        $table->foreignId('user_id');
        $table->string('admin')->default('N');
    });
}

/**
 * Get a database connection instance.
 */
function dbFactoryConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 */
function dbFactorySchema()
{
    return dbFactoryConnection()->getSchemaBuilder();
}

beforeEach(function () {
    $container = Vessel::getInstance();
    $container->singleton(Generator::class, function ($app, $parameters) {
        return \Faker\Factory::create('en_US');
    });
    $container->instance(Application::class, $app = m::mock(Application::class));
    $app->shouldReceive('getNamespace')->andReturn('App\\');

    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbFactoryCreateSchema();
    Factory::expandRelationshipsByDefault();
});

afterEach(function () {
    dbFactorySchema()->drop('users');

    Vessel::setInstance(null);
});

test('basic model can be created', function () {
    $user = FactoryTestUserFactory::new()->create();
    $this->assertInstanceOf(Instrument::class, $user);

    $user = FactoryTestUserFactory::new()->createOne();
    $this->assertInstanceOf(Instrument::class, $user);

    $user = FactoryTestUserFactory::new()->create(['name' => 'Taylor Otwell']);
    $this->assertInstanceOf(Instrument::class, $user);
    $this->assertSame('Taylor Otwell', $user->name);

    $user = FactoryTestUserFactory::new()->set('name', 'Taylor Otwell')->create();
    $this->assertInstanceOf(Instrument::class, $user);
    $this->assertSame('Taylor Otwell', $user->name);

    $users = FactoryTestUserFactory::new()->createMany([
        ['name' => 'Taylor Otwell'],
        ['name' => 'Jeffrey Way'],
    ]);
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(2, $users);

    $users = FactoryTestUserFactory::new()->createMany(2);
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(2, $users);
    $this->assertInstanceOf(FactoryTestUser::class, $users->first());

    $users = FactoryTestUserFactory::times(2)->createMany();
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(2, $users);
    $this->assertInstanceOf(FactoryTestUser::class, $users->first());

    $users = FactoryTestUserFactory::times(2)->createMany();
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(2, $users);
    $this->assertInstanceOf(FactoryTestUser::class, $users->first());

    $users = FactoryTestUserFactory::times(3)->createMany([
        ['name' => 'Taylor Otwell'],
        ['name' => 'Jeffrey Way'],
    ]);
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(2, $users);
    $this->assertInstanceOf(FactoryTestUser::class, $users->first());

    $users = FactoryTestUserFactory::new()->createMany();
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(1, $users);
    $this->assertInstanceOf(FactoryTestUser::class, $users->first());

    $users = FactoryTestUserFactory::times(10)->create();
    $this->assertCount(10, $users);
});

test('expanded closure attributes are resolved and passed to closures', function () {
    $user = FactoryTestUserFactory::new()->create([
        'name' => function () {
            return 'taylor';
        },
        'options' => function ($attributes) {
            return $attributes['name'].'-options';
        },
    ]);

    $this->assertSame('taylor-options', $user->options);
});

test('expanded closure attribute returning a factory is resolved', function () {
    $post = FactoryTestPostFactory::new()->create([
        'title' => 'post',
        'user_id' => fn ($attributes) => FactoryTestUserFactory::new([
            'options' => $attributes['title'].'-options',
        ]),
    ]);

    $this->assertEquals('post-options', $post->user->options);
});

test('make creates unpersisted model instance', function () {
    $user = FactoryTestUserFactory::new()->makeOne();
    $this->assertInstanceOf(Instrument::class, $user);

    $user = FactoryTestUserFactory::new()->make(['name' => 'Taylor Otwell']);

    $this->assertInstanceOf(Instrument::class, $user);
    $this->assertSame('Taylor Otwell', $user->name);
    $this->assertCount(0, FactoryTestUser::all());
});

test('make many creates unpersisted model instances', function () {
    $users = FactoryTestUserFactory::new()->makeMany([
        ['name' => 'Taylor Otwell'],
        ['name' => 'Jeffrey Way'],
    ]);

    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(2, $users);
    $this->assertSame('Taylor Otwell', $users[0]->name);
    $this->assertSame('Jeffrey Way', $users[1]->name);
    $this->assertCount(0, FactoryTestUser::all());

    $users = FactoryTestUserFactory::new()->makeMany(3);
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(3, $users);
    $this->assertInstanceOf(FactoryTestUser::class, $users->first());
    $this->assertCount(0, FactoryTestUser::all());

    $users = FactoryTestUserFactory::new()->makeMany();
    $this->assertInstanceOf(Collection::class, $users);
    $this->assertCount(1, $users);
    $this->assertCount(0, FactoryTestUser::all());
});

test('basic model attributes can be created', function () {
    $user = FactoryTestUserFactory::new()->raw();
    $this->assertIsArray($user);

    $user = FactoryTestUserFactory::new()->raw(['name' => 'Taylor Otwell']);
    $this->assertIsArray($user);
    $this->assertSame('Taylor Otwell', $user['name']);
});

test('expanded model attributes can be created', function () {
    $post = FactoryTestPostFactory::new()->raw();
    $this->assertIsArray($post);

    $post = FactoryTestPostFactory::new()->raw(['title' => 'Test Title']);
    $this->assertIsArray($post);
    $this->assertIsInt($post['user_id']);
    $this->assertSame('Test Title', $post['title']);
});

test('lazy model attributes can be created', function () {
    $userFunction = FactoryTestUserFactory::new()->lazy();
    $this->assertIsCallable($userFunction);
    $this->assertInstanceOf(Instrument::class, $userFunction());

    $userFunction = FactoryTestUserFactory::new()->lazy(['name' => 'Taylor Otwell']);
    $this->assertIsCallable($userFunction);

    $user = $userFunction();
    $this->assertInstanceOf(Instrument::class, $user);
    $this->assertSame('Taylor Otwell', $user->name);
});

test('multiple model attributes can be created', function () {
    $posts = FactoryTestPostFactory::times(10)->raw();
    $this->assertIsArray($posts);

    $this->assertCount(10, $posts);
});

test('after creating and making callbacks are called', function () {
    $user = FactoryTestUserFactory::new()
        ->afterMaking(function ($user) {
            $_SERVER['__test.user.making'] = $user;
        })
        ->afterCreating(function ($user) {
            $_SERVER['__test.user.creating'] = $user;
        })
        ->create();

    $this->assertSame($user, $_SERVER['__test.user.making']);
    $this->assertSame($user, $_SERVER['__test.user.creating']);

    unset($_SERVER['__test.user.making'], $_SERVER['__test.user.creating']);
});

test('without after making removes callbacks', function () {
    $user = FactoryTestUserFactory::new()
        ->afterMaking(function ($user) {
            $_SERVER['__test.user.making'] = $user;
        })
        ->withoutAfterMaking()
        ->create();

    $this->assertArrayNotHasKey('__test.user.making', $_SERVER);
});

test('without after creating removes callbacks', function () {
    $user = FactoryTestUserFactory::new()
        ->afterCreating(function ($user) {
            $_SERVER['__test.user.creating'] = $user;
        })
        ->withoutAfterCreating()
        ->create();

    $this->assertArrayNotHasKey('__test.user.creating', $_SERVER);
});

test('without after making removes configure callbacks', function () {
    $user = FactoryTestUserWithCallbacksFactory::new()
        ->withoutAfterMaking()
        ->create();

    $this->assertArrayNotHasKey('__test.user.making', $_SERVER);
    $this->assertSame($user, $_SERVER['__test.user.creating']);

    unset($_SERVER['__test.user.creating']);
});

test('without after creating removes configure callbacks', function () {
    $user = FactoryTestUserWithCallbacksFactory::new()
        ->withoutAfterCreating()
        ->create();

    $this->assertSame($user, $_SERVER['__test.user.making']);
    $this->assertArrayNotHasKey('__test.user.creating', $_SERVER);

    unset($_SERVER['__test.user.making']);
});

test('has many relationship', function () {
    $users = FactoryTestUserFactory::times(10)
        ->has(
            FactoryTestPostFactory::times(3)
                ->state(function ($attributes, $user) {
                    // Test parent is passed to child state mutations...
                    $_SERVER['__test.post.state-user'] = $user;

                    return [];
                })
                // Test parents passed to callback...
                ->afterCreating(function ($post, $user) {
                    $_SERVER['__test.post.creating-post'] = $post;
                    $_SERVER['__test.post.creating-user'] = $user;
                }),
            'posts'
        )
        ->create();

    $this->assertCount(10, FactoryTestUser::all());
    $this->assertCount(30, FactoryTestPost::all());
    $this->assertCount(3, FactoryTestUser::latest()->first()->posts);

    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.post.creating-post']);
    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.post.creating-user']);
    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.post.state-user']);

    unset($_SERVER['__test.post.creating-post'], $_SERVER['__test.post.creating-user'], $_SERVER['__test.post.state-user']);
});

test('belongs to relationship', function () {
    $posts = FactoryTestPostFactory::times(3)
        ->for(FactoryTestUserFactory::new(['name' => 'Taylor Otwell']), 'user')
        ->create();

    $this->assertCount(3, $posts->filter(function ($post) {
        return $post->user->name === 'Taylor Otwell';
    }));

    $this->assertCount(1, FactoryTestUser::all());
    $this->assertCount(3, FactoryTestPost::all());
});

test('belongs to relationship with existing model instance', function () {
    $user = FactoryTestUserFactory::new(['name' => 'Taylor Otwell'])->create();
    $posts = FactoryTestPostFactory::times(3)
        ->for($user, 'user')
        ->create();

    $this->assertCount(3, $posts->filter(function ($post) use ($user) {
        return $post->user->is($user);
    }));

    $this->assertCount(1, FactoryTestUser::all());
    $this->assertCount(3, FactoryTestPost::all());
});

test('belongs to relationship with existing model instance with relationship name implied from model', function () {
    $user = FactoryTestUserFactory::new(['name' => 'Taylor Otwell'])->create();
    $posts = FactoryTestPostFactory::times(3)
        ->for($user)
        ->create();

    $this->assertCount(3, $posts->filter(function ($post) use ($user) {
        return $post->factoryTestUser->is($user);
    }));

    $this->assertCount(1, FactoryTestUser::all());
    $this->assertCount(3, FactoryTestPost::all());
});

test('morph to relationship', function () {
    $posts = FactoryTestCommentFactory::times(3)
        ->for(FactoryTestPostFactory::new(['title' => 'Test Title']), 'commentable')
        ->create();

    $this->assertSame('Test Title', FactoryTestPost::first()->title);
    $this->assertCount(3, FactoryTestPost::first()->comments);

    $this->assertCount(1, FactoryTestPost::all());
    $this->assertCount(3, FactoryTestComment::all());
});

test('morph to relationship with existing model instance', function () {
    $post = FactoryTestPostFactory::new(['title' => 'Test Title'])->create();
    $posts = FactoryTestCommentFactory::times(3)
        ->for($post, 'commentable')
        ->create();

    $this->assertSame('Test Title', FactoryTestPost::first()->title);
    $this->assertCount(3, FactoryTestPost::first()->comments);

    $this->assertCount(1, FactoryTestPost::all());
    $this->assertCount(3, FactoryTestComment::all());
});

test('belongs to many relationship', function () {
    $users = FactoryTestUserFactory::times(3)
        ->hasAttached(
            FactoryTestRoleFactory::times(3)->afterCreating(function ($role, $user) {
                $_SERVER['__test.role.creating-role'] = $role;
                $_SERVER['__test.role.creating-user'] = $user;
            }),
            ['admin' => 'Y'],
            'roles'
        )
        ->create();

    $this->assertCount(9, FactoryTestRole::all());

    $user = FactoryTestUser::latest()->first();

    $this->assertCount(3, $user->roles);
    $this->assertSame('Y', $user->roles->first()->pivot->admin);

    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.role.creating-role']);
    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.role.creating-user']);

    unset($_SERVER['__test.role.creating-role'], $_SERVER['__test.role.creating-user']);
});

test('belongs to many relationship related models set on instance when touching owner', function () {
    $user = FactoryTestUserFactory::new()->create();
    $role = FactoryTestRoleFactory::new()->hasAttached($user, [], 'users')->create();

    $this->assertCount(1, $role->users);
});

test('relation can be loaded before model is created', function () {
    $user = FactoryTestUserFactory::new(['name' => 'Taylor Otwell'])->createOne();

    $post = FactoryTestPostFactory::new()
        ->for($user, 'user')
        ->afterMaking(function (FactoryTestPost $post) {
            $post->load('user');
        })
        ->createOne();

    $this->assertTrue($post->relationLoaded('user'));
    $this->assertTrue($post->user->is($user));

    $this->assertCount(1, FactoryTestUser::all());
    $this->assertCount(1, FactoryTestPost::all());
});

test('belongs to many relationship with existing model instances', function () {
    $roles = FactoryTestRoleFactory::times(3)
        ->afterCreating(function ($role) {
            $_SERVER['__test.role.creating-role'] = $role;
        })
        ->create();
    FactoryTestUserFactory::times(3)
        ->hasAttached($roles, ['admin' => 'Y'], 'roles')
        ->create();

    $this->assertCount(3, FactoryTestRole::all());

    $user = FactoryTestUser::latest()->first();

    $this->assertCount(3, $user->roles);
    $this->assertSame('Y', $user->roles->first()->pivot->admin);

    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.role.creating-role']);

    unset($_SERVER['__test.role.creating-role']);
});

test('belongs to many relationship with existing model instances using array', function () {
    $roles = FactoryTestRoleFactory::times(3)
        ->afterCreating(function ($role) {
            $_SERVER['__test.role.creating-role'] = $role;
        })
        ->create();
    FactoryTestUserFactory::times(3)
        ->hasAttached($roles->toArray(), ['admin' => 'Y'], 'roles')
        ->create();

    $this->assertCount(3, FactoryTestRole::all());

    $user = FactoryTestUser::latest()->first();

    $this->assertCount(3, $user->roles);
    $this->assertSame('Y', $user->roles->first()->pivot->admin);

    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.role.creating-role']);

    unset($_SERVER['__test.role.creating-role']);
});

test('belongs to many relationship with existing model instances with relationship name implied from model', function () {
    $roles = FactoryTestRoleFactory::times(3)
        ->afterCreating(function ($role) {
            $_SERVER['__test.role.creating-role'] = $role;
        })
        ->create();
    FactoryTestUserFactory::times(3)
        ->hasAttached($roles, ['admin' => 'Y'])
        ->create();

    $this->assertCount(3, FactoryTestRole::all());

    $user = FactoryTestUser::latest()->first();

    $this->assertCount(3, $user->factoryTestRoles);
    $this->assertSame('Y', $user->factoryTestRoles->first()->pivot->admin);

    $this->assertInstanceOf(Instrument::class, $_SERVER['__test.role.creating-role']);

    unset($_SERVER['__test.role.creating-role']);
});

test('sequences', function () {
    $users = FactoryTestUserFactory::times(2)->sequence(
        ['name' => 'Taylor Otwell'],
        ['name' => 'Abigail Otwell'],
    )->create();

    $this->assertSame('Taylor Otwell', $users[0]->name);
    $this->assertSame('Abigail Otwell', $users[1]->name);

    $user = FactoryTestUserFactory::new()
        ->hasAttached(
            FactoryTestRoleFactory::times(4),
            new Sequence(['admin' => 'Y'], ['admin' => 'N']),
            'roles'
        )
        ->create();

    $this->assertCount(4, $user->roles);

    $this->assertCount(2, $user->roles->filter(function ($role) {
        return $role->pivot->admin === 'Y';
    }));

    $this->assertCount(2, $user->roles->filter(function ($role) {
        return $role->pivot->admin === 'N';
    }));

    $users = FactoryTestUserFactory::times(2)->sequence(function ($sequence) {
        return ['name' => 'index: '.$sequence->index];
    })->create();

    $this->assertSame('index: 0', $users[0]->name);
    $this->assertSame('index: 1', $users[1]->name);
});

test('counted sequence', function () {
    $factory = FactoryTestUserFactory::new()->forEachSequence(
        ['name' => 'Taylor Otwell'],
        ['name' => 'Abigail Otwell'],
        ['name' => 'Dayle Rees']
    );

    $class = new ReflectionClass($factory);
    $prop = $class->getProperty('count');
    $value = $prop->getValue($factory);

    $this->assertSame(3, $value);
});

test('sequence with has many relationship', function () {
    $users = FactoryTestUserFactory::times(2)
        ->sequence(
            ['name' => 'Abigail Otwell'],
            ['name' => 'Taylor Otwell'],
        )
        ->has(
            FactoryTestPostFactory::times(3)
                ->state(['title' => 'Post'])
                ->sequence(function ($sequence, $attributes, $user) {
                    return ['title' => $user->name.' '.$attributes['title'].' '.($sequence->index % 3 + 1)];
                }),
            'posts'
        )
        ->create();

    $this->assertCount(2, FactoryTestUser::all());
    $this->assertCount(6, FactoryTestPost::all());
    $this->assertCount(3, FactoryTestUser::latest()->first()->posts);
    $this->assertEquals(
        FactoryTestPost::orderBy('title')->pluck('title')->all(),
        [
            'Abigail Otwell Post 1',
            'Abigail Otwell Post 2',
            'Abigail Otwell Post 3',
            'Taylor Otwell Post 1',
            'Taylor Otwell Post 2',
            'Taylor Otwell Post 3',
        ]
    );
});

test('cross join sequences', function () {
    $assert = function ($users) {
        $assertions = [
            ['first_name' => 'Thomas', 'last_name' => 'Anderson'],
            ['first_name' => 'Thomas', 'last_name' => 'Smith'],
            ['first_name' => 'Agent', 'last_name' => 'Anderson'],
            ['first_name' => 'Agent', 'last_name' => 'Smith'],
        ];

        foreach ($assertions as $key => $assertion) {
            $this->assertSame(
                $assertion,
                $users[$key]->only('first_name', 'last_name'),
            );
        }
    };

    $usersByClass = FactoryTestUserFactory::times(4)
        ->state(
            new CrossJoinSequence(
                [['first_name' => 'Thomas'], ['first_name' => 'Agent']],
                [['last_name' => 'Anderson'], ['last_name' => 'Smith']],
            ),
        )
        ->make();

    $assert($usersByClass);

    $usersByMethod = FactoryTestUserFactory::times(4)
        ->crossJoinSequence(
            [['first_name' => 'Thomas'], ['first_name' => 'Agent']],
            [['last_name' => 'Anderson'], ['last_name' => 'Smith']],
        )
        ->make();

    $assert($usersByMethod);
});

test('resolve nested model factories', function () {
    Factory::useNamespace('Factories\\');

    $resolves = [
        'App\\Foo' => 'Factories\\FooFactory',
        'App\\Models\\Foo' => 'Factories\\FooFactory',
        'App\\Models\\Nested\\Foo' => 'Factories\\Nested\\FooFactory',
        'App\\Models\\Really\\Nested\\Foo' => 'Factories\\Really\\Nested\\FooFactory',
    ];

    foreach ($resolves as $model => $factory) {
        $this->assertEquals($factory, Factory::resolveFactoryName($model));
    }
});

test('resolve nested model name from factory', function () {
    Vessel::getInstance()->instance(Application::class, $app = m::mock(Application::class));
    $app->shouldReceive('getNamespace')->andReturn('Tests\\Database\\Fixtures\\');

    Factory::useNamespace('Tests\\Database\\Fixtures\\Factories\\');

    $factory = Price::factory();

    $this->assertSame(Price::class, $factory->modelName());
});

test('resolve non app nested model factories', function () {
    Vessel::getInstance()->instance(Application::class, $app = m::mock(Application::class));
    $app->shouldReceive('getNamespace')->andReturn('Foo\\');

    Factory::useNamespace('Factories\\');

    $resolves = [
        'Foo\\Bar' => 'Factories\\BarFactory',
        'Foo\\Models\\Bar' => 'Factories\\BarFactory',
        'Foo\\Models\\Nested\\Bar' => 'Factories\\Nested\\BarFactory',
        'Foo\\Models\\Really\\Nested\\Bar' => 'Factories\\Really\\Nested\\BarFactory',
    ];

    foreach ($resolves as $model => $factory) {
        $this->assertEquals($factory, Factory::resolveFactoryName($model));
    }
});

test('model has factory', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $this->assertInstanceOf(FactoryTestUserFactory::class, FactoryTestUser::factory());
});

test('dynamic has and for methods', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $user = FactoryTestUserFactory::new()->hasPosts(3)->create();

    $this->assertCount(3, $user->posts);

    $post = FactoryTestPostFactory::new()
        ->forAuthor(['name' => 'Taylor Otwell'])
        ->hasComments(2)
        ->create();

    $this->assertInstanceOf(FactoryTestUser::class, $post->author);
    $this->assertSame('Taylor Otwell', $post->author->name);
    $this->assertCount(2, $post->comments);
});

test('can be macroable', function () {
    $factory = FactoryTestUserFactory::new();
    $factory->macro('getFoo', function () {
        return 'Hello World';
    });

    $this->assertSame('Hello World', $factory->getFoo());
});

test('factory can conditionally execute code', function () {
    FactoryTestUserFactory::new()
        ->when(true, function () {
            $this->assertTrue(true);
        })
        ->when(false, function () {
            $this->fail('Unreachable code that has somehow been reached.');
        })
        ->unless(false, function () {
            $this->assertTrue(true);
        })
        ->unless(true, function () {
            $this->fail('Unreachable code that has somehow been reached.');
        });
});

test('dynamic trashed state for softdeletes models', function () {
    $now = Carbon::create(2020, 6, 7, 8, 9);
    Carbon::setTestNow($now);
    $post = FactoryTestPostFactory::new()->trashed()->create();

    $this->assertTrue($post->deleted_at->equalTo($now->subDay()));

    $deleted_at = Carbon::create(2020, 1, 2, 3, 4, 5);
    $post = FactoryTestPostFactory::new()->trashed($deleted_at)->create();

    $this->assertTrue($deleted_at->equalTo($post->deleted_at));

    Carbon::setTestNow();
});

test('dynamic trashed state respects existing state', function () {
    $now = Carbon::create(2020, 6, 7, 8, 9);
    Carbon::setTestNow($now);
    $comment = FactoryTestCommentFactory::new()->trashed()->create();

    $this->assertTrue($comment->deleted_at->equalTo($now->subWeek()));

    Carbon::setTestNow();
});

test('dynamic trashed state throws exception when not a softdeletes model', function () {
    FactoryTestUserFactory::new()->trashed()->create();
})->throws(BadMethodCallException::class);

test('model instances can be used in place of nested factories', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $user = FactoryTestUserFactory::new()->create();
    $post = FactoryTestPostFactory::new()
        ->recycle($user)
        ->hasComments(2)
        ->create();

    $this->assertSame(1, FactoryTestUser::count());
    $this->assertEquals($user->id, $post->user_id);
    $this->assertEquals($user->id, $post->comments[0]->user_id);
    $this->assertEquals($user->id, $post->comments[1]->user_id);
});

test('for method recycles models', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $user = FactoryTestUserFactory::new()->create();
    $post = FactoryTestPostFactory::new()
        ->recycle($user)
        ->for(FactoryTestUserFactory::new())
        ->create();

    $this->assertSame(1, FactoryTestUser::count());
});

test('has method does not reassign the parent', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $post = FactoryTestPostFactory::new()->create();
    $user = FactoryTestUserFactory::new()
        ->recycle($post)
        // The recycled post already belongs to a user, so it shouldn't be recycled here.
        ->has(FactoryTestPostFactory::new(), 'posts')
        ->create();

    $this->assertSame(2, FactoryTestPost::count());
});

test('multiple models can be provided to recycle', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $users = FactoryTestUserFactory::new()->count(3)->create();

    $posts = FactoryTestPostFactory::new()
        ->recycle($users)
        ->for(FactoryTestUserFactory::new())
        ->has(FactoryTestCommentFactory::new()->count(5), 'comments')
        ->count(2)
        ->create();

    $this->assertSame(3, FactoryTestUser::count());
});

test('recycled models can be combined with multiple calls', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $users = FactoryTestUserFactory::new()
        ->count(2)
        ->create();
    $posts = FactoryTestPostFactory::new()
        ->recycle($users)
        ->count(2)
        ->create();
    $additionalUser = FactoryTestUserFactory::new()
        ->create();
    $additionalPost = FactoryTestPostFactory::new()
        ->recycle($additionalUser)
        ->create();

    $this->assertSame(3, FactoryTestUser::count());
    $this->assertSame(3, FactoryTestPost::count());

    $comments = FactoryTestCommentFactory::new()
        ->recycle($users)
        ->recycle($posts)
        ->recycle([$additionalUser, $additionalPost])
        ->count(5)
        ->create();

    $this->assertSame(3, FactoryTestUser::count());
    $this->assertSame(3, FactoryTestPost::count());
});

test('no models can be provided to recycle', function () {
    Factory::guessFactoryNamesUsing(function ($model) {
        return $model.'Factory';
    });

    $posts = FactoryTestPostFactory::new()
        ->recycle([])
        ->count(2)
        ->create();

    $this->assertSame(2, FactoryTestPost::count());
    $this->assertSame(2, FactoryTestUser::count());
});

test('can disable relationships', function () {
    $post = FactoryTestPostFactory::new()
        ->withoutParents()
        ->make();

    $this->assertNull($post->user_id);
});

test('can disable relationships explicitly by model name', function () {
    $comment = FactoryTestCommentFactory::new()
        ->withoutParents([FactoryTestUser::class])
        ->make();

    $this->assertNull($comment->user_id);
    $this->assertNotNull($comment->commentable->id);
});

test('can disable relationships explicitly by attribute name', function () {
    $comment = FactoryTestCommentFactory::new()
        ->withoutParents(['user_id'])
        ->make();

    $this->assertNull($comment->user_id);
    $this->assertNotNull($comment->commentable->id);
});

test('can disable relationships explicitly by both attribute name and model name', function () {
    $comment = FactoryTestCommentFactory::new()
        ->withoutParents(['user_id', FactoryTestPost::class])
        ->make();

    $this->assertNull($comment->user_id);
    $this->assertNull($comment->commentable);
});

test('can default to without parents', function () {
    FactoryTestPostFactory::dontExpandRelationshipsByDefault();

    $post = FactoryTestPostFactory::new()->make();
    $this->assertNull($post->user_id);

    FactoryTestPostFactory::expandRelationshipsByDefault();
    $postWithParents = FactoryTestPostFactory::new()->create();
    $this->assertNotNull($postWithParents->user_id);
});

test('factory model names correct', function () {
    $this->assertEquals(FactoryTestUseFactoryAttribute::factory()->modelName(), FactoryTestUseFactoryAttribute::class);
    $this->assertEquals(FactoryTestGuessModel::factory()->modelName(), FactoryTestGuessModel::class);
});

test('factory global model resolver', function () {
    Factory::guessModelNamesUsing(function ($factory) {
        return __NAMESPACE__.'\\'.Str::replaceLast('Factory', '', class_basename($factory::class));
    });

    $this->assertEquals(FactoryTestGuessModel::factory()->modelName(), FactoryTestGuessModel::class);
    $this->assertEquals(FactoryTestUseFactoryAttribute::factory()->modelName(), FactoryTestUseFactoryAttribute::class);

    $this->assertEquals(FactoryTestUseFactoryAttributeFactory::new()->modelName(), FactoryTestUseFactoryAttribute::class);
    $this->assertEquals(FactoryTestGuessModelFactory::new()->modelName(), FactoryTestGuessModel::class);
});

test('factory model has many relationship has pending attributes', function () {
    FactoryTestUser::factory()->has(new FactoryTestPostFactory(), 'postsWithFooBarBazAsTitle')->create();

    $this->assertEquals('foo bar baz', FactoryTestPost::first()->title);
});

test('factory model has many relationship has pending attributes override', function () {
    FactoryTestUser::factory()->has((new FactoryTestPostFactory())->state(['title' => 'other title']), 'postsWithFooBarBazAsTitle')->create();

    $this->assertEquals('other title', FactoryTestPost::first()->title);
});

test('factory model has one relationship has pending attributes', function () {
    FactoryTestUser::factory()->has(new FactoryTestPostFactory(), 'postWithFooBarBazAsTitle')->create();

    $this->assertEquals('foo bar baz', FactoryTestPost::first()->title);
});

test('factory model has one relationship has pending attributes override', function () {
    FactoryTestUser::factory()->has((new FactoryTestPostFactory())->state(['title' => 'other title']), 'postWithFooBarBazAsTitle')->create();

    $this->assertEquals('other title', FactoryTestPost::first()->title);
});

test('factory model belongs to many relationship has pending attributes', function () {
    FactoryTestUser::factory()->has(new FactoryTestRoleFactory(), 'rolesWithFooBarBazAsName')->create();

    $this->assertEquals('foo bar baz', FactoryTestRole::first()->name);
});

test('factory model belongs to many relationship has pending attributes override', function () {
    FactoryTestUser::factory()->has((new FactoryTestRoleFactory())->state(['name' => 'other name']), 'rolesWithFooBarBazAsName')->create();

    $this->assertEquals('other name', FactoryTestRole::first()->name);
});

test('factory model morph many relationship has pending attributes', function () {
    (new FactoryTestPostFactory())->has(new FactoryTestCommentFactory(), 'commentsWithFooBarBazAsBody')->create();

    $this->assertEquals('foo bar baz', FactoryTestComment::first()->body);
});

test('factory model morph many relationship has pending attributes override', function () {
    (new FactoryTestPostFactory())->has((new FactoryTestCommentFactory())->state(['body' => 'other body']), 'commentsWithFooBarBazAsBody')->create();

    $this->assertEquals('other body', FactoryTestComment::first()->body);
});

test('factory can insert', function () {
    (new FactoryTestPostFactory())
        ->count(5)
        ->recycle([
            (new FactoryTestUserFactory())->create(['name' => Name::Taylor]),
            (new FactoryTestUserFactory())->create(['name' => Name::Shad, 'created_at' => now()]),
        ])
        ->state(['title' => 'hello'])
        ->insert();
    $this->assertCount(5, $posts = FactoryTestPost::query()->where('title', 'hello')->get());
    $this->assertEquals(strtoupper($posts[0]->user->name), $posts[0]->upper_case_name);
    $this->assertEquals(
        2,
        ($users = FactoryTestUser::query()->get())->count()
    );
    $this->assertCount(1, $users->where('name', 'totwell'));
    $this->assertCount(1, $users->where('name', 'shaedrich'));
});

test('factory can insert zero models', function () {
    (new FactoryTestPostFactory())->count(0)->insert();

    $this->assertCount(0, FactoryTestPost::all());
});

test('factory can insert with hidden', function () {
    (new FactoryTestUserFactory())->forEachSequence(['name' => Name::Taylor, 'options' => 'abc'])->insert();
    $user = DB::table('users')->sole();
    $this->assertEquals('abc', $user->options);
    $userModel = FactoryTestUser::query()->sole();
    $this->assertEquals('abc', $userModel->options);
});

test('factory can insert with array casts', function () {
    (new FactoryTestUserWithArrayFactory())->count(2)->insert();
    $users = DB::table('users')->get();
    foreach ($users as $user) {
        $this->assertEquals(['rtj'], json_decode($user->options, true));
        $createdAt = Carbon::parse($user->created_at);
        $updatedAt = Carbon::parse($user->updated_at);
        $this->assertEquals($updatedAt, $createdAt);
    }
});

class FactoryTestUserFactory extends Factory
{
    protected $model = FactoryTestUser::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'options' => null,
        ];
    }
}

class FactoryTestUser extends Instrument
{
    use HasFactory;

    protected $table = 'users';
    protected $hidden = ['options'];
    protected $withCount = ['posts'];
    protected $with = ['posts'];

    public function posts()
    {
        return $this->hasMany(FactoryTestPost::class, 'user_id');
    }

    public function postsWithFooBarBazAsTitle()
    {
        return $this->hasMany(FactoryTestPost::class, 'user_id')->withAttributes(['title' => 'foo bar baz']);
    }

    public function postWithFooBarBazAsTitle()
    {
        return $this->hasOne(FactoryTestPost::class, 'user_id')->withAttributes(['title' => 'foo bar baz']);
    }

    public function roles()
    {
        return $this->belongsToMany(FactoryTestRole::class, 'role_user', 'user_id', 'role_id')->withPivot('admin');
    }

    public function rolesWithFooBarBazAsName()
    {
        return $this->belongsToMany(FactoryTestRole::class, 'role_user', 'user_id', 'role_id')->withPivot('admin')->withAttributes(['name' => 'foo bar baz']);
    }

    public function factoryTestRoles()
    {
        return $this->belongsToMany(FactoryTestRole::class, 'role_user', 'user_id', 'role_id')->withPivot('admin');
    }
}

class FactoryTestPostFactory extends Factory
{
    protected $model = FactoryTestPost::class;

    public function definition()
    {
        return [
            'user_id' => FactoryTestUserFactory::new(),
            'title' => $this->faker->name(),
        ];
    }
}

class FactoryTestPost extends Instrument
{
    use SoftDeletes;

    protected $table = 'posts';

    protected $appends = ['upper_case_name'];

    public function upperCaseName(): Attribute
    {
        return Attribute::get(fn ($attr) => Str::upper($this->user->name));
    }

    public function user()
    {
        return $this->belongsTo(FactoryTestUser::class, 'user_id');
    }

    public function factoryTestUser()
    {
        return $this->belongsTo(FactoryTestUser::class, 'user_id');
    }

    public function author()
    {
        return $this->belongsTo(FactoryTestUser::class, 'user_id');
    }

    public function comments()
    {
        return $this->morphMany(FactoryTestComment::class, 'commentable');
    }

    public function commentsWithFooBarBazAsBody()
    {
        return $this->morphMany(FactoryTestComment::class, 'commentable')->withAttributes(['body' => 'foo bar baz']);
    }
}

class FactoryTestCommentFactory extends Factory
{
    protected $model = FactoryTestComment::class;

    public function definition()
    {
        return [
            'commentable_id' => FactoryTestPostFactory::new(),
            'commentable_type' => FactoryTestPost::class,
            'user_id' => fn () => FactoryTestUserFactory::new(),
            'body' => $this->faker->name(),
        ];
    }

    public function trashed()
    {
        return $this->state([
            'deleted_at' => Carbon::now()->subWeek(),
        ]);
    }
}

class FactoryTestComment extends Instrument
{
    use SoftDeletes;

    protected $table = 'comments';

    public function commentable()
    {
        return $this->morphTo();
    }
}

class FactoryTestRoleFactory extends Factory
{
    protected $model = FactoryTestRole::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}

class FactoryTestRole extends Instrument
{
    protected $table = 'roles';

    protected $touches = ['users'];

    public function users()
    {
        return $this->belongsToMany(FactoryTestUser::class, 'role_user', 'role_id', 'user_id')->withPivot('admin');
    }
}

class FactoryTestGuessModelFactory extends Factory
{
    protected static function appNamespace()
    {
        return __NAMESPACE__.'\\';
    }

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}

class FactoryTestGuessModel extends Instrument
{
    use HasFactory;

    protected static $factory = FactoryTestGuessModelFactory::class;
}

class FactoryTestUseFactoryAttributeFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}

#[UseFactory(FactoryTestUseFactoryAttributeFactory::class)]
class FactoryTestUseFactoryAttribute extends Instrument
{
    use HasFactory;
}

class FactoryTestUserWithArray extends Instrument
{
    protected $table = 'users';

    protected function casts()
    {
        return ['options' => 'array'];
    }
}

class FactoryTestUserWithArrayFactory extends Factory
{
    protected $model = FactoryTestUserWithArray::class;

    public function definition()
    {
        return [
            'name' => 'killer mike',
            'options' => ['rtj'],
        ];
    }
}

class FactoryTestUserWithCallbacksFactory extends Factory
{
    protected $model = FactoryTestUser::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'options' => null,
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function ($user) {
            $_SERVER['__test.user.making'] = $user;
        })->afterCreating(function ($user) {
            $_SERVER['__test.user.creating'] = $user;
        });
    }
}

enum Name: string
{
    case Taylor = 'totwell';
    case Shad = 'shaedrich';
}
