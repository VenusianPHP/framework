<?php

use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\ResourceRegistry;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Filesystem\Offloading\PathLanes;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;

beforeEach(function () {
    $registry = new ResourceRegistry();
    $this->loop = new EventLoop($registry, new LoopWaiter($registry, new StreamSelectWaiterBackend(), 5_000_000), new GuzzlePromiseEngine());
    $this->lanes = PathLanes::forDisk($this->loop);
    $this->started = [];
    $this->sent = [];
});

/** A call that starts when the lanes let it, and settles when the test says so. */
function lane(object $test, string $name, array $paths): Promise
{
    return $test->lanes->run($paths, function () use ($test, $name): Promise {
        $test->started[] = $name;

        return $test->sent[$name] = $test->loop->promise();
    });
}

/** Settles a started call, and lets the loop carry the news to the lanes. */
function finish(object $test, string $name): void
{
    $test->sent[$name]->resolve($name);
    $test->loop->at(0, fn () => null);
    $test->loop->run();
}

it('runs calls on unrelated paths side by side, and one path\'s calls one at a time', function () {
    lane($this, 'write a', ['a.txt']);
    lane($this, 'write b', ['b.txt']);
    lane($this, 'read a', ['a.txt']);

    expect($this->started)->toBe(['write a', 'write b']);

    finish($this, 'write a');

    expect($this->started)->toBe(['write a', 'write b', 'read a']);
});

it('holds everything under a directory for a call on the directory, and the other way round', function () {
    lane($this, 'write inside', ['docs/readme.md']);
    lane($this, 'delete the directory', ['docs']);
    lane($this, 'write beside', ['docsx/other.md']);
    lane($this, 'list the root', ['']);

    expect($this->started)->toBe(['write inside', 'write beside']);

    finish($this, 'write inside');
    expect($this->started)->toBe(['write inside', 'write beside', 'delete the directory']);

    finish($this, 'delete the directory');
    expect($this->started)->toBe(['write inside', 'write beside', 'delete the directory']);

    finish($this, 'write beside');
    expect($this->started)->toBe(['write inside', 'write beside', 'delete the directory', 'list the root']);
});

it('never lets a later call overtake an earlier one waiting on the same path', function () {
    lane($this, 'move a to b', ['a.txt', 'b.txt']);
    lane($this, 'read b', ['b.txt']);
    lane($this, 'write c', ['c.txt']);

    expect($this->started)->toBe(['move a to b', 'write c']);

    finish($this, 'move a to b');

    expect($this->started)->toBe(['move a to b', 'write c', 'read b']);
});

it('settles the promise run() handed out with the call\'s own outcome', function () {
    $written = lane($this, 'write', ['a.txt']);

    finish($this, 'write');

    expect($written->wait())->toBe('write');
});

it('compares paths in their normal form', function () {
    lane($this, 'write', ['./docs//readme.md']);
    lane($this, 'read', ['docs/sub/../readme.md']);

    expect($this->started)->toBe(['write']);
});

it('cuts a glob pattern back to the directory it can match in', function () {
    expect(PathLanes::globRoot('/var/log/*.log'))->toBe('/var/log')
        ->and(PathLanes::globRoot('/var/log/app-*.log'))->toBe('/var/log')
        ->and(PathLanes::globRoot('/var/log/app.log'))->toBe('/var/log/app.log')
        ->and(PathLanes::globRoot('*.log'))->toBe('.');
});
