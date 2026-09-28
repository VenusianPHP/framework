<?php

use Voyager\Config\Repository;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\Vessel\ControlPanel;
use Voyager\IOPools\ResourceRegistry;
use Voyager\Signals\SignalDispatcher;
use Voyager\IOPools\Resources\Pollable;
use Voyager\Contracts\IOPools\MailHandler;
use Voyager\Contracts\IOPools\IOPoolsException;
use Voyager\IOPools\MailHandlers\SignalMailHandler;
use Voyager\IOPools\MailHandlers\SketchMailHandler;
use Voyager\IOPools\MailHandlers\MailHandlerManager;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Venusian\Tests\IOPools\Fixtures\MailSketch;
use Venusian\Tests\IOPools\Fixtures\PingSignal;

function mailLoop(MailHandler $mail_handler): EventLoop
{
    $registry = new ResourceRegistry();

    return new EventLoop($registry, new LoopWaiter($registry, new StreamSelectWaiterBackend(), 5_000_000), new GuzzlePromiseEngine(), $mail_handler);
}

/** A container whose config holds the given io-pools block, installed as the global instance for config(). */
function mailVessel(array $io_pools = []): ControlPanel
{
    $vessel = new ControlPanel();
    $vessel->registerInstance('config', new Repository(['io-pools' => $io_pools]));
    ControlPanel::setInstance($vessel);

    return $vessel;
}

afterEach(fn () => ControlPanel::setInstance(null));

it('dispatches every piece of mail through Signals in arrival order', function () {
    $signals = new SignalDispatcher();
    $heard = [];
    $signals->listen(PingSignal::class, function (PingSignal $ping) use (&$heard) {
        $heard[] = $ping->from;
    });

    $loop = mailLoop(new SignalMailHandler(fn () => $signals));
    $loop->post(new PingSignal('first'));
    $loop->post(new PingSignal('second'));
    $loop->at(0.01, fn () => $loop->stop());
    $loop->run();

    expect($heard)->toBe(['first', 'second']);
});

it('resolves the dispatcher at hand-off, not when the handler is built', function () {
    $signals = null;
    $heard = [];
    $handler = new SignalMailHandler(function () use (&$signals) { return $signals; });

    $signals = new SignalDispatcher();
    $signals->listen(PingSignal::class, function (PingSignal $ping) use (&$heard) {
        $heard[] = $ping->from;
    });

    $loop = mailLoop($handler);
    $loop->post(new PingSignal('late'));
    $loop->at(0.01, fn () => $loop->stop());
    $loop->run();

    expect($heard)->toBe(['late']);
});

it('passes all of a turn\'s mail to the sketch\'s loop() in one call', function () {
    $sketch = new MailSketch();
    $handler = new SketchMailHandler();
    $handler->attach($sketch);

    $loop = mailLoop($handler);
    $loop->post(new PingSignal('a'));
    $loop->post(new PingSignal('b'));
    $loop->at(0.01, fn () => $loop->stop());
    $loop->run();

    expect($sketch->loops)->toHaveCount(1)
        ->and(array_map(fn (PingSignal $p) => $p->from, $sketch->loops[0]))->toBe(['a', 'b']);
});

it('stops the loop when the sketch answers STOP', function () {
    $sketch = new MailSketch(stop_after: 1);
    $handler = new SketchMailHandler();
    $handler->attach($sketch);

    $loop = mailLoop($handler);
    $loop->resource('work', new class extends Pollable {
        public function tick(): void {}
    });
    $loop->at(0.01, fn () => $loop->post(new PingSignal('bye')));
    $started = hrtime(true);

    expect($loop->run())->toBe(0)
        ->and($sketch->loops)->toHaveCount(1)
        ->and(hrtime(true) - $started)->toBeLessThan(1_000_000_000);
});

it('refuses to drop mail when no sketch is attached', function () {
    $handler = new SketchMailHandler();
    $loop = mailLoop($handler);
    $loop->post(new PingSignal('lost'));
    $loop->at(0.01, fn () => $loop->stop());

    expect(fn () => $loop->run())->toThrow(IOPoolsException::class, 'no sketch attached');
});

it('detaches a sketch', function () {
    $handler = new SketchMailHandler();
    $handler->attach(new MailSketch());
    $handler->detach();

    expect($handler->sketch())->toBeNull();
});

it('builds the signal and sketch drivers by name, once each', function () {
    $manager = new MailHandlerManager(mailVessel());

    expect($manager->driver('signal'))->toBeInstanceOf(SignalMailHandler::class)
        ->and($manager->driver('sketch'))->toBeInstanceOf(SketchMailHandler::class)
        ->and($manager->driver('sketch'))->toBe($manager->driver('sketch'));
});

it('defaults to the signal driver and follows io-pools.event_loop.mail_handlers.default', function () {
    expect(new MailHandlerManager(mailVessel())->driver())->toBeInstanceOf(SignalMailHandler::class);

    $manager = new MailHandlerManager(mailVessel(['event_loop' => ['mail_handlers' => ['default' => 'sketch']]]));

    expect($manager->driver())->toBeInstanceOf(SketchMailHandler::class)
        ->and($manager->driver())->toBe($manager->driver('sketch'));
});

it('lets the signal driver reach the container\'s dispatcher', function () {
    $vessel = mailVessel();
    $signals = new SignalDispatcher($vessel);
    $vessel->registerInstance('signals', $signals);
    $heard = [];
    $signals->listen(PingSignal::class, function (PingSignal $ping) use (&$heard) {
        $heard[] = $ping->from;
    });

    $loop = mailLoop(new MailHandlerManager($vessel)->driver('signal'));
    $loop->post(new PingSignal('wired'));
    $loop->at(0.01, fn () => $loop->stop());
    $loop->run();

    expect($heard)->toBe(['wired']);
});
