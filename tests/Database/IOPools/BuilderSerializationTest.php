<?php

use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;
use Voyager\Database\DatabaseServiceProvider;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;
use Voyager\Database\Query\Builder as QueryBuilder;

class SerialUser extends Model { protected $table = 'users'; public $timestamps = false; protected $guarded = []; public function posts() { return $this->hasMany(SerialPost::class, 'user_id'); } }
class SerialPost extends Model { protected $table = 'posts'; public $timestamps = false; protected $guarded = []; }

beforeEach(function () {
    $this->app = RenderedInstance::setInstance(new RenderedInstance(dirname(__DIR__, 3)));
    $this->app->registerInstance('config', new Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
    ]));
    $this->app->register(new DatabaseServiceProvider($this->app));
    Model::setConnectionResolver($this->app['db']);
    $schema = $this->app['db']->connection()->getSchemaBuilder();
    $schema->create('users', function ($t) { $t->increments('id'); $t->string('name'); });
    $schema->create('posts', function ($t) { $t->increments('id'); $t->integer('user_id'); $t->string('title'); });
    SerialUser::insert([['name' => 'ada'], ['name' => 'bob']]);
    SerialPost::insert([['user_id' => 1, 'title' => 'a1'], ['user_id' => 1, 'title' => 'a2'], ['user_id' => 2, 'title' => 'b1']]);
});

afterEach(fn () => RenderedInstance::setInstance(null));

it('a query builder round-trips with its sql, bindings, joins, and connection name', function () {
    $q = $this->app['db']->table('users')->join('posts', 'posts.user_id', '=', 'users.id')->where('users.name', 'ada')->orderBy('posts.title')->limit(5);

    $back = unserialize(serialize($q));

    expect($back)->toBeInstanceOf(QueryBuilder::class)
        ->and($back->toSql())->toBe($q->toSql())
        ->and($back->getBindings())->toBe($q->getBindings())
        ->and($back->getConnection()->getName())->toBe('sqlite')
        ->and($back->pluck('title')->all())->toBe(['a1', 'a2']);
});

it('an instrument builder round-trips with its model, scopes, and eager loads', function () {
    $q = SerialUser::query()->where('name', 'ada')->with('posts');

    $back = unserialize(serialize($q));

    expect($back)->toBeInstanceOf(InstrumentBuilder::class)
        ->and($back->getModel())->toBeInstanceOf(SerialUser::class)
        ->and($back->toSql())->toBe($q->toSql())
        ->and($back->get()->first()->posts->pluck('title')->all())->toBe(['a1', 'a2']);
});

it('eager load closures survive', function () {
    $q = SerialUser::query()->with(['posts' => fn ($p) => $p->where('title', 'a2')]);

    $users = unserialize(serialize($q))->get();

    expect($users->firstWhere('name', 'ada')->posts->pluck('title')->all())->toBe(['a2'])
        ->and($users->firstWhere('name', 'bob')->posts)->toHaveCount(0);
});

it('a nested where and a union round-trip', function () {
    $q = $this->app['db']->table('users')->where(fn ($w) => $w->where('id', 1)->orWhere('id', 2))
        ->union($this->app['db']->table('users')->where('name', 'zed'));

    $back = unserialize(serialize($q));

    expect($back->toSql())->toBe($q->toSql())->and($back->get())->toHaveCount(2);
});
