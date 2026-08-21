<?php

use Tests\Vessel\Fixtures\ContainerCallCallableClassStringStub;
use Tests\Vessel\Fixtures\ContainerCallCallableStub;
use Tests\Vessel\Fixtures\ContainerCallConcreteStub;
use Tests\Vessel\Fixtures\ContainerTestCallStub;
use Voyager\Contracts\Vessel\BindingResolutionException;
use Voyager\Vessel\Vessel;

test('an at-sign reference without a method falls through to a function call', function () {
    (new Vessel)->call('ContainerTestCallStub');
})->throws(Error::class, 'Call to undefined function ContainerTestCallStub()');

describe('at-sign class references', function () {
    test('passes through the given parameters', function () {
        expect((new Vessel)->call(ContainerTestCallStub::class.'@work', ['foo', 'bar']))->toEqual(['foo', 'bar']);
    });

    test('injects typed dependencies and honours defaults', function () {
        $result = (new Vessel)->call(ContainerTestCallStub::class.'@inject');

        expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
            ->and($result[1])->toBe('taylor');
    });

    test('a named parameter overrides the default', function () {
        $result = (new Vessel)->call(ContainerTestCallStub::class.'@inject', ['default' => 'foo']);

        expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
            ->and($result[1])->toBe('foo');
    });

    test('the method may be given as a third argument', function () {
        expect((new Vessel)->call(ContainerTestCallStub::class, ['foo', 'bar'], 'work'))->toEqual(['foo', 'bar']);
    });
});

test('call accepts a callable array', function () {
    expect((new Vessel)->call([new ContainerTestCallStub, 'work'], ['foo', 'bar']))->toEqual(['foo', 'bar']);
});

test('call accepts a static method name string', function () {
    $result = (new Vessel)->call('Tests\Vessel\Fixtures\ContainerStaticMethodStub::inject');

    expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
        ->and($result[1])->toBe('taylor');
});

test('call accepts a namespaced function name', function () {
    $result = (new Vessel)->call('Tests\Vessel\Fixtures\containerTestInject');

    expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
        ->and($result[1])->toBe('taylor');
});

describe('bindMethod', function () {
    test('an at-sign key resolves an otherwise unresolvable method', function () {
        $vessel = new Vessel;
        $vessel->bindMethod(ContainerTestCallStub::class.'@unresolvable', fn ($stub) => $stub->unresolvable('foo', 'bar'));

        expect($vessel->call(ContainerTestCallStub::class.'@unresolvable'))->toEqual(['foo', 'bar']);
    });

    test('an at-sign key also applies to a callable array', function () {
        $vessel = new Vessel;
        $vessel->bindMethod(ContainerTestCallStub::class.'@unresolvable', fn ($stub) => $stub->unresolvable('foo', 'bar'));

        expect($vessel->call([new ContainerTestCallStub, 'unresolvable']))->toEqual(['foo', 'bar']);
    });

    test('the key may be given as an array', function () {
        $vessel = new Vessel;
        $vessel->bindMethod([ContainerTestCallStub::class, 'unresolvable'], fn ($stub) => $stub->unresolvable('foo', 'bar'));

        expect($vessel->call(ContainerTestCallStub::class.'@unresolvable'))->toEqual(['foo', 'bar']);
    });

    test('an array key also applies to a callable array', function () {
        $vessel = new Vessel;
        $vessel->bindMethod([ContainerTestCallStub::class, 'unresolvable'], fn ($stub) => $stub->unresolvable('foo', 'bar'));

        expect($vessel->call([new ContainerTestCallStub, 'unresolvable']))->toEqual(['foo', 'bar']);
    });

    test('unknown named parameters are ignored while known ones are honoured', function () {
        $result = (new Vessel)->call([new ContainerTestCallStub, 'inject'], ['_stub' => 'foo', 'default' => 'bar']);

        expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
            ->and($result[1])->toBe('bar');
    });

    test('unknown named parameters leave the default in place', function () {
        $result = (new Vessel)->call([new ContainerTestCallStub, 'inject'], ['_stub' => 'foo']);

        expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
            ->and($result[1])->toBe('taylor');
    });
});

test('a closure dependency resolves whether or not it is passed explicitly', function () {
    $vessel = new Vessel;

    $vessel->call(function (ContainerCallConcreteStub $stub) {
        expect($stub)->toBeInstanceOf(ContainerCallConcreteStub::class);
    }, ['foo' => 'bar']);

    $explicit = new ContainerCallConcreteStub;

    $vessel->call(function (ContainerCallConcreteStub $stub) use ($explicit) {
        expect($stub)->toBeInstanceOf(ContainerCallConcreteStub::class);
    }, ['foo' => 'bar', 'stub' => $explicit]);
});

describe('call with dependencies', function () {
    test('typed dependencies resolve and untyped ones keep their default', function () {
        $result = (new Vessel)->call(fn (stdClass $foo, $bar = []) => func_get_args());

        expect($result[0])->toBeInstanceOf(stdClass::class)
            ->and($result[1])->toEqual([]);
    });

    test('a named parameter overrides the default', function () {
        $result = (new Vessel)->call(fn (stdClass $foo, $bar = []) => func_get_args(), ['bar' => 'taylor']);

        expect($result[0])->toBeInstanceOf(stdClass::class)
            ->and($result[1])->toBe('taylor');
    });

    test('a class-string key supplies the instance for that dependency', function () {
        $stub = new ContainerCallConcreteStub;

        $result = (new Vessel)->call(
            fn (stdClass $foo, ContainerCallConcreteStub $bar) => func_get_args(),
            [ContainerCallConcreteStub::class => $stub],
        );

        expect($result[0])->toBeInstanceOf(stdClass::class)
            ->and($result[1])->toBe($stub);
    });

    test('wrap defers the call behind a closure', function () {
        $wrapped = (new Vessel)->wrap(fn (stdClass $foo, $bar = []) => func_get_args(), ['bar' => 'taylor']);

        expect($wrapped)->toBeInstanceOf(Closure::class);

        $result = $wrapped();

        expect($result[0])->toBeInstanceOf(stdClass::class)
            ->and($result[1])->toBe('taylor');
    });
});

test('a variadic dependency is spread from the binding', function () {
    $stub1 = new ContainerCallConcreteStub;
    $stub2 = new ContainerCallConcreteStub;

    $vessel = new Vessel;
    $vessel->bind(ContainerCallConcreteStub::class, fn () => [$stub1, $stub2]);

    $result = $vessel->call(fn (stdClass $foo, ContainerCallConcreteStub ...$bar) => func_get_args());

    expect($result[0])->toBeInstanceOf(stdClass::class)
        ->and($result[1])->toBeInstanceOf(ContainerCallConcreteStub::class)
        ->and($result[1])->toBe($stub1)
        ->and($result[2])->toBe($stub2);
});

test('call accepts an invokable object', function () {
    $result = (new Vessel)->call(new ContainerCallCallableStub);

    expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
        ->and($result[1])->toBe('jeffrey');
});

test('call accepts an invokable class string', function () {
    $result = (new Vessel)->call(ContainerCallCallableClassStringStub::class);

    expect($result[0])->toBeInstanceOf(ContainerCallConcreteStub::class)
        ->and($result[1])->toBe('jeffrey')
        ->and($result[2])->toBeInstanceOf(ContainerTestCallStub::class);
});

test('an unresolvable method parameter throws', function () {
    (new Vessel)->call(ContainerTestCallStub::class.'@unresolvable');
})->throws(
    BindingResolutionException::class,
    'Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class Tests\Vessel\Fixtures\ContainerTestCallStub',
);

test('unnamed parameters do not satisfy a required method parameter', function () {
    (new Vessel)->call([new ContainerTestCallStub, 'unresolvable'], ['foo', 'bar']);
})->throws(
    BindingResolutionException::class,
    'Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class Tests\Vessel\Fixtures\ContainerTestCallStub',
);

test('an unresolvable closure parameter throws', function () {
    (new Vessel)->call(fn ($foo, $bar = 'default') => $foo);
})->throws(BindingResolutionException::class, 'Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class P\\Tests\\Vessel\\ContainerCallTest');
