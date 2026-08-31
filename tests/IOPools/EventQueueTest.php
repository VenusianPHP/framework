<?php

use Voyager\IOPools\Event;
use Voyager\IOPools\EventQueue;
use Voyager\IOPools\TickRoster;
use Voyager\Contracts\IOPools\Tickable;

describe('event queue', function () {
    test('drains pushed events keyed by name and empties', function () {
        $queue = new EventQueue();
        $queue->push(new Event('task', 'a', ['n' => 1]));
        $queue->push(new Event('task', 'b'));

        $events = $queue->drain();

        expect($events)->toHaveCount(2)
            ->and($events->has('a'))->toBeTrue()
            ->and($events->get('a')->payload)->toBe(['n' => 1])
            ->and($queue->drain())->toHaveCount(0);
    });

    test('same-name pushes within one tick collapse to the last', function () {
        $queue = new EventQueue();
        $queue->push(new Event('task', 'a', ['v' => 'first']));
        $queue->push(new Event('task', 'a', ['v' => 'second']));

        expect($queue->drain()->get('a')->payload['v'])->toBe('second');
    });
});

describe('tick roster', function () {
    test('pumps registered tickables in registration order', function () {
        $order = [];
        $make = function (string $tag) use (&$order): Tickable {
            return new class($tag, $order) implements Tickable {
                public function __construct(protected string $tag, protected array &$order) {}
                public function tick(): void { $this->order[] = $this->tag; }
            };
        };
        $roster = new TickRoster();
        $roster->register($make('a'))->register($make('b'));

        $roster->tick();
        $roster->tick();

        expect($order)->toBe(['a', 'b', 'a', 'b']);
    });
});
