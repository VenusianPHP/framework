<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\DeferredRepository;
use Voyager\Cache\Repository;
use Voyager\Contracts\IOPools\Promise;
use Voyager\IOPools\EventLoop;

function deferredCache(EventLoop $loop): array
{
    $store = new ArrayStore;
    $repo = new Repository($store);
    $repo->setLoop($loop);

    return [$repo, $store];
}

it('put() lands on the next turn, and get() sees it after', function () {
    $loop = new EventLoop;
    [$repo, $store] = deferredCache($loop);

    $put = $repo->defer()->put('k', 'v', 60);

    expect($put)->toBeInstanceOf(Promise::class)
        ->and($store->get('k'))->toBeNull()                // deferred means deferred
        ->and($put->wait())->toBeTrue()
        ->and($repo->defer()->get('k')->wait())->toBe('v')
        ->and($repo->get('k'))->toBe('v');                // the sync path sees the same store
});

it('rejects when the store throws', function () {
    $loop = new EventLoop;
    [$repo] = deferredCache($loop);

    $promise = $repo->defer()->remember('k', 60, fn () => throw new RuntimeException('no value'));

    expect(fn () => $promise->wait())->toThrow(RuntimeException::class, 'no value');
});

it('overlaps under async(): two deferred gets resolve in one turn', function () {
    $loop = new EventLoop;
    [$repo] = deferredCache($loop);
    $repo->put('a', 1, 60);
    $repo->put('b', 2, 60);

    $sum = $loop->async(fn () => $repo->defer()->get('a')->wait() + $repo->defer()->get('b')->wait());

    expect($sum->wait())->toBe(3);
});

it('hands back a DeferredRepository from the manager\'s store too', function () {
    $loop = new EventLoop;
    $app = new \Voyager\Vessel\ControlPanel;
    $app->registerInstance('config', new \Voyager\Config\Repository(['cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]]));
    $app->registerInstance(\Voyager\Contracts\IOPools\Loop::class, $loop);

    $manager = new \Voyager\Cache\CacheManager($app);

    expect($manager->store()->defer())->toBeInstanceOf(DeferredRepository::class)
        ->and($manager->store()->defer()->put('x', 1, 60)->wait())->toBeTrue();
});
