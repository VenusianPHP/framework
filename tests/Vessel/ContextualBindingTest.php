<?php

use Tests\Vessel\Fixtures\ContainerConcreteStub;
use Tests\Vessel\Fixtures\ContainerContextImplementationStub;
use Tests\Vessel\Fixtures\ContainerContextImplementationStubTwo;
use Tests\Vessel\Fixtures\ContainerImplementationStub;
use Tests\Vessel\Fixtures\ContainerInjectVariableStub;
use Tests\Vessel\Fixtures\ContainerTestContextInjectArray;
use Tests\Vessel\Fixtures\ContainerTestContextInjectFromConfigArray;
use Tests\Vessel\Fixtures\ContainerTestContextInjectFromConfigIndividualValues;
use Tests\Vessel\Fixtures\ContainerTestContextInjectInstantiations;
use Tests\Vessel\Fixtures\ContainerTestContextInjectMethodArgument;
use Tests\Vessel\Fixtures\ContainerTestContextInjectOne;
use Tests\Vessel\Fixtures\ContainerTestContextInjectThree;
use Tests\Vessel\Fixtures\ContainerTestContextInjectTwo;
use Tests\Vessel\Fixtures\ContainerTestContextInjectTwoInstances;
use Tests\Vessel\Fixtures\ContainerTestContextInjectVariadic;
use Tests\Vessel\Fixtures\ContainerTestContextInjectVariadicAfterNonVariadic;
use Tests\Vessel\Fixtures\ContainerTestContextWithOptionalInnerDependency;
use Tests\Vessel\Fixtures\IContainerContextContractStub;
use Voyager\Config\Repository;
use Voyager\Vessel\Vessel;

describe('different implementations per consumer', function () {
    test('a concrete class may be given per context', function () {
        $vessel = new Vessel;
        $vessel->bind(IContainerContextContractStub::class, ContainerContextImplementationStub::class);

        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStub::class);
        $vessel->when(ContainerTestContextInjectTwo::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($vessel->make(ContainerTestContextInjectTwo::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('a closure may be given per context', function () {
        $vessel = new Vessel;
        $vessel->bind(IContainerContextContractStub::class, ContainerContextImplementationStub::class);

        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStub::class);
        $vessel->when(ContainerTestContextInjectTwo::class)->needs(IContainerContextContractStub::class)->give(fn ($vessel) => $vessel->make(ContainerContextImplementationStubTwo::class));

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($vessel->make(ContainerTestContextInjectTwo::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('a closure may resolve the same abstract from the outer context', function () {
        $vessel = new Vessel;
        $vessel->bind(IContainerContextContractStub::class, ContainerContextImplementationStub::class);

        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(fn ($vessel) => $vessel->make(IContainerContextContractStub::class));

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStub::class);
    });
});

describe('instances and aliases', function () {
    test('contextual binding beats an instance registered first', function () {
        $vessel = new Vessel;
        $vessel->instance(IContainerContextContractStub::class, new ContainerImplementationStub);
        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('contextual binding beats an instance registered afterwards', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);
        $vessel->instance(IContainerContextContractStub::class, new ContainerImplementationStub);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('contextual binding beats an existing aliased instance', function () {
        $vessel = new Vessel;
        $vessel->instance('stub', new ContainerImplementationStub);
        $vessel->alias('stub', IContainerContextContractStub::class);
        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('contextual binding beats a newly aliased instance', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);
        $vessel->instance('stub', new ContainerImplementationStub);
        $vessel->alias('stub', IContainerContextContractStub::class);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('contextual binding beats a newly aliased binding', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);
        $vessel->bind('stub', ContainerContextImplementationStub::class);
        $vessel->alias('stub', IContainerContextContractStub::class);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('a stale alias is not followed', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectOne::class)->needs('stale')->give(ContainerContextImplementationStub::class);
        $vessel->when(ContainerTestContextInjectOne::class)->needs('live')->give(ContainerContextImplementationStubTwo::class);

        $vessel->alias(IContainerContextContractStub::class, 'stale');
        $vessel->alias('unrelated', 'stale');
        $vessel->alias(IContainerContextContractStub::class, 'live');

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('contextual binding works with aliased targets on both sides', function () {
        $vessel = new Vessel;
        $vessel->bind(IContainerContextContractStub::class, ContainerContextImplementationStub::class);
        $vessel->alias(IContainerContextContractStub::class, 'interface-stub');
        $vessel->alias(ContainerContextImplementationStub::class, 'stub-1');

        $vessel->when(ContainerTestContextInjectOne::class)->needs('interface-stub')->give('stub-1');
        $vessel->when(ContainerTestContextInjectTwo::class)->needs('interface-stub')->give(ContainerContextImplementationStubTwo::class);

        expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($vessel->make(ContainerTestContextInjectTwo::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });
});

test('a contextual binding may target several consumers at once', function () {
    $vessel = new Vessel;
    $vessel->bind(IContainerContextContractStub::class, ContainerContextImplementationStub::class);

    $vessel->when([ContainerTestContextInjectTwo::class, ContainerTestContextInjectThree::class])
        ->needs(IContainerContextContractStub::class)
        ->give(ContainerContextImplementationStubTwo::class);

    expect($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStub::class)
        ->and($vessel->make(ContainerTestContextInjectTwo::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class)
        ->and($vessel->make(ContainerTestContextInjectThree::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
});

test('a contextual binding does not override non-contextual resolution', function () {
    $vessel = new Vessel;
    $vessel->instance('stub', new ContainerContextImplementationStub);
    $vessel->alias('stub', IContainerContextContractStub::class);

    $vessel->when(ContainerTestContextInjectTwo::class)->needs(IContainerContextContractStub::class)->give(ContainerContextImplementationStubTwo::class);

    expect($vessel->make(ContainerTestContextInjectTwo::class)->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class)
        ->and($vessel->make(ContainerTestContextInjectOne::class)->impl)->toBeInstanceOf(ContainerContextImplementationStub::class);
});

test('a contextually bound instance is not recreated on every resolution', function () {
    ContainerTestContextInjectInstantiations::$instantiations = 0;

    $vessel = new Vessel;
    $vessel->instance(IContainerContextContractStub::class, new ContainerImplementationStub);
    $vessel->instance(ContainerTestContextInjectInstantiations::class, new ContainerTestContextInjectInstantiations);

    expect(ContainerTestContextInjectInstantiations::$instantiations)->toEqual(1);

    $vessel->when(ContainerTestContextInjectOne::class)->needs(IContainerContextContractStub::class)->give(ContainerTestContextInjectInstantiations::class);

    $vessel->make(ContainerTestContextInjectOne::class);
    $vessel->make(ContainerTestContextInjectOne::class);
    $vessel->make(ContainerTestContextInjectOne::class);
    $vessel->make(ContainerTestContextInjectOne::class);

    expect(ContainerTestContextInjectInstantiations::$instantiations)->toEqual(1);
});

describe('primitive injection', function () {
    test('a scalar may be given for a named variable', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerInjectVariableStub::class)->needs('$something')->give(100);

        expect($vessel->make(ContainerInjectVariableStub::class)->something)->toEqual(100);
    });

    test('a closure may be given for a named variable', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerInjectVariableStub::class)->needs('$something')->give(fn ($vessel) => $vessel->make(ContainerConcreteStub::class));

        expect($vessel->make(ContainerInjectVariableStub::class)->something)->toBeInstanceOf(ContainerConcreteStub::class);
    });
});

test('contextual binding works for nested optional dependencies', function () {
    $vessel = new Vessel;

    $vessel->when(ContainerTestContextInjectTwoInstances::class)
        ->needs(ContainerTestContextInjectTwo::class)
        ->give(fn () => new ContainerTestContextInjectTwo(new ContainerContextImplementationStubTwo));

    $resolvedInstance = $vessel->make(ContainerTestContextInjectTwoInstances::class);

    expect($resolvedInstance->implOne)->toBeInstanceOf(ContainerTestContextWithOptionalInnerDependency::class)
        ->and($resolvedInstance->implOne->inner)->toBeNull()
        ->and($resolvedInstance->implTwo)->toBeInstanceOf(ContainerTestContextInjectTwo::class)
        ->and($resolvedInstance->implTwo->impl)->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
});

describe('variadic dependencies', function () {
    test('a factory closure fills the variadic', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectVariadic::class)->needs(IContainerContextContractStub::class)->give(fn ($c) => [
            $c->make(ContainerContextImplementationStub::class),
            $c->make(ContainerContextImplementationStubTwo::class),
        ]);

        $resolvedInstance = $vessel->make(ContainerTestContextInjectVariadic::class);

        expect($resolvedInstance->stubs)->toHaveCount(2)
            ->and($resolvedInstance->stubs[0])->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($resolvedInstance->stubs[1])->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('nothing bound leaves the variadic empty', function () {
        expect((new Vessel)->make(ContainerTestContextInjectVariadic::class)->stubs)->toHaveCount(0);
    });

    test('a factory closure fills a variadic that follows a non-variadic parameter', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectVariadicAfterNonVariadic::class)->needs(IContainerContextContractStub::class)->give(fn ($c) => [
            $c->make(ContainerContextImplementationStub::class),
            $c->make(ContainerContextImplementationStubTwo::class),
        ]);

        $resolvedInstance = $vessel->make(ContainerTestContextInjectVariadicAfterNonVariadic::class);

        expect($resolvedInstance->stubs)->toHaveCount(2)
            ->and($resolvedInstance->stubs[0])->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($resolvedInstance->stubs[1])->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('nothing bound leaves a variadic after a non-variadic parameter empty', function () {
        expect((new Vessel)->make(ContainerTestContextInjectVariadicAfterNonVariadic::class)->stubs)->toHaveCount(0);
    });

    test('an array of class names fills the variadic without a factory', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectVariadic::class)->needs(IContainerContextContractStub::class)->give([
            ContainerContextImplementationStub::class,
            ContainerContextImplementationStubTwo::class,
        ]);

        $resolvedInstance = $vessel->make(ContainerTestContextInjectVariadic::class);

        expect($resolvedInstance->stubs)->toHaveCount(2)
            ->and($resolvedInstance->stubs[0])->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($resolvedInstance->stubs[1])->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });
});

describe('giveTagged', function () {
    test('an undefined tag gives an empty array', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectArray::class)->needs('$stubs')->giveTagged('stub');

        expect($vessel->make(ContainerTestContextInjectArray::class)->stubs)->toHaveCount(0);
    });

    test('an undefined tag leaves a variadic empty', function () {
        $vessel = new Vessel;
        $vessel->when(ContainerTestContextInjectVariadic::class)->needs(IContainerContextContractStub::class)->giveTagged('stub');

        expect($vessel->make(ContainerTestContextInjectVariadic::class)->stubs)->toHaveCount(0);
    });

    test('a defined tag fills an array parameter', function () {
        $vessel = new Vessel;
        $vessel->tag([
            ContainerContextImplementationStub::class,
            ContainerContextImplementationStubTwo::class,
        ], ['stub']);

        $vessel->when(ContainerTestContextInjectArray::class)->needs('$stubs')->giveTagged('stub');

        $resolvedInstance = $vessel->make(ContainerTestContextInjectArray::class);

        expect($resolvedInstance->stubs)->toHaveCount(2)
            ->and($resolvedInstance->stubs[0])->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($resolvedInstance->stubs[1])->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });

    test('a defined tag fills a variadic parameter', function () {
        $vessel = new Vessel;
        $vessel->tag([
            ContainerContextImplementationStub::class,
            ContainerContextImplementationStubTwo::class,
        ], ['stub']);

        $vessel->when(ContainerTestContextInjectVariadic::class)->needs(IContainerContextContractStub::class)->giveTagged('stub');

        $resolvedInstance = $vessel->make(ContainerTestContextInjectVariadic::class);

        expect($resolvedInstance->stubs)->toHaveCount(2)
            ->and($resolvedInstance->stubs[0])->toBeInstanceOf(ContainerContextImplementationStub::class)
            ->and($resolvedInstance->stubs[1])->toBeInstanceOf(ContainerContextImplementationStubTwo::class);
    });
});

describe('giveConfig', function () {
    test('an absent optional key resolves to null', function () {
        $vessel = new Vessel;
        $vessel->singleton('config', fn () => new Repository([
            'test' => [
                'username' => 'venusian',
                'password' => 'hunter42',
            ],
        ]));

        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$username')->giveConfig('test.username');
        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$password')->giveConfig('test.password');

        $resolvedInstance = $vessel->make(ContainerTestContextInjectFromConfigIndividualValues::class);

        expect($resolvedInstance->username)->toBe('venusian')
            ->and($resolvedInstance->password)->toBe('hunter42')
            ->and($resolvedInstance->alias)->toBeNull();
    });

    test('a present optional key is injected', function () {
        $vessel = new Vessel;
        $vessel->singleton('config', fn () => new Repository([
            'test' => [
                'username' => 'venusian',
                'password' => 'hunter42',
                'alias' => 'lumen',
            ],
        ]));

        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$username')->giveConfig('test.username');
        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$password')->giveConfig('test.password');
        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$alias')->giveConfig('test.alias');

        $resolvedInstance = $vessel->make(ContainerTestContextInjectFromConfigIndividualValues::class);

        expect($resolvedInstance->username)->toBe('venusian')
            ->and($resolvedInstance->password)->toBe('hunter42')
            ->and($resolvedInstance->alias)->toBe('lumen');
    });

    test('a missing key falls back to the given default', function () {
        $vessel = new Vessel;
        $vessel->singleton('config', fn () => new Repository([
            'test' => [
                'password' => 'hunter42',
            ],
        ]));

        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$username')->giveConfig('test.username', 'DEFAULT_USERNAME');
        $vessel->when(ContainerTestContextInjectFromConfigIndividualValues::class)->needs('$password')->giveConfig('test.password');

        $resolvedInstance = $vessel->make(ContainerTestContextInjectFromConfigIndividualValues::class);

        expect($resolvedInstance->username)->toBe('DEFAULT_USERNAME')
            ->and($resolvedInstance->password)->toBe('hunter42')
            ->and($resolvedInstance->alias)->toBeNull();
    });

    test('a whole config section may be injected as an array', function () {
        $vessel = new Vessel;
        $vessel->singleton('config', fn () => new Repository([
            'test' => [
                'username' => 'venusian',
                'password' => 'hunter42',
                'alias' => 'lumen',
            ],
        ]));

        $vessel->when(ContainerTestContextInjectFromConfigArray::class)->needs('$settings')->giveConfig('test');

        $resolvedInstance = $vessel->make(ContainerTestContextInjectFromConfigArray::class);

        expect($resolvedInstance->settings['username'])->toBe('venusian')
            ->and($resolvedInstance->settings['password'])->toBe('hunter42')
            ->and($resolvedInstance->settings['alias'])->toBe('lumen');
    });
});

test('contextual binding applies to method invocation', function () {
    $vessel = new Vessel;

    $vessel->when(ContainerTestContextInjectMethodArgument::class)
        ->needs(IContainerContextContractStub::class)
        ->give(ContainerContextImplementationStub::class);

    $object = new ContainerTestContextInjectMethodArgument;

    // array callable syntax...
    expect($vessel->call([$object, 'method']))->toBeInstanceOf(ContainerContextImplementationStub::class)
        // first class callable syntax...
        ->and($vessel->call($object->method(...)))->toBeInstanceOf(ContainerContextImplementationStub::class);
});
