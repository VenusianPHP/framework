<?php

use Tests\Vessel\Fixtures\ResolvingContractStub;
use Tests\Vessel\Fixtures\ResolvingImplementationStub;
use Tests\Vessel\Fixtures\ResolvingImplementationStubTwo;
use Voyager\Vessel\Vessel;

describe('which callbacks fire', function () {
    test('a callback registered against the abstract fires', function () {
        $vessel = new Vessel;
        $vessel->resolving('foo', fn ($object) => $object->name = 'taylor');
        $vessel->bind('foo', fn () => new stdClass);

        expect($vessel->make('foo')->name)->toBe('taylor');
    });

    test('a global callback fires', function () {
        $vessel = new Vessel;
        $vessel->resolving(fn ($object) => $object->name = 'taylor');
        $vessel->bind('foo', fn () => new stdClass);

        expect($vessel->make('foo')->name)->toBe('taylor');
    });

    test('a callback registered against the concrete type fires', function () {
        $vessel = new Vessel;
        $vessel->resolving(stdClass::class, fn ($object) => $object->name = 'taylor');
        $vessel->bind('foo', fn () => new stdClass);

        expect($vessel->make('foo')->name)->toBe('taylor');
    });

    test('a callback registered against an alias fires', function () {
        $vessel = new Vessel;
        $vessel->alias(stdClass::class, 'std');
        $vessel->resolving('std', fn ($object) => $object->name = 'taylor');
        $vessel->bind('foo', fn () => new stdClass);

        expect($vessel->make('foo')->name)->toBe('taylor');
    });
});

describe('how often callbacks fire', function () {
    test('an interface callback fires once per resolution of the implementation', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);
    });

    test('a global callback fires once per resolution', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(2);
    });

    test('binding the concrete as well does not double-fire', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);
        $vessel->bind(ResolvingImplementationStub::class);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(3);
    });

    test('a callback may be added after the first resolution', function () {
        $vessel = new Vessel;
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);
        $vessel->make(ResolvingImplementationStub::class);

        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);
    });

    test('rebinding the interface to another concrete cancels the concrete callback', function () {
        $vessel = new Vessel;
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        $callCounter = 0;
        $vessel->resolving(ResolvingImplementationStub::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStubTwo::class);
        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(1);
    });

    test('a string abstraction fires once per resolution', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving('foo', function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind('foo', ResolvingImplementationStub::class);

        $vessel->make('foo');
        expect($callCounter)->toEqual(1);

        $vessel->make('foo');
        expect($callCounter)->toEqual(2);
    });

    test('a concrete callback fires for every abstraction bound to it', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingImplementationStub::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->bind('foo', ResolvingImplementationStub::class);
        $vessel->bind('bar', ResolvingImplementationStub::class);
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make('foo');
        expect($callCounter)->toEqual(2);

        $vessel->make('bar');
        expect($callCounter)->toEqual(3);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(4);
    });

    test('a closure binding still fires the interface callback for both keys', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind(ResolvingContractStub::class, fn () => new ResolvingImplementationStub);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(3);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(4);
    });

    test('rebinding does not affect the resolving callbacks', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);
        $vessel->bind(ResolvingContractStub::class, fn () => new ResolvingImplementationStub);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(3);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(4);
    });

    test('rebinding does not affect multiple resolving callbacks', function () {
        $vessel = new Vessel;
        $callCounter = 0;

        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->resolving(ResolvingImplementationStubTwo::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        // it should call the callback for interface
        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(1);

        // it should call the callback for interface
        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);

        // should call the callback for the interface it implements
        // plus the callback for ResolvingImplementationStubTwo.
        $vessel->make(ResolvingImplementationStubTwo::class);
        expect($callCounter)->toEqual(4);
    });

    test('an interface callback fires when the interface is resolved', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        $vessel->make(ResolvingContractStub::class);

        expect($callCounter)->toEqual(1);
    });

    test('a concrete callback fires whether the interface or the concrete is resolved', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingImplementationStub::class, function () use (&$callCounter) {
            $callCounter++;
        });
        $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

        $vessel->make(ResolvingContractStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);
    });

    test('a concrete callback fires with no binding registered', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingImplementationStub::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);
    });

    test('an interface callback fires with no binding registered', function () {
        $vessel = new Vessel;
        $callCounter = 0;
        $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
            $callCounter++;
        });

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(1);

        $vessel->make(ResolvingImplementationStub::class);
        expect($callCounter)->toEqual(2);
    });
});

test('resolving callbacks are called when a rebind happens', function () {
    $vessel = new Vessel;

    $resolvingCallCounter = 0;
    $vessel->resolving(ResolvingContractStub::class, function () use (&$resolvingCallCounter) {
        $resolvingCallCounter++;
    });

    $rebindCallCounter = 0;
    $vessel->rebinding(ResolvingContractStub::class, function () use (&$rebindCallCounter) {
        $rebindCallCounter++;
    });

    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

    $vessel->make(ResolvingContractStub::class);
    expect($resolvingCallCounter)->toEqual(1)
        ->and($rebindCallCounter)->toEqual(0);

    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStubTwo::class);
    expect($resolvingCallCounter)->toEqual(2)
        ->and($rebindCallCounter)->toEqual(1);

    $vessel->make(ResolvingImplementationStubTwo::class);
    expect($resolvingCallCounter)->toEqual(3)
        ->and($rebindCallCounter)->toEqual(1);

    $vessel->bind(ResolvingContractStub::class, fn () => new ResolvingImplementationStubTwo);
    expect($resolvingCallCounter)->toEqual(4)
        ->and($rebindCallCounter)->toEqual(2);

    $vessel->make(ResolvingContractStub::class);
    expect($resolvingCallCounter)->toEqual(5)
        ->and($rebindCallCounter)->toEqual(2);
});

test('resolving callbacks are not called when no rebindings are registered', function () {
    $vessel = new Vessel;

    $callCounter = 0;
    $vessel->resolving(ResolvingContractStub::class, function () use (&$callCounter) {
        $callCounter++;
    });

    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

    $vessel->make(ResolvingContractStub::class);
    expect($callCounter)->toEqual(1);

    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStubTwo::class);
    expect($callCounter)->toEqual(1);

    $vessel->make(ResolvingImplementationStubTwo::class);
    expect($callCounter)->toEqual(2);

    $vessel->bind(ResolvingContractStub::class, fn () => new ResolvingImplementationStubTwo);
    expect($callCounter)->toEqual(2);

    $vessel->make(ResolvingContractStub::class);
    expect($callCounter)->toEqual(3);
});

test('the object and the container are passed into every callback', function () {
    $vessel = new Vessel;

    $assertArguments = function ($obj, $app) use ($vessel) {
        expect($obj)->toBeInstanceOf(ResolvingContractStub::class)
            ->and($obj)->toBeInstanceOf(ResolvingImplementationStubTwo::class)
            ->and($app)->toBe($vessel);
    };

    $vessel->resolving(ResolvingContractStub::class, $assertArguments);
    $vessel->afterResolving(ResolvingContractStub::class, $assertArguments);
    $vessel->afterResolving($assertArguments);

    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStubTwo::class);
    $vessel->make(ResolvingContractStub::class);
});

test('afterResolving callbacks fire once per resolution of the implementation', function () {
    $vessel = new Vessel;

    $callCounter = 0;
    $vessel->afterResolving(ResolvingContractStub::class, function () use (&$callCounter) {
        $callCounter++;
    });

    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

    $vessel->make(ResolvingImplementationStub::class);
    expect($callCounter)->toEqual(1);

    $vessel->make(ResolvingContractStub::class);
    expect($callCounter)->toEqual(2);
});

test('beforeResolving callbacks fire for both the interface and the implementation', function () {
    // Given a call counter initialized to zero.
    $vessel = new Vessel;
    $callCounter = 0;

    // And a contract/implementation stub binding.
    $vessel->bind(ResolvingContractStub::class, ResolvingImplementationStub::class);

    // When we add a before resolving callback that increment the counter by one.
    $vessel->beforeResolving(ResolvingContractStub::class, function () use (&$callCounter) {
        $callCounter++;
    });

    // Then resolving the implementation stub increases the counter by one.
    $vessel->make(ResolvingImplementationStub::class);
    expect($callCounter)->toEqual(1);

    // And resolving the contract stub increases the counter by one.
    $vessel->make(ResolvingContractStub::class);
    expect($callCounter)->toEqual(2);
});

test('global beforeResolving callbacks fire for anything', function () {
    // Given a call counter initialized to zero.
    $vessel = new Vessel;
    $callCounter = 0;

    // When we add a global before resolving callback that increment that counter by one.
    $vessel->beforeResolving(function () use (&$callCounter) {
        $callCounter++;
    });

    // Then resolving anything increases the counter by one.
    $vessel->make(ResolvingImplementationStub::class);
    expect($callCounter)->toEqual(1);
});
