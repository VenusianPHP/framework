<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Core\RenderedInstance;
use Voyager\Database\Connection;
use Voyager\Database\DatabaseServiceProvider;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\IOPools\OffloadedQuery;
use Voyager\Database\IOPools\QueryGig;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\WorkTargetManager;
use Voyager\Pagination\LengthAwarePaginator;
use Voyager\Signals\SignalDispatcher;

class ViaUser extends Model { protected $table = 'users'; public $timestamps = false; protected $guarded = []; public function posts() { return $this->hasMany(ViaPost::class, 'user_id'); } }
class ViaPost extends Model { protected $table = 'posts'; public $timestamps = false; protected $guarded = []; }

beforeEach(function () {
    $this->app = RenderedInstance::setInstance(new RenderedInstance(dirname(__DIR__, 3)));
    $this->app->registerInstance('config', new Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'io-pools' => ['work' => ['default' => 'sync']],
    ]));
    $this->app->registerInstance(Loop::class, $this->loop = new EventLoop);
    $this->app->registerInstance('signals', $this->signals = new SignalDispatcher($this->app));
    $this->app->registerInstance('work-targets', new WorkTargetManager($this->app));
    $this->app->register(new DatabaseServiceProvider($this->app));
    Model::setConnectionResolver($this->app['db']);
    Model::setEventDispatcher($this->signals);
    $schema = $this->app['db']->connection()->getSchemaBuilder();
    $schema->create('users', function ($t) { $t->increments('id'); $t->string('name'); });
    $schema->create('posts', function ($t) { $t->increments('id'); $t->integer('user_id'); $t->string('title'); });
    ViaUser::insert([['name' => 'ada'], ['name' => 'bob'], ['name' => 'cy']]);
    ViaPost::insert([['user_id' => 1, 'title' => 'a1'], ['user_id' => 2, 'title' => 'b1']]);
});

afterEach(function () { ViaUser::flushEventListeners(); ViaPost::flushEventListeners(); RenderedInstance::setInstance(null); });

it('a query gig runs the terminal on the builder it carries', function () {
    $gig = unserialize(serialize(new QueryGig(ViaUser::where('name', 'ada')->with('posts'), 'get', [])));

    $users = $gig->handle();

    expect($users)->toBeInstanceOf(Collection::class)->toHaveCount(1)
        ->and($users->first()->posts->first()->title)->toBe('a1');
});

it('via() answers get, first, count, exists, and paginate with what the blocking call returns', function () {
    expect(ViaUser::via())->toBeInstanceOf(OffloadedQuery::class);

    $get = ViaUser::orderBy('id')->via()->get();
    expect($get)->toBeInstanceOf(Promise::class)
        ->and($get->wait())->toBeInstanceOf(Collection::class)->toHaveCount(3)
        ->and($get->wait()->first())->toBeInstanceOf(ViaUser::class)
        ->and(ViaUser::where('name', 'bob')->via()->first()->wait()->name)->toBe('bob')
        ->and(ViaUser::where('name', 'nobody')->via()->first()->wait())->toBeNull()
        ->and(ViaUser::via()->count()->wait())->toBe(3)
        ->and(ViaUser::where('id', 2)->via()->exists()->wait())->toBeTrue();

    $page = ViaUser::orderBy('id')->via()->paginate(2)->wait();
    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(3)->and($page->items())->toHaveCount(2);
});

it('the base query builder offloads too', function () {
    expect($this->app['db']->table('users')->where('id', 1)->via()->value('name')->wait())->toBe('ada')
        ->and($this->app['db']->table('users')->via()->pluck('name')->wait()->all())->toBe(['ada', 'bob', 'cy']);
});

it('retrieved fires once, here', function () {
    $seen = [];
    ViaUser::retrieved(function (ViaUser $u) use (&$seen) { $seen[] = $u->name; });

    ViaUser::orderBy('id')->via()->get()->wait();

    expect($seen)->toBe(['ada', 'bob', 'cy']);
});

it('eager models are retrieved once, here', function () {
    $users = [];
    $posts = [];
    ViaUser::retrieved(function (ViaUser $u) use (&$users) { $users[] = $u->name; });
    ViaPost::retrieved(function (ViaPost $p) use (&$posts) { $posts[] = $p->title; });

    ViaUser::orderBy('id')->with('posts')->via()->get()->wait();

    expect($users)->toBe(['ada', 'bob', 'cy'])->and($posts)->toBe(['a1', 'b1']);
});

it('a write terminal still fires its model event', function () {
    $seen = [];
    ViaUser::created(function (ViaUser $u) use (&$seen) { $seen[] = $u->name; });

    ViaUser::via()->create(['name' => 'dora'])->wait();

    expect($seen)->toBe(['dora']);
});

it('via() names its target, and the builder itself stays blocking', function () {
    expect(ViaUser::via('defer')->count())->toBeInstanceOf(Promise::class)
        ->and(fn () => ViaUser::via('telepathy'))->toThrow(InvalidArgumentException::class)
        ->and(ViaUser::count())->toBe(3);
});

it('refuses terminals that take a callback or yield', function () {
    foreach (['cursor', 'lazy', 'chunk', 'chunkById', 'each', 'lazyById'] as $method) {
        expect(fn () => ViaUser::via()->{$method}(10))->toThrow(BadMethodCallException::class, 'stream()');
    }
});

it('unnamed connection refuses via()', function () {
    $bare = new Connection(new PDO('sqlite::memory:'));

    expect(fn () => $bare->table('users')->via())->toThrow(LogicException::class, 'named connection');
});
