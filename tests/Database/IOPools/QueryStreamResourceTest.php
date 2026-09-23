<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\MailCollection;
use Voyager\Contracts\IOPools\Receivable;
use Voyager\Core\RenderedInstance;
use Voyager\Database\DatabaseServiceProvider;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\IOPools\ModelChunk;
use Voyager\Database\IOPools\QueryStreamResource;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\WorkTargetManager;

class StreamRow extends Model { protected $table = 'rows'; public $timestamps = false; protected $guarded = []; }

beforeEach(function () {
    $this->handler = new class implements Receivable {
        public array $chunks = [];
        public function handOff(MailCollection $mail): void { foreach ($mail->mail() as $m) if ($m instanceof ModelChunk) $this->chunks[] = $m; }
    };
    $this->app = RenderedInstance::setInstance(new RenderedInstance(dirname(__DIR__, 3)));
    $this->app->registerInstance('config', new Repository([
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'io-pools' => ['work' => ['default' => 'sync']],
    ]));
    $this->app->registerInstance(Loop::class, $this->loop = new EventLoop(mail_handler: $this->handler));
    $this->app->registerInstance('work-targets', new WorkTargetManager($this->app));
    $this->app->register(new DatabaseServiceProvider($this->app));
    Model::setConnectionResolver($this->app['db']);
    $this->app['db']->connection()->getSchemaBuilder()->create('rows', function ($t) { $t->increments('id'); $t->string('v'); });
    StreamRow::insert(array_map(fn ($i) => ['v' => "r$i"], range(1, 25)));
});

afterEach(fn () => RenderedInstance::setInstance(null));

it('delivers the rows as ordered chunks and settles with the count', function () {
    $stream = StreamRow::query()->stream(10);
    $total = null;
    $stream->done()->then(function (int $n) use (&$total) { $total = $n; });

    $this->loop->run();

    $chunks = $this->handler->chunks;
    expect($stream)->toBeInstanceOf(QueryStreamResource::class)
        ->and($total)->toBe(25)
        ->and(count($chunks))->toBe(3)
        ->and(array_map(fn (ModelChunk $c) => $c->page, $chunks))->toBe([1, 2, 3])
        ->and($chunks[0]->rows)->toBeInstanceOf(Collection::class)->toHaveCount(10)
        ->and($chunks[0]->rows->first())->toBeInstanceOf(StreamRow::class)
        ->and($chunks[2]->rows)->toHaveCount(5)
        ->and($chunks[2]->last)->toBeTrue()
        ->and($chunks[1]->last)->toBeFalse()
        ->and($chunks[2]->rows->last()->id)->toBe(25);
});

it('an exact multiple ends with a full last page and no empty chunk', function () {
    StreamRow::whereIn('id', [21, 22, 23, 24, 25])->delete();
    $stream = StreamRow::query()->stream(10);

    $this->loop->run();

    expect(count($this->handler->chunks))->toBe(2)
        ->and($this->handler->chunks[1]->last)->toBeTrue()
        ->and($stream->done()->wait())->toBe(20);
});

it('empty stream', function () {
    StreamRow::query()->delete();
    $stream = StreamRow::query()->stream(10);

    $this->loop->run();

    expect($this->handler->chunks)->toBe([])
        ->and($stream->done()->wait())->toBe(0);
});

it('respects the builder\'s wheres and the base builder streams stdClass rows', function () {
    $stream = $this->app['db']->table('rows')->where('id', '>', 20)->stream(2);

    $this->loop->run();

    expect(array_sum(array_map(fn (ModelChunk $c) => $c->rows->count(), $this->handler->chunks)))->toBe(5)
        ->and($this->handler->chunks[0]->rows->first())->toBeInstanceOf(stdClass::class)
        ->and($stream->done()->wait())->toBe(5);
});
