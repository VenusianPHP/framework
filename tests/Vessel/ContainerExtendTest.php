<?php

use Tests\Vessel\Fixtures\ContainerExtendConsumesInterfaceStub;
use Tests\Vessel\Fixtures\ContainerExtendInterfaceImplementationStub;
use Tests\Vessel\Fixtures\ContainerExtendInterfaceStub;
use Tests\Vessel\Fixtures\ContainerLazyExtendStub;
use Voyager\Vessel\Vessel;

test('an extender decorates a plain binding', function () {
    $vessel = new Vessel;
    $vessel['foo'] = 'foo';
    $vessel->extend('foo', fn ($old, $vessel) => $old.'bar');

    expect($vessel->make('foo'))->toBe('foobar');
});

test('an extended singleton stays a singleton', function () {
    $vessel = new Vessel;
    $vessel->singleton('foo', fn () => (object) ['name' => 'taylor']);
    $vessel->extend('foo', function ($old, $vessel) {
        $old->age = 26;

        return $old;
    });

    $result = $vessel->make('foo');

    expect($result->name)->toBe('taylor')
        ->and($result->age)->toEqual(26)
        ->and($vessel->make('foo'))->toBe($result);
});

test('extending an instance preserves it across every extender', function () {
    $vessel = new Vessel;
    $vessel->bind('foo', function () {
        $obj = new stdClass;
        $obj->foo = 'bar';

        return $obj;
    });

    $obj = new stdClass;
    $obj->foo = 'foo';
    $vessel->instance('foo', $obj);
    $vessel->extend('foo', function ($obj, $vessel) {
        $obj->bar = 'baz';

        return $obj;
    });
    $vessel->extend('foo', function ($obj, $vessel) {
        $obj->baz = 'foo';

        return $obj;
    });

    expect($vessel->make('foo')->foo)->toBe('foo')
        ->and($vessel->make('foo')->bar)->toBe('baz')
        ->and($vessel->make('foo')->baz)->toBe('foo');
});

test('extenders are initialised lazily', function () {
    ContainerLazyExtendStub::$initialized = false;

    $vessel = new Vessel;
    $vessel->bind(ContainerLazyExtendStub::class);
    $vessel->extend(ContainerLazyExtendStub::class, function ($obj, $vessel) {
        $obj->init();

        return $obj;
    });

    expect(ContainerLazyExtendStub::$initialized)->toBeFalse();

    $vessel->make(ContainerLazyExtendStub::class);

    expect(ContainerLazyExtendStub::$initialized)->toBeTrue();
});

test('extend may be called before bind', function () {
    $vessel = new Vessel;
    $vessel->extend('foo', fn ($old, $vessel) => $old.'bar');
    $vessel['foo'] = 'foo';

    expect($vessel->make('foo'))->toBe('foobar');
});

test('extending an instance fires the rebinding callback', function () {
    $_SERVER['_test_rebind'] = false;

    $vessel = new Vessel;
    $vessel->rebinding('foo', function () {
        $_SERVER['_test_rebind'] = true;
    });

    $vessel->instance('foo', new stdClass);
    $vessel->extend('foo', fn ($obj, $vessel) => $obj);

    expect($_SERVER['_test_rebind'])->toBeTrue();
});

test('extending a resolved binding fires the rebinding callback', function () {
    $_SERVER['_test_rebind'] = false;

    $vessel = new Vessel;
    $vessel->rebinding('foo', function () {
        $_SERVER['_test_rebind'] = true;
    });
    $vessel->bind('foo', fn () => new stdClass);

    expect($_SERVER['_test_rebind'])->toBeFalse();

    $vessel->make('foo');
    $vessel->extend('foo', fn ($obj, $vessel) => $obj);

    expect($_SERVER['_test_rebind'])->toBeTrue();
});

test('extension works through an alias', function () {
    $vessel = new Vessel;
    $vessel->singleton('something', fn () => 'some value');
    $vessel->alias('something', 'something-alias');
    $vessel->extend('something-alias', fn ($value) => $value.' extended');

    expect($vessel->make('something'))->toBe('some value extended');
});

test('extenders stack in registration order', function () {
    $vessel = new Vessel;
    $vessel['foo'] = 'foo';
    $vessel->extend('foo', fn ($old, $vessel) => $old.'bar');
    $vessel->extend('foo', fn ($old, $vessel) => $old.'baz');

    expect($vessel->make('foo'))->toBe('foobarbaz');
});

test('forgetExtenders drops the extenders for an unset binding', function () {
    $vessel = new Vessel;
    $vessel->bind('foo', function () {
        $obj = new stdClass;
        $obj->foo = 'bar';

        return $obj;
    });
    $vessel->extend('foo', function ($obj, $vessel) {
        $obj->bar = 'baz';

        return $obj;
    });

    unset($vessel['foo']);
    $vessel->forgetExtenders('foo');

    $vessel->bind('foo', fn () => 'foo');

    expect($vessel->make('foo'))->toBe('foo');
});

test('an extender applies to a contextual binding', function () {
    $vessel = new Vessel;
    $vessel->when(ContainerExtendConsumesInterfaceStub::class)
        ->needs(ContainerExtendInterfaceStub::class)
        ->give(fn () => new ContainerExtendInterfaceImplementationStub('foo'));

    $vessel->extend(ContainerExtendInterfaceStub::class, function ($instance) {
        expect($instance)->toBeInstanceOf(ContainerExtendInterfaceImplementationStub::class)
            ->and($instance->value)->toBe('foo');

        return new ContainerExtendInterfaceImplementationStub('bar');
    });

    expect($vessel->make(ContainerExtendConsumesInterfaceStub::class)->stub->value)->toBe('bar');
});

// https://github.com/venusian/framework/issues/53501
test('an extender applies to a contextual binding registered after resolution', function () {
    $vessel = new Vessel;
    $vessel->when(ContainerExtendConsumesInterfaceStub::class)
        ->needs(ContainerExtendInterfaceStub::class)
        ->give(fn () => new ContainerExtendInterfaceImplementationStub('foo'));

    $vessel->make(ContainerExtendConsumesInterfaceStub::class);

    $vessel->extend(ContainerExtendInterfaceStub::class, function ($instance) {
        expect($instance)->toBeInstanceOf(ContainerExtendInterfaceImplementationStub::class)
            ->and($instance->value)->toBe('foo');

        return new ContainerExtendInterfaceImplementationStub('bar');
    });

    expect($vessel->make(ContainerExtendConsumesInterfaceStub::class)->stub->value)->toBe('bar');
});
