<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Guard as GuardContract;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Instrument\Model;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Tests\Vessel\Fixtures\AuthedTest;
use Tests\Vessel\Fixtures\CacheTest;
use Tests\Vessel\Fixtures\ConfigTest;
use Tests\Vessel\Fixtures\ComplexDependency;
use Tests\Vessel\Fixtures\ContainerTestAttributeThatResolvesContractImpl;
use Tests\Vessel\Fixtures\ContainerTestConfigValue;
use Tests\Vessel\Fixtures\ContainerTestContract;
use Tests\Vessel\Fixtures\ContainerTestHasAttributeThatResolvesToImplA;
use Tests\Vessel\Fixtures\ContainerTestHasAttributeThatResolvesToImplB;
use Tests\Vessel\Fixtures\ContainerTestHasConfigValueProperty;
use Tests\Vessel\Fixtures\ContainerTestHasConfigValueWithResolveProperty;
use Tests\Vessel\Fixtures\ContainerTestHasConfigValueWithResolvePropertyAndAfterCallback;
use Tests\Vessel\Fixtures\ContainerTestImplA;
use Tests\Vessel\Fixtures\ContainerTestImplB;
use Tests\Vessel\Fixtures\ContextHiddenTest;
use Tests\Vessel\Fixtures\ContextTest;
use Tests\Vessel\Fixtures\DatabaseTest;
use Tests\Vessel\Fixtures\GiveTestComplex;
use Tests\Vessel\Fixtures\GiveTestSimple;
use Tests\Vessel\Fixtures\GuardTest;
use Tests\Vessel\Fixtures\LocaleObject;
use Tests\Vessel\Fixtures\LogTest;
use Tests\Vessel\Fixtures\RouteParameterTest;
use Tests\Vessel\Fixtures\SimpleDependency;
use Tests\Vessel\Fixtures\StorageTest;
use Tests\Vessel\Fixtures\TimezoneObject;
use Voyager\Config\Repository;
use Voyager\Vessel\Attributes\Config;
use Voyager\Vessel\Attributes\Tag;
use Voyager\Vessel\RewindableGenerator;
use Voyager\Vessel\Vessel;

test('a dependency can be resolved from an attribute binding', function () {
    $vessel = new Vessel;

    $vessel->bind(ContainerTestContract::class, fn (): ContainerTestImplB => new ContainerTestImplB);
    $vessel->whenHasAttribute(ContainerTestAttributeThatResolvesContractImpl::class, function (ContainerTestAttributeThatResolvesContractImpl $attribute) {
        return match ($attribute->name) {
            'A' => new ContainerTestImplA,
            'B' => new ContainerTestImplB
        };
    });

    $classA = $vessel->make(ContainerTestHasAttributeThatResolvesToImplA::class);

    expect($classA)->toBeInstanceOf(ContainerTestHasAttributeThatResolvesToImplA::class)
        ->and($classA->property)->toBeInstanceOf(ContainerTestImplA::class);

    $classB = $vessel->make(ContainerTestHasAttributeThatResolvesToImplB::class);

    expect($classB)->toBeInstanceOf(ContainerTestHasAttributeThatResolvesToImplB::class)
        ->and($classB->property)->toBeInstanceOf(ContainerTestImplB::class);
});

test('a simple dependency is resolved from a Give attribute', function () {
    $vessel = new Vessel;
    $vessel->bind(ContainerTestContract::class, concrete: ContainerTestImplA::class);

    expect($vessel->make(GiveTestSimple::class)->dependency)->toBeInstanceOf(SimpleDependency::class);
});

test('a complex dependency is resolved from a Give attribute', function () {
    $vessel = new Vessel;
    $vessel->bind(ContainerTestContract::class, concrete: ContainerTestImplA::class);

    $resolution = $vessel->make(GiveTestComplex::class);

    expect($resolution->dependency)->toBeInstanceOf(ComplexDependency::class)
        ->and($resolution->dependency->param)->toBeTrue();
});

test('a scalar dependency is resolved from an attribute binding', function () {
    $vessel = new Vessel;
    $vessel->singleton('config', fn () => new Repository([
        'app' => [
            'timezone' => 'Europe/Paris',
        ],
    ]));

    $vessel->whenHasAttribute(ContainerTestConfigValue::class, function (ContainerTestConfigValue $attribute, Vessel $vessel) {
        return $vessel->make('config')->get($attribute->key);
    });

    $class = $vessel->make(ContainerTestHasConfigValueProperty::class);

    expect($class)->toBeInstanceOf(ContainerTestHasConfigValueProperty::class)
        ->and($class->timezone)->toEqual('Europe/Paris');
});

test('a scalar dependency is resolved from the attribute resolve method', function () {
    $vessel = new Vessel;
    $vessel->singleton('config', fn () => new Repository([
        'app' => [
            'env' => 'production',
        ],
    ]));

    $class = $vessel->make(ContainerTestHasConfigValueWithResolveProperty::class);

    expect($class)->toBeInstanceOf(ContainerTestHasConfigValueWithResolveProperty::class)
        ->and($class->env)->toEqual('production');
});

test('a dependency with an after callback attribute can be resolved', function () {
    $class = (new Vessel)->make(ContainerTestHasConfigValueWithResolvePropertyAndAfterCallback::class);

    expect($class->person->role)->toEqual('Developer');
});

test('the Authenticated and CurrentUser attributes resolve the guard user', function () {
    $vessel = new Vessel;
    $vessel->singleton('auth', function () {
        $manager = Mockery::mock(AuthManager::class);
        $manager->shouldReceive('userResolver')->andReturn(fn ($guard = null) => $manager->guard($guard)->user());
        $manager->shouldReceive('guard')->with('foo')->andReturnUsing(function () {
            $guard = Mockery::mock(GuardContract::class);
            $guard->shouldReceive('user')->andReturn(m:mock(AuthenticatableContract::class));

            return $guard;
        });
        $manager->shouldReceive('guard')->with('bar')->andReturnUsing(function () {
            $guard = Mockery::mock(GuardContract::class);
            $guard->shouldReceive('user')->andReturn(m:mock(AuthenticatableContract::class));

            return $guard;
        });

        return $manager;
    });

    $vessel->make(AuthedTest::class);
});

test('the Cache attribute resolves a store', function () {
    $vessel = new Vessel;
    $vessel->singleton('cache', function () {
        $manager = Mockery::mock(CacheManager::class);
        $manager->shouldReceive('store')->with('foo')->andReturn(Mockery::mock(CacheRepository::class));
        $manager->shouldReceive('store')->with('bar')->andReturn(Mockery::mock(CacheRepository::class));

        return $manager;
    });

    $vessel->make(CacheTest::class);
});

test('the Config attribute resolves a config value', function () {
    $vessel = new Vessel;
    $vessel->singleton('config', function () {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('get')->with('foo', null)->andReturn('foo');
        $repository->shouldReceive('get')->with('bar', null)->andReturn('bar');

        return $repository;
    });

    $vessel->make(ConfigTest::class);
});

test('the Database attribute resolves a connection', function () {
    $vessel = new Vessel;
    $vessel->singleton('db', function () {
        $manager = Mockery::mock(DatabaseManager::class);
        $manager->shouldReceive('connection')->with('foo')->andReturn(Mockery::mock(Connection::class));
        $manager->shouldReceive('connection')->with('bar')->andReturn(Mockery::mock(Connection::class));

        return $manager;
    });

    $vessel->make(DatabaseTest::class);
});

test('the Auth attribute resolves a guard', function () {
    $vessel = new Vessel;
    $vessel->singleton('auth', function () {
        $manager = Mockery::mock(AuthManager::class);
        $manager->shouldReceive('guard')->with('foo')->andReturn(Mockery::mock(GuardContract::class));
        $manager->shouldReceive('guard')->with('bar')->andReturn(Mockery::mock(GuardContract::class));

        return $manager;
    });

    $vessel->make(GuardTest::class);
});

test('the Log attribute resolves a channel', function () {
    $vessel = new Vessel;
    $vessel->singleton('log', function () {
        $manager = Mockery::mock(LogManager::class);
        $manager->shouldReceive('channel')->with('foo')->andReturn(Mockery::mock(LoggerInterface::class));
        $manager->shouldReceive('channel')->with('bar')->andReturn(Mockery::mock(LoggerInterface::class));

        return $manager;
    });

    $vessel->make(LogTest::class);
});

test('the RouteParameter attribute resolves a route parameter', function () {
    $vessel = new Vessel;
    $vessel->singleton('request', function () {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('route')->with('foo')->andReturn(Mockery::mock(Model::class));
        $request->shouldReceive('route')->with('bar')->andReturn('bar');

        return $request;
    });

    $vessel->make(RouteParameterTest::class);
});

test('the Context attribute reads from the context repository', function () {
    $vessel = new Vessel;

    $vessel->singleton(ContextRepository::class, function () {
        $context = Mockery::mock(ContextRepository::class);
        $context->shouldReceive('get')->once()->with('foo', null)->andReturn('foo');

        return $context;
    });

    $vessel->make(ContextTest::class);
});

test('the Context attribute reads hidden context when asked', function () {
    $vessel = new Vessel;

    $vessel->singleton(ContextRepository::class, function () {
        $context = Mockery::mock(ContextRepository::class);
        $context->shouldReceive('getHidden')->once()->with('bar', null)->andReturn('bar');
        $context->shouldNotReceive('get');

        return $context;
    });

    $vessel->make(ContextHiddenTest::class);
});

test('the Storage attribute resolves a disk', function () {
    $vessel = new Vessel;
    $vessel->singleton('filesystem', function () {
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->with('foo')->andReturn(Mockery::mock(Filesystem::class));
        $manager->shouldReceive('disk')->with('bar')->andReturn(Mockery::mock(Filesystem::class));

        return $manager;
    });

    $vessel->make(StorageTest::class);
});

test('an attributed class injects into a called closure', function () {
    $person = (new Vessel)->call(function (ContainerTestHasConfigValueWithResolvePropertyAndAfterCallback $hasAttribute) {
        return $hasAttribute->person;
    });

    expect($person->name)->toEqual('Taylor');
});

test('an attribute on a called closure parameter resolves', function () {
    $vessel = new Vessel;
    $vessel->singleton('config', fn () => new Repository([
        'app' => [
            'timezone' => 'Europe/Paris',
            'locale' => null,
        ],
    ]));

    $value = $vessel->call(function (#[Config('app.timezone')] string $value) {
        return $value;
    });

    expect($value)->toEqual('Europe/Paris');

    $value = $vessel->call(function (#[Config('app.locale')] ?string $value) {
        return $value;
    });

    expect($value)->toBeNull();
});

test('an attribute nested inside a resolved object resolves', function () {
    $vessel = new Vessel;
    $vessel->singleton('config', fn () => new Repository([
        'app' => [
            'timezone' => 'Europe/Paris',
            'locale' => null,
        ],
    ]));

    $value = $vessel->call(function (TimezoneObject $object) {
        return $object;
    });

    expect($value->timezone)->toEqual('Europe/Paris');

    $value = $vessel->call(function (LocaleObject $object) {
        return $object;
    });

    expect($value->locale)->toBeNull();
});

test('the Tag attribute resolves a tagged generator', function () {
    $vessel = new Vessel;
    $vessel->bind('one', fn (): int => 1);
    $vessel->bind('two', fn (): int => 2);
    $vessel->tag(['one', 'two'], 'numbers');

    $value = $vessel->call(function (#[Tag('numbers')] RewindableGenerator $integers) {
        return $integers;
    });

    expect(iterator_to_array($value))->toEqual([1, 2]);
});
