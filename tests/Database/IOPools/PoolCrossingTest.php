<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\MailCollection;
use Voyager\Contracts\IOPools\Receivable;
use Voyager\Contracts\IOPools\RemoteException;
use Voyager\Contracts\IOPools\WorkerPool;
use Voyager\Core\RenderedInstance;
use Voyager\Database\DatabaseServiceProvider;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\IOPools\ModelChunk;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\ProcessPool;
use Voyager\IOPools\WorkTargetManager;
use Voyager\Signals\SignalDispatcher;
use Venusian\Tests\Database\IOPools\PoolPost;
use Venusian\Tests\Database\IOPools\PoolUser;

beforeEach(function () {
    // the worker boots THIS repo and reads config/database.php; DB_DATABASE points both sides at one file
    $this->root = dirname(__DIR__, 3);
    $this->file = $this->root.'/storage/app/vf-db-'.getmypid().'.sqlite';
    touch($this->file);
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE='.$this->file);

    $this->handler = new class implements Receivable {
        public array $chunks = [];
        public function handOff(MailCollection $mail): void { foreach ($mail->mail() as $m) if ($m instanceof ModelChunk) $this->chunks[] = $m; }
    };
    $this->app = RenderedInstance::setInstance(new RenderedInstance($this->root));
    $this->app->registerInstance('config', new Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $this->file, 'prefix' => '', 'foreign_key_constraints' => true]]],
        'io-pools' => ['work' => ['default' => 'pool']],
    ]));
    $this->app->registerInstance(Loop::class, $this->loop = new EventLoop(mail_handler: $this->handler));
    $this->app->registerInstance('signals', $signals = new SignalDispatcher($this->app));
    $this->app->registerInstance(WorkerPool::class, $this->pool = new ProcessPool($this->loop, [PHP_BINARY, $this->root.'/src/Voyager/IOPools/bin/pool-worker', $this->root.'/vendor/autoload.php', $this->root], 2));
    $this->app->registerInstance('work-targets', new WorkTargetManager($this->app));
    $this->app->register(new DatabaseServiceProvider($this->app));
    Model::setConnectionResolver($this->app['db']);
    Model::setEventDispatcher($signals);

    $schema = $this->app['db']->connection()->getSchemaBuilder();
    $schema->create('users', function ($t) { $t->increments('id'); $t->string('name'); });
    $schema->create('posts', function ($t) { $t->increments('id'); $t->integer('user_id'); $t->string('title'); });
    PoolUser::insert(array_map(fn ($i) => ['name' => "u$i"], range(1, 12)));
    PoolPost::insert([['user_id' => 1, 'title' => 'p1'], ['user_id' => 1, 'title' => 'p2']]);
});

afterEach(function () {
    $this->pool->shutDown();
    PoolUser::flushEventListeners();
    RenderedInstance::setInstance(null);
    putenv('DB_DATABASE'); putenv('DB_CONNECTION');
    @unlink($this->file);
});

it('a query crosses to a worker and comes back hydrated with its eager loads', function () {
    $seen = 0;
    PoolUser::retrieved(function () use (&$seen) { $seen++; });

    $users = PoolUser::with('posts')->orderBy('id')->via()->get()->wait();

    expect($users)->toBeInstanceOf(Collection::class)->toHaveCount(12)
        ->and($users->first())->toBeInstanceOf(PoolUser::class)
        ->and($users->first()->posts->pluck('title')->all())->toBe(['p1', 'p2'])
        ->and($users->first()->exists)->toBeTrue()
        ->and($seen)->toBe(12);
});

it('bad sql rejects', function () {
    $failed = null;
    $this->app['db']->connection()->via()->select('select * from no_such_table')->error(function (Throwable $e) use (&$failed) { $failed = $e; });

    $this->loop->run();

    expect($failed)->toBeInstanceOf(RemoteException::class)
        ->and($failed->getMessage())->toContain('no_such_table');
});

it('streams across workers in page order', function () {
    $stream = PoolUser::query()->stream(5);

    $this->loop->run();

    expect($stream->done()->wait())->toBe(12)
        ->and(array_map(fn (ModelChunk $c) => $c->page, $this->handler->chunks))->toBe([1, 2, 3])
        ->and($this->handler->chunks[2]->rows->pluck('id')->all())->toBe([11, 12]);
});
