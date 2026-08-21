<?php

use Tests\Events\Fixtures\AnotherEvent;
use Tests\Events\Fixtures\DeferTestEvent;
use Tests\Events\Fixtures\DispatchableNamedArgumentsEvent;
use Tests\Events\Fixtures\ExampleEvent;
use Tests\Events\Fixtures\ImmediateTestEvent;
use Tests\Events\Fixtures\SomeEventInterface;
use Tests\Events\Fixtures\TestEvent;
use Tests\Events\Fixtures\TestEventListener;
use Tests\Events\Fixtures\TestListener;
use Tests\Events\Fixtures\TestListener1;
use Tests\Events\Fixtures\TestListener2;
use Tests\Events\Fixtures\TestListener2Falser;
use Tests\Events\Fixtures\TestListener3;
use Tests\Events\Fixtures\TestListenerInvokey;
use Tests\Events\Fixtures\TestListenerInvokeyHandler;
use Tests\Events\Fixtures\TestListenerLean;
use Voyager\Events\Dispatcher;
use Voyager\Vessel\Vessel;

test('basic event execution', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'] = $foo;
        });
        $response = $d->dispatch('foo', ['bar']);

        expect($response)->toEqual([null]);
        expect($_SERVER['__event.test'])->toBe('bar');

        // we can still add listeners after the event has fired
        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'] .= $foo;
        });

        $d->dispatch('foo', ['bar']);
        expect($_SERVER['__event.test'])->toBe('barbar');
    });

test('defer event execution', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'] = $foo;
        });

        $result = $d->defer(function () use ($d) {
            $d->dispatch('foo', ['bar']);
            expect($_SERVER)->not->toHaveKey('__event.test');

            return 'callback_result';
        });

        expect($result)->toEqual('callback_result');
        expect($_SERVER['__event.test'])->toBe('bar');
    });

test('defer multiple events', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen('foo', function ($value) {
            $_SERVER['__event.test'][] = $value;
        });
        $d->listen('bar', function ($value) {
            $_SERVER['__event.test'][] = $value;
        });
        $d->defer(function () use ($d) {
            $d->dispatch('foo', ['foo']);
            $d->dispatch('bar', ['bar']);
            expect($_SERVER['__event.test'])->toBe([]);
        });

        expect($_SERVER['__event.test'])->toBe(['foo', 'bar']);
    });

test('defer nested events', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'][] = $foo;
        });

        $d->defer(function () use ($d) {
            $d->dispatch('foo', ['outer1']);

            $d->defer(function () use ($d) {
                $d->dispatch('foo', ['inner']);
                expect($_SERVER['__event.test'])->toBe([]);
            });

            expect($_SERVER['__event.test'])->toBe(['inner']);
            $d->dispatch('foo', ['outer2']);
        });

        expect($_SERVER['__event.test'])->toBe(['inner', 'outer1', 'outer2']);
    });

test('defer specific events', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;

        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'][] = $foo;
        });

        $d->listen('bar', function ($bar) {
            $_SERVER['__event.test'][] = $bar;
        });

        $d->defer(function () use ($d) {
            $d->dispatch('foo', ['deferred']);
            $d->dispatch('bar', ['immediate']);

            expect($_SERVER['__event.test'])->toBe(['immediate']);
        }, ['foo']);

        expect($_SERVER['__event.test'])->toBe(['immediate', 'deferred']);
    });

test('defer specific nested events', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;

        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'][] = $foo;
        });

        $d->listen('bar', function ($bar) {
            $_SERVER['__event.test'][] = $bar;
        });

        $d->defer(function () use ($d) {
            $d->dispatch('foo', ['outer-deferred']);
            $d->dispatch('bar', ['outer-immediate']);

            expect($_SERVER['__event.test'])->toBe(['outer-immediate']);

            $d->defer(function () use ($d) {
                $d->dispatch('foo', ['inner-deferred']);
                $d->dispatch('bar', ['inner-immediate']);

                expect($_SERVER['__event.test'])->toBe(['outer-immediate', 'inner-immediate']);
            }, ['foo']);

            expect($_SERVER['__event.test'])->toBe(['outer-immediate', 'inner-immediate', 'inner-deferred']);
        }, ['foo']);

        expect($_SERVER['__event.test'])->toBe(['outer-immediate', 'inner-immediate', 'inner-deferred', 'outer-deferred']);
    });

test('defer specific object events', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;

        $d->listen(DeferTestEvent::class, function () {
            $_SERVER['__event.test'][] = 'DeferTestEvent';
        });

        $d->listen(ImmediateTestEvent::class, function () {
            $_SERVER['__event.test'][] = 'ImmediateTestEvent';
        });

        $d->defer(function () use ($d) {
            $d->dispatch(new DeferTestEvent());
            $d->dispatch(new ImmediateTestEvent());

            expect($_SERVER['__event.test'])->toBe(['ImmediateTestEvent']);
        }, [DeferTestEvent::class]);

        expect($_SERVER['__event.test'])->toBe(['ImmediateTestEvent', 'DeferTestEvent']);
    });

test('halting event execution', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo', function ($foo) {
            expect(true)->toBeTrue();

            return 'here';
        });
        $d->listen('foo', function ($foo) {
            throw new Exception('should not be called');
        });

        $response = $d->dispatch('foo', ['bar'], true);
        expect($response)->toBe('here');

        $response = $d->until('foo', ['bar']);
        expect($response)->toBe('here');
    });

test('response when no listeners are set', function () {
        $d = new Dispatcher;
        $response = $d->dispatch('foo');

        expect($response)->toEqual([]);

        $response = $d->dispatch('foo', [], true);
        expect($response)->toBeNull();
    });

test('returning false stops propagation', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo', function ($foo) {
            return $foo;
        });

        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'] = $foo;

            return false;
        });

        $d->listen('foo', function ($foo) {
            throw new Exception('should not be called');
        });

        $response = $d->dispatch('foo', ['bar']);

        expect($_SERVER['__event.test'])->toBe('bar');
        expect($response)->toEqual(['bar']);
    });

test('returning falsy values continues propagation', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo', function () {
            return 0;
        });
        $d->listen('foo', function () {
            return [];
        });
        $d->listen('foo', function () {
            return '';
        });
        $d->listen('foo', function () {
        });

        $response = $d->dispatch('foo', ['bar']);

        expect($response)->toEqual([0, [], '', null]);
    });

test('container resolution of event handlers', function () {
        $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
        $vessel->shouldReceive('make')->once()->with(TestEventListener::class)->andReturn(new TestEventListener);
        $d->listen('foo', TestEventListener::class.'@onFooEvent');
        $response = $d->dispatch('foo', ['foo', 'bar']);

        expect($response)->toEqual(['baz']);
    });

test('container resolution of event handlers with default methods', function () {
        $d = new Dispatcher(new Vessel);
        $d->listen('foo', TestEventListener::class);
        $response = $d->dispatch('foo', ['foo', 'bar']);
        expect($response)->toEqual(['baz']);
    });

test('queued events are fired', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('update', function ($name) {
            $_SERVER['__event.test'] = $name;
        });
        $d->push('update', ['name' => 'taylor']);
        $d->listen('update', function ($name) {
            $_SERVER['__event.test'] .= '_'.$name;
        });

        expect(isset($_SERVER['__event.test']))->toBeFalse();
        $d->flush('update');
        $d->listen('update', function ($name) {
            $_SERVER['__event.test'] .= $name;
        });
        expect($_SERVER['__event.test'])->toBe('taylor_taylor');
    });

test('queued events can be forgotten', function () {
        $_SERVER['__event.test'] = 'unset';
        $d = new Dispatcher;
        $d->push('update', ['name' => 'taylor']);
        $d->listen('update', function ($name) {
            $_SERVER['__event.test'] = $name;
        });

        $d->forgetPushed();
        $d->flush('update');
        expect($_SERVER['__event.test'])->toBe('unset');
    });

test('multiple pushed events will get flushed', function () {
        $_SERVER['__event.test'] = '';
        $d = new Dispatcher;
        $d->push('update', ['name' => 'taylor ']);
        $d->push('update', ['name' => 'otwell']);
        $d->listen('update', function ($name) {
            $_SERVER['__event.test'] .= $name;
        });

        $d->flush('update');
        expect($_SERVER['__event.test'])->toBe('taylor otwell');
    });

test('push method can accept object as payload', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->push(ExampleEvent::class, $e = new ExampleEvent);
        $d->listen(ExampleEvent::class, function ($payload) {
            $_SERVER['__event.test'] = $payload;
        });

        $d->flush(ExampleEvent::class);

        expect($_SERVER['__event.test'])->toBe($e);
    });

test('wildcard listeners', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo.bar', function () {
            $_SERVER['__event.test'] = 'regular';
        });
        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'wildcard';
        });
        $d->listen('bar.*', function () {
            $_SERVER['__event.test'] = 'nope';
        });

        $response = $d->dispatch('foo.bar');

        expect($response)->toEqual([null, null]);
        expect($_SERVER['__event.test'])->toBe('wildcard');
    });

test('wildcard listeners with responses', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo.bar', function () {
            return 'regular';
        });
        $d->listen('foo.*', function () {
            return 'wildcard';
        });
        $d->listen('bar.*', function () {
            return 'nope';
        });

        $response = $d->dispatch('foo.bar');

        expect($response)->toEqual(['regular', 'wildcard']);
    });

test('wildcard listeners cache flushing', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'cached_wildcard';
        });
        $d->dispatch('foo.bar');
        expect($_SERVER['__event.test'])->toBe('cached_wildcard');

        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'new_wildcard';
        });
        $d->dispatch('foo.bar');
        expect($_SERVER['__event.test'])->toBe('new_wildcard');
    });

test('listeners can be removed', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo', function () {
            $_SERVER['__event.test'] = 'foo';
        });
        $d->forget('foo');
        $d->dispatch('foo');

        expect(isset($_SERVER['__event.test']))->toBeFalse();
    });

test('wildcard listeners can be removed', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'foo';
        });
        $d->forget('foo.*');
        $d->dispatch('foo.bar');

        expect(isset($_SERVER['__event.test']))->toBeFalse();
    });

test('wildcard cache is cleared when listeners are removed', function () {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;
        $d->listen('foo*', function () {
            $_SERVER['__event.test'] = 'foo';
        });
        $d->dispatch('foo');

        expect($_SERVER['__event.test'])->toBe('foo');

        unset($_SERVER['__event.test']);

        $d->forget('foo*');
        $d->dispatch('foo');

        expect(isset($_SERVER['__event.test']))->toBeFalse();
    });

test('has wildcard listeners', function () {
        $d = new Dispatcher;
        $d->listen('foo', 'listener1');
        expect($d->hasWildcardListeners('foo'))->toBeFalse();

        $d->listen('foo*', 'listener1');
        expect($d->hasWildcardListeners('foo'))->toBeTrue();
    });

test('listeners can be found', function () {
        $d = new Dispatcher;
        expect($d->hasListeners('foo'))->toBeFalse();

        $d->listen('foo', function () {
            //
        });
        expect($d->hasListeners('foo'))->toBeTrue();
    });

test('wildcard listeners can be found', function () {
        $d = new Dispatcher;
        expect($d->hasListeners('foo.*'))->toBeFalse();

        $d->listen('foo.*', function () {
            //
        });
        expect($d->hasListeners('foo.*'))->toBeTrue();
        expect($d->hasListeners('foo.bar'))->toBeTrue();
    });

test('event passed first to wildcards', function () {
        $d = new Dispatcher;
        $d->listen('foo.*', function ($event, $data) {
            expect($event)->toBe('foo.bar');
            expect($data)->toEqual(['first', 'second']);
        });
        $d->dispatch('foo.bar', ['first', 'second']);

        $d = new Dispatcher;
        $d->listen('foo.bar', function ($first, $second) {
            expect($first)->toBe('first');
            expect($second)->toBe('second');
        });
        $d->dispatch('foo.bar', ['first', 'second']);
    });

test('classes work', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen(ExampleEvent::class, function () {
            $_SERVER['__event.test'] = 'baz';
        });
        $d->dispatch(new ExampleEvent);

        expect($_SERVER['__event.test'])->toBe('baz');
    });

test('classes work with anonymous listeners', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen(function (ExampleEvent $event) {
            $_SERVER['__event.test'] = 'qux';
        });
        $d->dispatch(new ExampleEvent);

        expect($_SERVER['__event.test'])->toBe('qux');
    });

test('event classes are payload', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen(ExampleEvent::class, function ($payload) {
            $_SERVER['__event.test'] = $payload;
        });
        $d->dispatch($e = new ExampleEvent, ['foo']);

        expect($_SERVER['__event.test'])->toBe($e);
    });

test('interfaces work', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher;
        $d->listen(SomeEventInterface::class, function () {
            $_SERVER['__event.test'] = 'bar';
        });
        $d->dispatch(new AnotherEvent);

        expect($_SERVER['__event.test'])->toBe('bar');
    });

test('both classes and interfaces work', function () {
        unset($_SERVER['__event.test']);
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen(AnotherEvent::class, function ($p) {
            $_SERVER['__event.test'][] = $p;
            $_SERVER['__event.test1'] = 'fooo';
        });
        $d->listen(SomeEventInterface::class, function ($p) {
            $_SERVER['__event.test'][] = $p;
            $_SERVER['__event.test2'] = 'baar';
        });
        $d->dispatch($e = new AnotherEvent, ['foo']);

        expect($_SERVER['__event.test'][0])->toBe($e);
        expect($_SERVER['__event.test'][1])->toBe($e);
        expect($_SERVER['__event.test1'])->toBe('fooo');
        expect($_SERVER['__event.test2'])->toBe('baar');

        unset($_SERVER['__event.test1'], $_SERVER['__event.test2']);
    });

test('nested event', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;

        $d->listen('event', function () use ($d) {
            $d->listen('event', function () {
                $_SERVER['__event.test'][] = 'fired 1';
            });
            $d->listen('event', function () {
                $_SERVER['__event.test'][] = 'fired 2';
            });
        });

        $d->dispatch('event');
        expect($_SERVER['__event.test'])->toBe([]);
        $d->dispatch('event');
        expect($_SERVER['__event.test'])->toEqual(['fired 1', 'fired 2']);
    });

test('duplicate listeners will fire', function () {
        $d = new Dispatcher;
        $d->listen('event', TestListener::class);
        $d->listen('event', TestListener::class);
        $d->listen('event', TestListener::class.'@handle');
        $d->listen('event', TestListener::class.'@handle');
        $d->dispatch('event');

        expect(TestListener::$counter)->toEqual(4);
        TestListener::$counter = 0;
    });

test('get listeners', function () {
        $d = new Dispatcher;
        $d->listen(ExampleEvent::class, 'Listener1');
        $d->listen(ExampleEvent::class, 'Listener2');
        $listeners = $d->getListeners(ExampleEvent::class);
        expect($listeners)->toHaveCount(2);

        $d->listen(ExampleEvent::class, 'Listener3');
        $listeners = $d->getListeners(ExampleEvent::class);
        expect($listeners)->toHaveCount(3);
    });

test('listeners objects creation order', function () {
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen(TestEvent::class, TestListener1::class);
        $d->listen(TestEvent::class, TestListener2::class);
        $d->listen(TestEvent::class, TestListener3::class);

        // Attaching events does not make any objects.
        expect($_SERVER['__event.test'])->toEqual([]);

        $d->dispatch(TestEvent::class);

        // Dispatching event does not make an object of the event class.
        expect($_SERVER['__event.test'])->toEqual([
            'cons-1',
            'handle-1',
            'cons-2',
            'handle-2',
            'cons-3',
            'handle-3',
        ]);

        $d->dispatch(TestEvent::class);

        // Event Objects are re-resolved on each dispatch. (No memoization)
        expect($_SERVER['__event.test'])->toEqual([
            'cons-1',
            'handle-1',
            'cons-2',
            'handle-2',
            'cons-3',
            'handle-3',
            'cons-1',
            'handle-1',
            'cons-2',
            'handle-2',
            'cons-3',
            'handle-3',
        ]);

        unset($_SERVER['__event.test']);
    });

test('listener object creation is lazy', function () {
        $d = new Dispatcher;
        $d->listen(TestEvent::class, TestListener1::class);
        $d->listen(TestEvent::class, TestListener2Falser::class);
        $d->listen(TestEvent::class, TestListener3::class);
        $d->listen(ExampleEvent::class, TestListener2::class);

        $_SERVER['__event.test'] = [];
        $d->dispatch(ExampleEvent::class);

        // It only resolves relevant listeners not all.
        expect($_SERVER['__event.test'])->toEqual(['cons-2', 'handle-2']);

        $_SERVER['__event.test'] = [];
        $d->dispatch(TestEvent::class);

        expect($_SERVER['__event.test'])->toEqual([
            'cons-1',
            'handle-1',
            'cons-2-falser',
            'handle-2-falser',
        ]);

        unset($_SERVER['__event.test']);

        $d = new Dispatcher;
        $d->listen(TestEvent::class, TestListener1::class);
        $d->listen(TestEvent::class, TestListener2Falser::class);
        $d->listen(TestEvent::class, TestListener3::class);

        $_SERVER['__event.test'] = [];
        $d->dispatch(TestEvent::class, halt: true);

        expect($_SERVER['__event.test'])->toEqual([
            'cons-1',
            'handle-1',
        ]);

        unset($_SERVER['__event.test']);
    });

test('invoke is called', function () {
        // Only "handle" is called when both "handle" and "__invoke" exist on listener.
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen('myEvent', TestListenerInvokeyHandler::class);
        $d->dispatch('myEvent');
        expect($_SERVER['__event.test'])->toEqual(['__construct', 'handle']);

        // "__invoke" is called when there is no handle.
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen('myEvent', TestListenerInvokey::class);
        $d->listen('myEvent', TestListenerInvokeyHandler::class);
        $d->dispatch('myEvent', 'somePayload');
        expect($_SERVER['__event.test'])->toEqual(['__construct', '__invoke_somePayload']);

        // It falls back to __invoke if the referenced method is not found.
        $_SERVER['__event.test'] = [];
        $d = new Dispatcher;
        $d->listen('myEvent', [TestListenerInvokey::class, 'someAbsentMethod']);
        $d->dispatch('myEvent', 'somePayload');
        expect($_SERVER['__event.test'])->toEqual(['__construct', '__invoke_somePayload']);

        // It throws an "Error" when there is no method to be called.
        $d = new Dispatcher;
        $d->listen('myEvent', TestListenerLean::class);

        $d->dispatch('myEvent', 'somePayload');
    })->throws(Error::class, 'Call to undefined method '.TestListenerLean::class.'::__invoke()');

test('event dispatches using named arguments', function () {
        $vessel = new Vessel;
        $events = Mockery::mock(Dispatcher::class);
        $vessel->instance('events', $events);

        $originalContainer = Vessel::getInstance();
        Vessel::setInstance($vessel);

        try {
            $events->shouldReceive('dispatch')
                ->once()
                ->with(Mockery::on(function ($event) {
                    expect($event)->toBeInstanceOf(DispatchableNamedArgumentsEvent::class);
                    expect($event->first)->toBe('first-value');
                    expect($event->second)->toBe('second-value');

                    return true;
                }))
                ->andReturn(['dispatched']);

            expect(DispatchableNamedArgumentsEvent::dispatch(second: 'second-value', first: 'first-value'))->toBe(['dispatched']);
        } finally {
            Vessel::setInstance($originalContainer);
        }
    });

