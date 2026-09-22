<?php

use Venusian\Tests\Signals\Fixtures\HardwareSignal;
use Venusian\Tests\Signals\Fixtures\OrderShipped;
use Venusian\Tests\Signals\Fixtures\PinChanged;
use Venusian\Tests\Signals\Fixtures\RecordingListener;
use Venusian\Tests\Signals\Fixtures\SelfNamed;
use Voyager\Contracts\IOPools\Pumpable;
use Voyager\Contracts\IOPools\Tickable;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\MailHandlers\DispatchEverythingHandler;
use Voyager\Signals\SignalDispatcher;
use Voyager\Vessel\ControlPanel;

function dispatcher(): SignalDispatcher
{
    return new SignalDispatcher(new ControlPanel);
}

it('dispatches a named signal to a listener on its exact name', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', function (PinChanged $s) use (&$heard) { $heard[] = $s->pin; });
    $signals->listen('gpio.pin18.rising', function () use (&$heard) { $heard[] = 'wrong pin'; });

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe([17]);
});

it('dispatches a named signal to a wildcard on its name', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen('gpio.*', function (string $name, array $payload) use (&$heard) {
        $heard[] = $name.'/'.$payload[0]->edge;
    });

    $signals->dispatch(new PinChanged(17, 'rising'));
    $signals->dispatch(new PinChanged(4, 'falling'));

    expect($heard)->toBe(['gpio.pin17.rising/rising', 'gpio.pin4.falling/falling']);
});

it('dispatches a named signal to a listener on its class', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen(PinChanged::class, function (PinChanged $s) use (&$heard) { $heard[] = $s->name(); });

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(['gpio.pin17.rising']);
});

it('dispatches a named signal to a listener on an interface it implements', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen(HardwareSignal::class, function ($s) use (&$heard) { $heard[] = $s->name(); });

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(['gpio.pin17.rising']);
});

it('reaches name, wildcard, class and interface listeners in one dispatch', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', function () use (&$heard) { $heard[] = 'name'; });
    $signals->listen(PinChanged::class, function () use (&$heard) { $heard[] = 'class'; });
    $signals->listen(HardwareSignal::class, function () use (&$heard) { $heard[] = 'interface'; });
    $signals->listen('gpio.*', function () use (&$heard) { $heard[] = 'wildcard'; });

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(['name', 'class', 'interface', 'wildcard']);
});

it('calls a listener once when the signal name IS its class', function () {
    $heard = 0;
    $signals = dispatcher();

    $signals->listen(SelfNamed::class, function () use (&$heard) { $heard++; });

    $signals->dispatch(new SelfNamed);

    expect($heard)->toBe(1);
});

it('calls a wildcard once when it matches both the name and the class', function () {
    $heard = 0;
    $signals = dispatcher();

    $signals->listen('*', function () use (&$heard) { $heard++; });

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(1);
});

it('dispatches an arbitrary name with a payload and no class at all', function () {
    $heard = null;
    $signals = dispatcher();

    $signals->listen('app.ready', function (array $payload) use (&$heard) { $heard = $payload; });

    $signals->dispatch('app.ready', [['boot_ms' => 41]]);

    expect($heard)->toBe(['boot_ms' => 41]);
});

it('does not give a name dispatch the class listeners of its payload', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen(PinChanged::class, function () use (&$heard) { $heard[] = 'class'; });
    $signals->listen('custom.name', function () use (&$heard) { $heard[] = 'name'; });

    // The object is cargo here, not the signal.
    $signals->dispatch('custom.name', new PinChanged(17, 'rising'));

    expect($heard)->toBe(['name']);
});

it('still dispatches an unnamed signal by its class, the Laravel way', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen(OrderShipped::class, function (OrderShipped $s) use (&$heard) { $heard[] = $s->order; });

    $signals->dispatch(new OrderShipped('A-9'));

    expect($heard)->toBe(['A-9']);
});

it('resolves a class listener out of the container', function () {
    RecordingListener::$heard = [];
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', RecordingListener::class);

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect(RecordingListener::$heard)->toBe(['class-listener:gpio.pin17.rising']);
});

it('stops at the first answer under until()', function () {
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', fn () => null);
    $signals->listen(PinChanged::class, fn () => 'first answer');
    $signals->listen(HardwareSignal::class, fn () => 'too late');

    expect($signals->until(new PinChanged(17, 'rising')))->toBe('first answer');
});

it('stops the rest of the chain when a listener answers false', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', function () use (&$heard) { $heard[] = 'first'; return false; });
    $signals->listen(PinChanged::class, function () use (&$heard) { $heard[] = 'never'; });

    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(['first']);
});

it('collects every listener answer in order', function () {
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', fn () => 'a');
    $signals->listen(PinChanged::class, fn () => 'b');

    expect($signals->dispatch(new PinChanged(17, 'rising')))->toBe(['a', 'b']);
});

it('forgets a name without touching the class listeners', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->listen('gpio.pin17.rising', function () use (&$heard) { $heard[] = 'name'; });
    $signals->listen(PinChanged::class, function () use (&$heard) { $heard[] = 'class'; });

    $signals->forget('gpio.pin17.rising');
    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(['class']);
});

it('sees a wildcard registered after an earlier dispatch cached the lookup', function () {
    $heard = [];
    $signals = dispatcher();

    $signals->dispatch(new PinChanged(17, 'rising'));          // primes the wildcard cache

    $signals->listen('gpio.*', function () use (&$heard) { $heard[] = 'late'; });
    $signals->dispatch(new PinChanged(17, 'rising'));

    expect($heard)->toBe(['late']);
});

it('carries loop mail to name, wildcard and class listeners in one run', function () {
    $vessel = new ControlPanel;
    $signals = new SignalDispatcher($vessel);
    $vessel->registerInstance('signals', $signals);
    ControlPanel::setInstance($vessel);

    $heard = [];
    $signals->listen('gpio.pin17.rising', function () use (&$heard) { $heard[] = 'name'; });
    $signals->listen('gpio.*', function (string $name) use (&$heard) { $heard[] = 'wildcard:'.$name; });
    $signals->listen(PinChanged::class, function () use (&$heard) { $heard[] = 'class'; });

    $loop = new EventLoop(mail_handler: new DispatchEverythingHandler);

    // A resource that drops one piece of mail on its first tick, then retires.
    $loop->resource('pin', new class($loop) implements Tickable, Pumpable {
        private array $queued = [];

        public function __construct(private EventLoop $loop) {}

        public function tick(): void
        {
            $this->queued[] = new PinChanged(17, 'rising');
            $this->loop->forget('pin');
        }

        public function pump(): array
        {
            $mail = $this->queued;
            $this->queued = [];

            return $mail;
        }
    });

    $loop->run();

    expect($heard)->toBe(['name', 'class', 'wildcard:gpio.pin17.rising']);

    ControlPanel::setInstance(null);
});
