<?php

use Tests\Vessel\Fixtures\AltConcrete;
use Tests\Vessel\Fixtures\CliConcrete;
use Tests\Vessel\Fixtures\CliOnlyInterface;
use Tests\Vessel\Fixtures\ContainerBindScopedTestInterface;
use Tests\Vessel\Fixtures\ContainerBindSingletonTestInterface;
use Tests\Vessel\Fixtures\ContainerClassWithDefaultValueStub;
use Tests\Vessel\Fixtures\ContainerConcreteStub;
use Tests\Vessel\Fixtures\ContainerContextualBindingCallTarget;
use Tests\Vessel\Fixtures\ContainerCurrentResolvingAttribute;
use Tests\Vessel\Fixtures\ContainerCurrentResolvingConcrete;
use Tests\Vessel\Fixtures\ContainerDefaultValueStub;
use Tests\Vessel\Fixtures\ContainerDependentStub;
use Tests\Vessel\Fixtures\ContainerImplementationStub;
use Tests\Vessel\Fixtures\ContainerImplementationStubTwo;
use Tests\Vessel\Fixtures\ContainerInjectVariableStubWithInterfaceImplementation;
use Tests\Vessel\Fixtures\ContainerMixedPrimitiveStub;
use Tests\Vessel\Fixtures\ContainerNestedDependentStub;
use Tests\Vessel\Fixtures\ContainerScopedAttribute;
use Tests\Vessel\Fixtures\ContainerSingletonAttribute;
use Tests\Vessel\Fixtures\DevConcrete;
use Tests\Vessel\Fixtures\EmptyEnvInterface;
use Tests\Vessel\Fixtures\FallbackConcrete;
use Tests\Vessel\Fixtures\IsScoped;
use Tests\Vessel\Fixtures\IsSingleton;
use Tests\Vessel\Fixtures\IVesselContractStub;
use Tests\Vessel\Fixtures\MultiEnvInterface;
use Tests\Vessel\Fixtures\OverrideInterface;
use Tests\Vessel\Fixtures\ProdConcrete;
use Tests\Vessel\Fixtures\ProdEnvOnlyInterface;
use Tests\Vessel\Fixtures\RequestDto;
use Tests\Vessel\Fixtures\RequestDtoDependency;
use Tests\Vessel\Fixtures\RequestDtoDependencyContract;
use Tests\Vessel\Fixtures\WildcardAndProdInterface;
use Tests\Vessel\Fixtures\WildcardOnlyInterface;
use Psr\Container\ContainerExceptionInterface;
use Voyager\Contracts\Vessel\BindingResolutionException;
use Voyager\Vessel\EntryNotFoundException;
use Voyager\Vessel\Vessel;

afterEach(function () {
    Vessel::setInstance(null);
});

test('the container keeps a global instance', function () {
    $vessel = Vessel::setInstance(new Vessel);

    expect(Vessel::getInstance())->toBe($vessel);

    Vessel::setInstance(null);

    $container2 = Vessel::getInstance();

    expect($container2)->toBeInstanceOf(Vessel::class)
        ->and($container2)->not->toBe($vessel);
});

describe('binding', function () {
    test('a closure binding resolves', function () {
        $vessel = new Vessel;
        $vessel->bind('name', fn () => 'Taylor');

        expect($vessel->make('name'))->toBe('Taylor');
    });

    test('the abstract may be taken from the closure return type', function () {
        $vessel = new Vessel;
        $vessel->bind(fn (): IVesselContractStub|ContainerImplementationStub => new ContainerImplementationStub);
        $vessel->singleton(fn (): ContainerConcreteStub => new ContainerConcreteStub);

        expect($vessel->make(IVesselContractStub::class))->toBeInstanceOf(IVesselContractStub::class)
            ->and($vessel->isShared(ContainerConcreteStub::class))->toBeTrue();
    });

    test('bindIf leaves an already registered service alone', function () {
        $vessel = new Vessel;
        $vessel->bind('name', fn () => 'Taylor');
        $vessel->bindIf('name', fn () => 'Dayle');

        expect($vessel->make('name'))->toBe('Taylor');
    });

    test('bindIf registers a service that is not bound yet', function () {
        $vessel = new Vessel;
        $vessel->bind('surname', fn () => 'Taylor');
        $vessel->bindIf('name', fn () => 'Dayle');

        expect($vessel->make('name'))->toBe('Dayle');
    });

    test('bind fails loudly when given a non-closure concrete instance', function () {
        (new Vessel)->bind(ContainerConcreteStub::class, new ContainerConcreteStub);
    })->throws(TypeError::class);

    test('bindings can be overridden', function () {
        $vessel = new Vessel;
        $vessel['foo'] = 'bar';
        $vessel['foo'] = 'baz';

        expect($vessel['foo'])->toBe('baz');
    });

    test('bound reports on the abstract, not the concrete', function () {
        $vessel = new Vessel;
        $vessel->bind(ContainerConcreteStub::class, function () {
            //
        });

        expect($vessel->bound(ContainerConcreteStub::class))->toBeTrue()
            ->and($vessel->bound(IVesselContractStub::class))->toBeFalse();

        $vessel = new Vessel;
        $vessel->bind(IVesselContractStub::class, ContainerConcreteStub::class);

        expect($vessel->bound(IVesselContractStub::class))->toBeTrue()
            ->and($vessel->bound(ContainerConcreteStub::class))->toBeFalse();
    });

    test('any word may be bound', function () {
        $vessel = new Vessel;
        $vessel->bind('Taylor', stdClass::class);

        expect($vessel->get('Taylor'))->toBeInstanceOf(stdClass::class);
    });
});

describe('singletons and scoped bindings', function () {
    test('singletonIf leaves an already registered binding alone', function () {
        $vessel = new Vessel;
        $vessel->singleton('class', fn () => new stdClass);
        $firstInstantiation = $vessel->make('class');
        $vessel->singletonIf('class', fn () => new ContainerConcreteStub);

        expect($vessel->make('class'))->toBe($firstInstantiation);
    });

    test('singletonIf registers a binding that is not bound yet', function () {
        $vessel = new Vessel;
        $vessel->singleton('class', fn () => new stdClass);
        $vessel->singletonIf('otherClass', fn () => new ContainerConcreteStub);

        expect($vessel->make('otherClass'))->toBe($vessel->make('otherClass'));
    });

    test('a shared closure resolves to the same instance', function () {
        $vessel = new Vessel;
        $vessel->singleton('class', fn () => new stdClass);

        expect($vessel->make('class'))->toBe($vessel->make('class'));
    });

    test('a scoped closure resolves to the same instance', function () {
        $vessel = new Vessel;
        $vessel->scoped('class', fn () => new stdClass);

        expect($vessel->make('class'))->toBe($vessel->make('class'));
    });

    test('a scoped binding may take its abstract from the closure return type', function () {
        $vessel = new Vessel;
        $vessel->scoped(fn (): stdClass => new stdClass);
        $vessel->forgetScopedInstances();
    })->throwsNoExceptions();

    test('scopedIf leaves an already registered binding alone', function () {
        $vessel = new Vessel;
        $vessel->scopedIf('class', fn () => 'foo');

        expect($vessel->make('class'))->toBe('foo');

        $vessel->scopedIf('class', fn () => 'bar');

        expect($vessel->make('class'))->toBe('foo')
            ->and($vessel->make('class'))->not->toBe('bar');
    });

    test('forgetScopedInstances resets a scoped closure', function () {
        $vessel = new Vessel;
        $vessel->scoped('class', fn () => new stdClass);
        $firstInstantiation = $vessel->make('class');

        $vessel->forgetScopedInstances();

        expect($vessel->make('class'))->not->toBe($firstInstantiation);
    });

    test('a shared concrete resolves to the same instance', function () {
        $vessel = new Vessel;
        $vessel->singleton(ContainerConcreteStub::class);

        expect($vessel->make(ContainerConcreteStub::class))->toBe($vessel->make(ContainerConcreteStub::class));
    });

    test('forgetScopedInstances resets a scoped concrete', function () {
        $vessel = new Vessel;
        $vessel->scoped(ContainerConcreteStub::class);
        $var1 = $vessel->make(ContainerConcreteStub::class);

        $vessel->forgetScopedInstances();

        expect($vessel->make(ContainerConcreteStub::class))->not->toBe($var1);
    });
});

describe('resolution', function () {
    test('a concrete class resolves without any binding', function () {
        expect((new Vessel)->make(ContainerConcreteStub::class))->toBeInstanceOf(ContainerConcreteStub::class);
    });

    test('an abstract resolves to its bound concrete', function () {
        $vessel = new Vessel;
        $vessel->bind(IVesselContractStub::class, ContainerImplementationStub::class);

        expect($vessel->make(ContainerDependentStub::class)->impl)->toBeInstanceOf(ContainerImplementationStub::class);
    });

    test('nested dependencies resolve', function () {
        $vessel = new Vessel;
        $vessel->bind(IVesselContractStub::class, ContainerImplementationStub::class);
        $class = $vessel->make(ContainerNestedDependentStub::class);

        expect($class->inner)->toBeInstanceOf(ContainerDependentStub::class)
            ->and($class->inner->impl)->toBeInstanceOf(ContainerImplementationStub::class);
    });

    test('the container is passed to resolvers', function () {
        $vessel = new Vessel;
        $vessel->bind('something', fn ($c) => $c);

        expect($vessel->make('something'))->toBe($vessel);
    });

    test('default parameters are used when nothing is given', function () {
        $instance = (new Vessel)->make(ContainerDefaultValueStub::class);

        expect($instance->stub)->toBeInstanceOf(ContainerConcreteStub::class)
            ->and($instance->default)->toBe('taylor');
    });

    test('a nullable class parameter defaults to null until the class is bound', function () {
        $vessel = new Vessel;
        $instance = $vessel->make(ContainerClassWithDefaultValueStub::class);

        expect($instance->noDefault)->toBeInstanceOf(ContainerConcreteStub::class)
            ->and($instance->default)->toBeNull();

        $vessel->bind(ContainerConcreteStub::class, fn () => new ContainerConcreteStub);

        expect($vessel->make(ContainerClassWithDefaultValueStub::class)->default)->toBeInstanceOf(ContainerConcreteStub::class);
    });

    test('a contextual binding fills a nullable class parameter', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerClassWithDefaultValueStub::class)
            ->needs(ContainerConcreteStub::class)
            ->give(fn () => new ContainerConcreteStub);

        expect($vessel->make(ContainerClassWithDefaultValueStub::class)->default)->toBeInstanceOf(ContainerConcreteStub::class);
    });

    test('build works without a parameter stack for a class with no constructor', function () {
        expect((new Vessel)->build(ContainerConcreteStub::class))->toBeInstanceOf(ContainerConcreteStub::class);
    });

    test('build works without a parameter stack for a class with a constructor', function () {
        $vessel = new Vessel;
        $vessel->bind(IVesselContractStub::class, ContainerImplementationStub::class);

        expect($vessel->build(ContainerDependentStub::class))->toBeInstanceOf(ContainerDependentStub::class);
    });

    test('the container resolves classes through get', function () {
        expect((new Vessel)->get(ContainerConcreteStub::class))->toBeInstanceOf(ContainerConcreteStub::class);
    });

    test('currentlyResolving reports the concrete under construction', function () {
        $vessel = new Vessel;

        $vessel->afterResolvingAttribute(ContainerCurrentResolvingAttribute::class, function ($attr, $instance, $vessel) {
            expect($vessel->currentlyResolving())->toEqual(ContainerCurrentResolvingConcrete::class);
        });

        $vessel->when(ContainerCurrentResolvingConcrete::class)
            ->needs('$currentlyResolving')
            ->give(fn ($vessel) => $vessel->currentlyResolving());

        expect($vessel->make(ContainerCurrentResolvingConcrete::class)->currentlyResolving)
            ->toEqual(ContainerCurrentResolvingConcrete::class);
    });
});

describe('resolution parameters', function () {
    test('makeWith is an alias for make', function () {
        $mock = Mockery::mock(Vessel::class)->makePartial();
        $mock->shouldReceive('make')
            ->once()
            ->with(ContainerDefaultValueStub::class, ['default' => 'laurence'])
            ->andReturn(new stdClass);

        expect($mock->makeWith(ContainerDefaultValueStub::class, ['default' => 'laurence']))->toBeInstanceOf(stdClass::class);
    });

    test('an array of parameters overrides the defaults', function () {
        $vessel = new Vessel;

        expect($vessel->make(ContainerDefaultValueStub::class, ['default' => 'adam'])->default)->toBe('adam')
            ->and($vessel->make(ContainerDefaultValueStub::class)->default)->toBe('taylor');

        $vessel->bind('foo', fn ($app, $config) => $config);

        expect($vessel->make('foo', [1, 2, 3]))->toEqual([1, 2, 3]);
    });

    test('mixed parameters resolve alongside injected classes', function () {
        $instance = (new Vessel)->make(ContainerMixedPrimitiveStub::class, ['first' => 1, 'last' => 2, 'third' => 3]);

        expect($instance->first)->toBe(1)
            ->and($instance->stub)->toBeInstanceOf(ContainerConcreteStub::class)
            ->and($instance->last)->toBe(2)
            ->and(isset($instance->third))->toBeFalse();
    });

    test('parameters pass through an interface binding', function () {
        $vessel = new Vessel;
        $vessel->bind(IVesselContractStub::class, ContainerInjectVariableStubWithInterfaceImplementation::class);

        expect($vessel->make(IVesselContractStub::class, ['something' => 'laurence'])->something)->toBe('laurence');
    });

    test('a nested make may override parameters', function () {
        $vessel = new Vessel;
        $vessel->bind('foo', fn ($app, $config) => $app->make('bar', ['name' => 'Taylor']));
        $vessel->bind('bar', fn ($app, $config) => $config);

        expect($vessel->make('foo', ['something']))->toEqual(['name' => 'Taylor']);
    });

    test('nested parameters are reset for a fresh make', function () {
        $vessel = new Vessel;
        $vessel->bind('foo', fn ($app, $config) => $app->make('bar'));
        $vessel->bind('bar', fn ($app, $config) => $config);

        expect($vessel->make('foo', ['something']))->toEqual([]);
    });

    test('singleton bindings are not respected when make parameters are given', function () {
        $vessel = new Vessel;
        $vessel->singleton('foo', fn ($app, $config) => $config);

        expect($vessel->make('foo', ['name' => 'taylor']))->toEqual(['name' => 'taylor'])
            ->and($vessel->make('foo', ['name' => 'abigail']))->toEqual(['name' => 'abigail']);
    });
});

describe('array access', function () {
    test('a closure offset can be set, read and unset', function () {
        $vessel = new Vessel;

        expect(isset($vessel['something']))->toBeFalse();

        $vessel['something'] = fn () => 'foo';

        expect(isset($vessel['something']))->toBeTrue()
            ->and($vessel['something'])->not->toBeEmpty()
            ->and($vessel['something'])->toBe('foo');

        unset($vessel['something']);

        expect(isset($vessel['something']))->toBeFalse();
    });

    test('a non-closure offset can be set, read and unset', function () {
        $vessel = new Vessel;
        $vessel['something'] = 'text';

        expect(isset($vessel['something']))->toBeTrue()
            ->and($vessel['something'])->not->toBeEmpty()
            ->and($vessel['something'])->toBe('text');

        unset($vessel['something']);

        expect(isset($vessel['something']))->toBeFalse();
    });

    test('a service may be set dynamically', function () {
        $vessel = new Vessel;

        expect(isset($vessel['name']))->toBeFalse();

        $vessel['name'] = 'Taylor';

        expect(isset($vessel['name']))->toBeTrue()
            ->and($vessel['name'])->toBe('Taylor');
    });

    test('unset removes a bound instance', function () {
        $vessel = new Vessel;
        $vessel->instance('object', new stdClass);
        unset($vessel['object']);

        expect($vessel->bound('object'))->toBeFalse();
    });

    test('a bound instance and its alias both report as set', function () {
        $vessel = new Vessel;
        $vessel->instance('object', new stdClass);
        $vessel->alias('object', 'alias');

        expect(isset($vessel['object']))->toBeTrue()
            ->and(isset($vessel['alias']))->toBeTrue();
    });
});

describe('aliases', function () {
    test('aliases chain through to the underlying binding', function () {
        $vessel = new Vessel;
        $vessel['foo'] = 'bar';
        $vessel->alias('foo', 'baz');
        $vessel->alias('baz', 'bat');

        expect($vessel->make('foo'))->toBe('bar')
            ->and($vessel->make('baz'))->toBe('bar')
            ->and($vessel->make('bat'))->toBe('bar');
    });

    test('parameters pass through an alias', function () {
        $vessel = new Vessel;
        $vessel->bind('foo', fn ($app, $config) => $config);
        $vessel->alias('foo', 'baz');

        expect($vessel->make('baz', [1, 2, 3]))->toEqual([1, 2, 3]);
    });

    test('getAlias returns the aliased abstract', function () {
        $vessel = new Vessel;
        $vessel->alias('ConcreteStub', 'foo');

        expect($vessel->getAlias('foo'))->toBe('ConcreteStub');
    });

    test('getAlias resolves recursively', function () {
        $vessel = new Vessel;
        $vessel->alias('ConcreteStub', 'foo');
        $vessel->alias('foo', 'bar');
        $vessel->alias('bar', 'baz');

        expect($vessel->getAlias('baz'))->toBe('ConcreteStub')
            ->and($vessel->isAlias('baz'))->toBeTrue()
            ->and($vessel->isAlias('bar'))->toBeTrue()
            ->and($vessel->isAlias('foo'))->toBeTrue();
    });

    test('aliasing an abstract to itself throws', function () {
        (new Vessel)->alias('name', 'name');
    })->throws(LogicException::class, '[name] is aliased to itself.');
});

describe('instances', function () {
    test('binding an instance returns the instance', function () {
        $vessel = new Vessel;
        $bound = new stdClass;

        expect($vessel->instance('foo', $bound))->toBe($bound);
    });

    test('an instance is bound as shared', function () {
        $vessel = new Vessel;
        $bound = new stdClass;
        $vessel->instance('foo', $bound);

        expect($vessel->make('foo'))->toBe($bound);
    });

    test('forgetInstance forgets a single instance', function () {
        $vessel = new Vessel;
        $vessel->instance(ContainerConcreteStub::class, new ContainerConcreteStub);

        expect($vessel->isShared(ContainerConcreteStub::class))->toBeTrue();

        $vessel->forgetInstance(ContainerConcreteStub::class);

        expect($vessel->isShared(ContainerConcreteStub::class))->toBeFalse();
    });

    test('forgetInstances forgets every instance', function () {
        $vessel = new Vessel;
        $vessel->instance('Instance1', new ContainerConcreteStub);
        $vessel->instance('Instance2', new ContainerConcreteStub);
        $vessel->instance('Instance3', new ContainerConcreteStub);

        expect($vessel->isShared('Instance1'))->toBeTrue()
            ->and($vessel->isShared('Instance2'))->toBeTrue()
            ->and($vessel->isShared('Instance3'))->toBeTrue();

        $vessel->forgetInstances();

        expect($vessel->isShared('Instance1'))->toBeFalse()
            ->and($vessel->isShared('Instance2'))->toBeFalse()
            ->and($vessel->isShared('Instance3'))->toBeFalse();
    });
});

describe('rebinding', function () {
    test('a rebound listener fires when a binding is replaced', function () {
        unset($_SERVER['__test.rebind']);

        $vessel = new Vessel;
        $vessel->bind('foo', function () {
            //
        });
        $vessel->rebinding('foo', function () {
            $_SERVER['__test.rebind'] = true;
        });
        $vessel->bind('foo', function () {
            //
        });

        expect($_SERVER['__test.rebind'])->toBeTrue();
    });

    test('a rebound listener fires when an instance is replaced', function () {
        unset($_SERVER['__test.rebind']);

        $vessel = new Vessel;
        $vessel->instance('foo', function () {
            //
        });
        $vessel->rebinding('foo', function () {
            $_SERVER['__test.rebind'] = true;
        });
        $vessel->instance('foo', function () {
            //
        });

        expect($_SERVER['__test.rebind'])->toBeTrue();
    });

    test('a rebound listener does not fire for a first-time instance', function () {
        $_SERVER['__test.rebind'] = false;

        $vessel = new Vessel;
        $vessel->rebinding('foo', function () {
            $_SERVER['__test.rebind'] = true;
        });
        $vessel->instance('foo', function () {
            //
        });

        expect($_SERVER['__test.rebind'])->toBeFalse();
    });
});

describe('resolution failures', function () {
    test('an unresolvable primitive on an internal class throws', function () {
        (new Vessel)->make(ContainerMixedPrimitiveStub::class, []);
    })->throws(
        BindingResolutionException::class,
        'Unresolvable dependency resolving [Parameter #0 [ <required> $first ]] in class Tests\Vessel\Fixtures\ContainerMixedPrimitiveStub',
    );

    test('an unbound interface reports that it is not instantiable', function () {
        (new Vessel)->make(IVesselContractStub::class, []);
    })->throws(BindingResolutionException::class, 'Target [Tests\Vessel\Fixtures\IVesselContractStub] is not instantiable.');

    test('the message includes the build stack', function () {
        (new Vessel)->make(ContainerDependentStub::class, []);
    })->throws(
        BindingResolutionException::class,
        'Target [Tests\Vessel\Fixtures\IVesselContractStub] is not instantiable while building [Tests\Vessel\Fixtures\ContainerDependentStub].',
    );

    test('a missing class reports that it does not exist', function () {
        (new Vessel)->build('Foo\Bar\Baz\DummyClass');
    })->throws(BindingResolutionException::class, 'Target class [Foo\Bar\Baz\DummyClass] does not exist.');

    test('an unknown entry throws EntryNotFoundException', function () {
        (new Vessel)->get('Taylor');
    })->throws(EntryNotFoundException::class);

    test('a bound but unresolvable entry throws a container exception', function () {
        $vessel = new Vessel;
        $vessel->bind('Taylor', IVesselContractStub::class);

        try {
            $vessel->get('Taylor');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(ContainerExceptionInterface::class);

            return;
        }

        $this->fail('No container exception was thrown.');
    });
});

test('flush clears bindings, aliases and resolved instances', function () {
    $vessel = new Vessel;
    $vessel->bind('ConcreteStub', fn () => new ContainerConcreteStub, true);
    $vessel->alias('ConcreteStub', 'ContainerConcreteStub');
    $vessel->make('ConcreteStub');

    expect($vessel->resolved('ConcreteStub'))->toBeTrue()
        ->and($vessel->isAlias('ContainerConcreteStub'))->toBeTrue()
        ->and($vessel->getBindings())->toHaveKey('ConcreteStub')
        ->and($vessel->isShared('ConcreteStub'))->toBeTrue();

    $vessel->flush();

    expect($vessel->resolved('ConcreteStub'))->toBeFalse()
        ->and($vessel->isAlias('ContainerConcreteStub'))->toBeFalse()
        ->and($vessel->getBindings())->toBeEmpty()
        ->and($vessel->isShared('ConcreteStub'))->toBeFalse();
});

test('resolved follows an alias to the binding name', function () {
    $vessel = new Vessel;
    $vessel->bind('ConcreteStub', fn () => new ContainerConcreteStub, true);
    $vessel->alias('ConcreteStub', 'foo');

    expect($vessel->resolved('ConcreteStub'))->toBeFalse()
        ->and($vessel->resolved('foo'))->toBeFalse();

    $vessel->make('ConcreteStub');

    expect($vessel->resolved('ConcreteStub'))->toBeTrue()
        ->and($vessel->resolved('foo'))->toBeTrue();
});

test('factory returns a closure that resolves the binding', function () {
    $vessel = new Vessel;
    $vessel->bind('name', fn () => 'Taylor');

    $factory = $vessel->factory('name');

    expect($factory())->toEqual($vessel->make('name'));
});

test('has reports a bound entry', function () {
    $vessel = new Vessel;
    $vessel->bind(IVesselContractStub::class, ContainerImplementationStub::class);

    expect($vessel->has(IVesselContractStub::class))->toBeTrue();
});

test('contextual binding applies at the method level', function () {
    $vessel = new Vessel;
    $vessel->bind(IVesselContractStub::class, ContainerImplementationStubTwo::class);

    $vessel->when(ContainerContextualBindingCallTarget::class)
        ->needs(IVesselContractStub::class)
        ->give(ContainerImplementationStub::class);

    expect($vessel->call([new ContainerContextualBindingCallTarget, 'work']))->toBeInstanceOf(ContainerImplementationStub::class);
});

describe('binding attributes', function () {
    test('the Singleton attribute shares the instance', function () {
        $vessel = new Vessel;

        expect($vessel->get(ContainerSingletonAttribute::class))->toBe($vessel->get(ContainerSingletonAttribute::class));
    });

    test('the Scoped attribute shares the instance until scopes are forgotten', function () {
        $vessel = new Vessel;
        $firstInstantiation = $vessel->get(ContainerScopedAttribute::class);

        expect($vessel->get(ContainerScopedAttribute::class))->toBe($firstInstantiation);

        $vessel->forgetScopedInstances();

        expect($vessel->get(ContainerScopedAttribute::class))->not->toBe($firstInstantiation);
    });

    test('an interface may be bound to a singleton', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($arr) => true);

        expect($vessel->get(ContainerBindSingletonTestInterface::class))->toBe($vessel->get(ContainerBindSingletonTestInterface::class));
    });

    test('an interface may be bound to a scoped instance', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($arr) => $arr === ['test']);
        $firstInstantiation = $vessel->get(ContainerBindScopedTestInterface::class);

        expect($vessel->get(ContainerBindScopedTestInterface::class))->toBe($firstInstantiation);

        // With a different environment
        $vessel->resolveEnvironmentUsing(fn ($arr) => $arr === ['test2']);

        expect($vessel->get(ContainerBindScopedTestInterface::class))->toBe($firstInstantiation);

        $vessel->forgetScopedInstances();

        expect($vessel->get(ContainerBindScopedTestInterface::class))->not->toBe($firstInstantiation);
    });

    test('a scoped Bind attribute shares until scopes are forgotten', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($environments) => true);

        $original = $vessel->make(IsScoped::class);

        expect($vessel->make(IsScoped::class))->toBe($original);

        $vessel->forgetScopedInstances();

        expect($vessel->make(IsScoped::class))->not->toBe($original);
    });

    test('a singleton Bind attribute shares the instance', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($environments) => true);

        expect($vessel->make(IsSingleton::class))->toBe($vessel->make(IsSingleton::class));
    });

    test('a Bind factory resolves its own dependencies', function () {
        $vessel = new Vessel;
        $_SERVER['__withFactory.email'] = 'taylor@venusian.com';
        $_SERVER['__withFactory.userId'] = 999;

        $vessel->bind(RequestDtoDependencyContract::class, RequestDtoDependency::class);
        $r = $vessel->make(RequestDto::class);

        expect($r)->toBeInstanceOf(RequestDto::class)
            ->and($r->userId)->toEqual(999)
            ->and($r->email)->toEqual('taylor@venusian.com');
    });
});

describe('environment-scoped bindings', function () {
    test('a wildcard binding with no environment resolver throws', function () {
        (new Vessel)->make(WildcardOnlyInterface::class);
    })->throws(BindingResolutionException::class);

    test('a more specific environment wins over the wildcard', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($env) => in_array('prod', $env));

        expect($vessel->make(WildcardAndProdInterface::class))->toBeInstanceOf(ProdConcrete::class);

        $vessel->flush();
        $vessel->resolveEnvironmentUsing(fn ($env) => in_array('some_string', $env));

        expect($vessel->make(WildcardAndProdInterface::class))->toBeInstanceOf(FallbackConcrete::class);
    });

    test('the environment may be given as a string', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($env) => in_array('cli', $env));

        expect($vessel->make(CliOnlyInterface::class))->toBeInstanceOf(CliConcrete::class);
    });

    test('an empty environment list throws', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn () => true);
        $vessel->make(EmptyEnvInterface::class);
    })->throws(InvalidArgumentException::class);

    test('an explicit container binding takes precedence', function () {
        $vessel = new Vessel;
        $vessel->bind(OverrideInterface::class, AltConcrete::class);

        expect($vessel->make(OverrideInterface::class))->toBeInstanceOf(AltConcrete::class);
    });

    test('flush resets the environment resolver and the checked bindings', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn ($environments) => in_array('prod', $environments));

        expect($vessel->make(MultiEnvInterface::class))->toBeInstanceOf(ProdConcrete::class);

        $vessel->flush();
        $vessel->resolveEnvironmentUsing(fn (array $environments) => in_array('dev', $environments));

        expect($vessel->make(MultiEnvInterface::class))->toBeInstanceOf(DevConcrete::class);
    });

    test('no matching environment and no wildcard throws', function () {
        $vessel = new Vessel;
        $vessel->resolveEnvironmentUsing(fn () => false);

        $vessel->make(ProdEnvOnlyInterface::class);
    })->throws(BindingResolutionException::class);
});
