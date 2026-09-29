<?php

use Voyager\Pipeline\Hub;
use Voyager\Pipeline\Pipeline;
use Voyager\Contracts\Pipeline\Hub as HubContract;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Pipeline\Fixtures\AppendPipe;
use Venusian\Tests\Pipeline\Fixtures\InvokablePipe;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('binds a fresh pipeline per ask and one hub', function () {
    expect($this->app->make('pipeline'))->toBeInstanceOf(Pipeline::class)
        ->and($this->app->make('pipeline'))->not->toBe($this->app->make('pipeline'))
        ->and($this->app->make(HubContract::class))->toBeInstanceOf(Hub::class)
        ->and($this->app->make(HubContract::class))->toBe($this->app->make(HubContract::class));
});

it('sends the passable through closure, class-string, parameterized and object pipes in order', function () {
    $result = $this->app->make('pipeline')
        ->send('a')
        ->through([
            fn (string $passable, Closure $next) => $next($passable.'b'),
            AppendPipe::class,
            AppendPipe::class.':c',
            new InvokablePipe(),
        ])
        ->then(fn (string $passable) => "done: {$passable}");

    expect($result)->toBe('done: [ab!c]');
});

it('takes the pipes as arguments, and adds more with pipe()', function () {
    $result = $this->app->make('pipeline')
        ->send('a')
        ->through(AppendPipe::class, AppendPipe::class.':b')
        ->pipe(AppendPipe::class.':c')
        ->thenReturn();

    expect($result)->toBe('a!bc');
});

it('calls the method via() names on each pipe', function () {
    expect($this->app->make('pipeline')->send('a')->through([AppendPipe::class])->via('differently')->thenReturn())->toBe('a?');
});

it('stops where a pipe doesn\'t call the next one', function () {
    $reached = false;

    $result = $this->app->make('pipeline')
        ->send('a')
        ->through([fn (string $passable, Closure $next) => 'short-circuited', AppendPipe::class])
        ->then(function () use (&$reached) {
            $reached = true;
        });

    expect($result)->toBe('short-circuited')
        ->and($reached)->toBeFalse();
});

it('runs finally() with the passable it was sent, whether the pipes finish or throw', function () {
    $seen = [];

    $this->app->make('pipeline')->send('ok')->through([AppendPipe::class])
        ->finally(function ($passable) use (&$seen) { $seen[] = $passable; })
        ->thenReturn();

    expect(function () use (&$seen) {
        $this->app->make('pipeline')->send('boom')
            ->through([fn () => throw new RuntimeException('pipe failed')])
            ->finally(function ($passable) use (&$seen) { $seen[] = $passable; })
            ->thenReturn();
    })->toThrow(RuntimeException::class, 'pipe failed');

    expect($seen)->toBe(['ok', 'boom']);
});

it('runs a pipeline built without the container when every pipe is a closure or an object', function () {
    expect(new Pipeline()->send(2)->through([fn (int $n, Closure $next) => $next($n * 10)])->thenReturn())->toBe(20);
});

it('needs the container to resolve a class-string pipe', function () {
    expect(fn () => new Pipeline()->send('a')->through([AppendPipe::class])->thenReturn())
        ->toThrow(RuntimeException::class, 'A container instance has not been passed to the Pipeline.');
});

it('sends objects through the hub\'s default and named pipelines', function () {
    $hub = $this->app->make(HubContract::class);
    $hub->defaults(fn (Pipeline $pipeline, string $object) => $pipeline->send($object)->through([AppendPipe::class])->thenReturn());
    $hub->pipeline('shout', fn (Pipeline $pipeline, string $object) => $pipeline->send($object)->through([fn ($o, $next) => $next(strtoupper($o))])->thenReturn());

    expect($hub->pipe('hi'))->toBe('hi!')
        ->and($hub->pipe('hi', 'shout'))->toBe('HI');
});
