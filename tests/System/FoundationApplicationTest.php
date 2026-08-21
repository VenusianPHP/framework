<?php

use Tests\System\Stubs\AbstractClass;
use Tests\System\Stubs\ApplicationBasicServiceProviderStub;
use Tests\System\Stubs\ApplicationBindingsServiceProviderStub;
use Tests\System\Stubs\ApplicationDeferredServiceProviderCountStub;
use Tests\System\Stubs\ApplicationDeferredServiceProviderStub;
use Tests\System\Stubs\ApplicationDeferredSharedServiceProviderStub;
use Tests\System\Stubs\ApplicationFactoryProviderStub;
use Tests\System\Stubs\ApplicationMultiProviderStub;
use Tests\System\Stubs\ApplicationSingletonsServiceProviderStub;
use Tests\System\Stubs\ConcreteClass;
use Tests\System\Stubs\ConcreteTerminator;
use Tests\System\Stubs\FileExistsFake;
use Tests\System\Stubs\InterfaceToImplementationDeferredServiceProvider;
use Tests\System\Stubs\NonContractBackedClass;
use Tests\System\Stubs\SampleImplementation;
use Tests\System\Stubs\SampleImplementationDeferredServiceProvider;
use Tests\System\Stubs\SampleInterface;
use Voyager\Config\Repository;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\System\Application;
use Voyager\System\Bootstrap\LoadConfiguration;
use Voyager\System\Bootstrap\RegisterMagicAliases;
use Voyager\System\Events\LocaleUpdated;

/** Clear the cache-path server variables the cache path tests set. */
function forgetCachePathEnvironment(): void
{
    unset(
        $_SERVER['APP_SERVICES_CACHE'],
        $_SERVER['APP_PACKAGES_CACHE'],
        $_SERVER['APP_CONFIG_CACHE'],
        $_SERVER['APP_ROUTES_CACHE'],
        $_SERVER['APP_EVENTS_CACHE']
    );
}

test('setLocale sets the locale and fires the LocaleUpdated event', function () {
    $app = new Application;

    $app['config'] = $config = Mockery::mock(stdClass::class);
    $config->shouldReceive('get')->once()->with('app.locale')->andReturn('bar');
    $config->shouldReceive('set')->once()->with('app.locale', 'foo');
    $app['translator'] = $trans = Mockery::mock(stdClass::class);
    $trans->shouldReceive('setLocale')->once()->with('foo');
    $app['events'] = $events = Mockery::mock(stdClass::class);
    $events->shouldReceive('dispatch')->once()->with(Mockery::on(function (LocaleUpdated $event) {
        return $event->locale === 'foo' && $event->previousLocale === 'bar';
    }));

    $app->setLocale('foo');
});

describe('service providers', function () {
    test('a registered provider is recorded as loaded', function () {
        $provider = Mockery::mock(ApplicationBasicServiceProviderStub::class);
        $class = get_class($provider);
        $provider->shouldReceive('register')->once();
        $app = new Application;
        $app->register($provider);

        expect($app->getLoadedProviders())->toHaveKey($class);
    });

    test('a provider bindings property binds classes', function () {
        $app = new Application;
        $app->register($provider = new ApplicationBindingsServiceProviderStub($app));

        expect($app->getLoadedProviders())->toHaveKey(get_class($provider));

        $instance = $app->make(AbstractClass::class);

        expect($instance)->toBeInstanceOf(ConcreteClass::class)
            ->and($app->make(AbstractClass::class))->not->toBe($instance);
    });

    test('a provider singletons property binds shared classes', function () {
        $app = new Application;
        $app->register($provider = new ApplicationSingletonsServiceProviderStub($app));

        expect($app->getLoadedProviders())->toHaveKey(get_class($provider));

        $instance = $app->make(AbstractClass::class);

        expect($instance)->toBeInstanceOf(ConcreteClass::class)
            ->and($app->make(AbstractClass::class))->toBe($instance);

        $instance = $app->make(NonContractBackedClass::class);

        expect($instance)->toBeInstanceOf(NonContractBackedClass::class)
            ->and($app->make(NonContractBackedClass::class))->toBe($instance);
    });

    test('a provider without its own register method is still recorded', function () {
        $provider = Mockery::mock(ServiceProvider::class);
        $class = get_class($provider);
        $provider->shouldReceive('register')->once();
        $app = new Application;
        $app->register($provider);

        expect($app->getLoadedProviders())->toHaveKey($class);
    });

    test('providerIsLoaded reports what has been registered', function () {
        $provider = Mockery::mock(ServiceProvider::class);
        $class = get_class($provider);
        $provider->shouldReceive('register')->once();
        $app = new Application;
        $app->register($provider);

        expect($app->providerIsLoaded($class))->toBeTrue()
            ->and($app->providerIsLoaded(ApplicationBasicServiceProviderStub::class))->toBeFalse();
    });
});

describe('deferred services', function () {
    test('are marked as bound before they are resolved', function () {
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationDeferredServiceProviderStub::class]);

        expect($app->bound('foo'))->toBeTrue()
            ->and($app->make('foo'))->toBe('foo');
    });

    test('are shared properly', function () {
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationDeferredSharedServiceProviderStub::class]);

        expect($app->bound('foo'))->toBeTrue();

        $one = $app->make('foo');
        $two = $app->make('foo');

        expect($one)->toBeInstanceOf(stdClass::class)
            ->and($two)->toBeInstanceOf(stdClass::class)
            ->and($two)->toBe($one);
    });

    test('can be extended', function () {
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationDeferredServiceProviderStub::class]);
        $app->extend('foo', function ($instance, $container) {
            return $instance.'bar';
        });

        expect($app->make('foo'))->toBe('foobar');
    });

    test('register their provider only once', function () {
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationDeferredServiceProviderCountStub::class]);
        $obj = $app->make('foo');

        expect($obj)->toBeInstanceOf(stdClass::class)
            ->and($app->make('foo'))->toBe($obj)
            ->and(ApplicationDeferredServiceProviderCountStub::$count)->toEqual(1);
    });

    test('do not run when an instance has been set', function () {
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationDeferredServiceProviderStub::class]);
        $app->instance('foo', 'bar');

        expect($app->make('foo'))->toBe('bar');
    });

    test('are lazily initialized', function () {
        ApplicationDeferredServiceProviderStub::$initialized = false;
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationDeferredServiceProviderStub::class]);

        expect($app->bound('foo'))->toBeTrue()
            ->and(ApplicationDeferredServiceProviderStub::$initialized)->toBeFalse();

        $app->extend('foo', function ($instance, $container) {
            return $instance.'bar';
        });

        expect(ApplicationDeferredServiceProviderStub::$initialized)->toBeFalse()
            ->and($app->make('foo'))->toBe('foobar')
            ->and(ApplicationDeferredServiceProviderStub::$initialized)->toBeTrue();
    });

    test('can register factories', function () {
        $app = new Application;
        $app->setDeferredServices(['foo' => ApplicationFactoryProviderStub::class]);

        expect($app->bound('foo'))->toBeTrue()
            ->and($app->make('foo'))->toEqual(1)
            ->and($app->make('foo'))->toEqual(2)
            ->and($app->make('foo'))->toEqual(3);
    });

    test('a single provider can provide several of them', function () {
        $app = new Application;
        $app->setDeferredServices([
            'foo' => ApplicationMultiProviderStub::class,
            'bar' => ApplicationMultiProviderStub::class,
        ]);

        expect($app->make('foo'))->toBe('foo')
            ->and($app->make('bar'))->toBe('foobar');
    });

    test('are loaded when the implementation is reached through its interface', function () {
        $app = new Application;
        $app->setDeferredServices([
            SampleInterface::class => InterfaceToImplementationDeferredServiceProvider::class,
            SampleImplementation::class => SampleImplementationDeferredServiceProvider::class,
        ]);

        expect($app->make(SampleInterface::class)->getPrimitive())->toBe('foo');
    });
});

describe('environment', function () {
    test('environment reports and matches the current environment', function () {
        $app = new Application;
        $app['env'] = 'foo';

        expect($app->environment())->toBe('foo')
            ->and($app->environment('foo'))->toBeTrue()
            ->and($app->environment('f*'))->toBeTrue()
            ->and($app->environment('foo', 'bar'))->toBeTrue()
            ->and($app->environment(['foo', 'bar']))->toBeTrue()
            ->and($app->environment('qux'))->toBeFalse()
            ->and($app->environment('q*'))->toBeFalse()
            ->and($app->environment('qux', 'bar'))->toBeFalse()
            ->and($app->environment(['qux', 'bar']))->toBeFalse();
    });

    test('the environment helpers agree with the environment', function () {
        $local = new Application;
        $local['env'] = 'local';

        expect($local->isLocal())->toBeTrue()
            ->and($local->isProduction())->toBeFalse()
            ->and($local->runningUnitTests())->toBeFalse();

        $production = new Application;
        $production['env'] = 'production';

        expect($production->isProduction())->toBeTrue()
            ->and($production->isLocal())->toBeFalse()
            ->and($production->runningUnitTests())->toBeFalse();

        $testing = new Application;
        $testing['env'] = 'testing';

        expect($testing->runningUnitTests())->toBeTrue()
            ->and($testing->isLocal())->toBeFalse()
            ->and($testing->isProduction())->toBeFalse();
    });

    test('hasDebugModeEnabled follows the config', function () {
        $debugOff = new Application;
        $debugOff['config'] = new Repository(['app' => ['debug' => false]]);

        expect($debugOff->hasDebugModeEnabled())->toBeFalse();

        $debugOn = new Application;
        $debugOn['config'] = new Repository(['app' => ['debug' => true]]);

        expect($debugOn->hasDebugModeEnabled())->toBeTrue();
    });
});

describe('bootstrapping hooks', function () {
    test('afterLoadingEnvironment listens for the environment bootstrapper', function () {
        $app = new Application;
        $app->afterLoadingEnvironment(function () {
            //
        });

        expect($app['events']->getListeners('bootstrapped: Voyager\System\Bootstrap\LoadEnvironmentVariables'))->toHaveKey(0);
    });

    test('beforeBootstrapping listens before the named bootstrapper', function () {
        $app = new Application;
        $app->beforeBootstrapping(RegisterMagicAliases::class, function () {
            //
        });

        expect($app['events']->getListeners('bootstrapping: Voyager\System\Bootstrap\RegisterMagicAliases'))->toHaveKey(0);
    });

    test('afterBootstrapping listens after the named bootstrapper', function () {
        $app = new Application;
        $app->afterBootstrapping(RegisterMagicAliases::class, function () {
            //
        });

        expect($app['events']->getListeners('bootstrapped: Voyager\System\Bootstrap\RegisterMagicAliases'))->toHaveKey(0);
    });
});

describe('termination', function () {
    test('terminating callbacks run in registration order', function () {
        $app = new Application;

        $result = [];
        $callback1 = function () use (&$result) {
            $result[] = 1;
        };

        $callback2 = function () use (&$result) {
            $result[] = 2;
        };

        $callback3 = function () use (&$result) {
            $result[] = 3;
        };

        $app->terminating($callback1);
        $app->terminating($callback2);
        $app->terminating($callback3);

        $app->terminate();

        expect($result)->toEqual([1, 2, 3]);
    });

    test('a terminating callback can be given in Class@method notation', function () {
        $app = new Application;
        $app->terminating(ConcreteTerminator::class.'@terminate');

        $app->terminate();

        expect(ConcreteTerminator::$counter)->toEqual(1);
    });
});

describe('boot callbacks', function () {
    test('booting callbacks all run, each handed the application', function () {
        $application = new Application;

        $counter = 0;
        $closure = function ($app) use (&$counter, $application) {
            $counter++;
            expect($app)->toBe($application);
        };

        $closure2 = function ($app) use (&$counter, $application) {
            $counter++;
            expect($app)->toBe($application);
        };

        $application->booting($closure);
        $application->booting($closure2);

        $application->boot();

        expect($counter)->toEqual(2);
    });

    test('booted callbacks run on boot, and immediately once booted', function () {
        $application = new Application;

        $counter = 0;
        $closure = function ($app) use (&$counter, $application) {
            $counter++;
            expect($app)->toBe($application);
        };

        $closure2 = function ($app) use (&$counter, $application) {
            $counter++;
            expect($app)->toBe($application);
        };

        $closure3 = function ($app) use (&$counter, $application) {
            $counter++;
            expect($app)->toBe($application);
        };

        $application->booting($closure);
        $application->booted($closure);
        $application->booted($closure2);
        $application->boot();

        expect($counter)->toEqual(3);

        $application->booted($closure3);

        expect($counter)->toEqual(4);
    });
});

test('getNamespace reads the application namespace out of composer.json', function () {
    $app1 = new Application(realpath(__DIR__.'/fixtures/laravel1'));
    $app2 = new Application(realpath(__DIR__.'/fixtures/laravel2'));

    expect($app1->getNamespace())->toBe('Laravel\\One\\')
        ->and($app2->getNamespace())->toBe('Laravel\\Two\\');
});

describe('cache paths', function () {
    afterEach(function () {
        forgetCachePathEnvironment();
    });

    test('resolve into the bootstrap cache directory', function () {
        $app = new Application('/base/path');

        $ds = DIRECTORY_SEPARATOR;

        expect($app->getCachedServicesPath())->toBe('/base/path'.$ds.'bootstrap'.$ds.'cache/services.php')
            ->and($app->getCachedPackagesPath())->toBe('/base/path'.$ds.'bootstrap'.$ds.'cache/packages.php')
            ->and($app->getCachedConfigPath())->toBe('/base/path'.$ds.'bootstrap'.$ds.'cache/config.php')
            ->and($app->getCachedEventsPath())->toBe('/base/path'.$ds.'bootstrap'.$ds.'cache/events.php');
    });

    test('an absolute path in the environment is used as given', function () {
        $app = new Application('/base/path');
        $_SERVER['APP_SERVICES_CACHE'] = '/absolute/path/services.php';
        $_SERVER['APP_PACKAGES_CACHE'] = '/absolute/path/packages.php';
        $_SERVER['APP_CONFIG_CACHE'] = '/absolute/path/config.php';
        $_SERVER['APP_ROUTES_CACHE'] = '/absolute/path/routes.php';
        $_SERVER['APP_EVENTS_CACHE'] = '/absolute/path/events.php';

        expect($app->getCachedServicesPath())->toBe('/absolute/path/services.php')
            ->and($app->getCachedPackagesPath())->toBe('/absolute/path/packages.php')
            ->and($app->getCachedConfigPath())->toBe('/absolute/path/config.php')
            ->and($app->getCachedEventsPath())->toBe('/absolute/path/events.php');
    });

    test('a relative path in the environment is anchored to the base path', function () {
        $app = new Application('/base/path');
        $_SERVER['APP_SERVICES_CACHE'] = 'relative/path/services.php';
        $_SERVER['APP_PACKAGES_CACHE'] = 'relative/path/packages.php';
        $_SERVER['APP_CONFIG_CACHE'] = 'relative/path/config.php';
        $_SERVER['APP_ROUTES_CACHE'] = 'relative/path/routes.php';
        $_SERVER['APP_EVENTS_CACHE'] = 'relative/path/events.php';

        $ds = DIRECTORY_SEPARATOR;

        expect($app->getCachedServicesPath())->toBe('/base/path'.$ds.'relative/path/services.php')
            ->and($app->getCachedPackagesPath())->toBe('/base/path'.$ds.'relative/path/packages.php')
            ->and($app->getCachedConfigPath())->toBe('/base/path'.$ds.'relative/path/config.php')
            ->and($app->getCachedEventsPath())->toBe('/base/path'.$ds.'relative/path/events.php');
    });

    test('a relative path with no base path is anchored at the root', function () {
        $app = new Application;
        $_SERVER['APP_SERVICES_CACHE'] = 'relative/path/services.php';
        $_SERVER['APP_PACKAGES_CACHE'] = 'relative/path/packages.php';
        $_SERVER['APP_CONFIG_CACHE'] = 'relative/path/config.php';
        $_SERVER['APP_ROUTES_CACHE'] = 'relative/path/routes.php';
        $_SERVER['APP_EVENTS_CACHE'] = 'relative/path/events.php';

        $ds = DIRECTORY_SEPARATOR;

        expect($app->getCachedServicesPath())->toBe($ds.'relative/path/services.php')
            ->and($app->getCachedPackagesPath())->toBe($ds.'relative/path/packages.php')
            ->and($app->getCachedConfigPath())->toBe($ds.'relative/path/config.php')
            ->and($app->getCachedEventsPath())->toBe($ds.'relative/path/events.php');
    });

    test('a registered windows drive prefix counts as absolute', function () {
        $app = new Application(__DIR__);
        $app->addAbsoluteCachePathPrefix('C:');
        $_SERVER['APP_SERVICES_CACHE'] = 'C:\framework\services.php';
        $_SERVER['APP_PACKAGES_CACHE'] = 'C:\framework\packages.php';
        $_SERVER['APP_CONFIG_CACHE'] = 'C:\framework\config.php';
        $_SERVER['APP_ROUTES_CACHE'] = 'C:\framework\routes.php';
        $_SERVER['APP_EVENTS_CACHE'] = 'C:\framework\events.php';

        expect($app->getCachedServicesPath())->toBe('C:\framework\services.php')
            ->and($app->getCachedPackagesPath())->toBe('C:\framework\packages.php')
            ->and($app->getCachedConfigPath())->toBe('C:\framework\config.php')
            ->and($app->getCachedEventsPath())->toBe('C:\framework\events.php');
    });
});

test('the application is macroable', function () {
    $app = new Application;
    $app['env'] = 'foo';

    $app->macro('foo', function () {
        return $this->environment('foo');
    });

    expect($app->foo())->toBeTrue();

    $app['env'] = 'bar';

    expect($app->foo())->toBeFalse();
});

test('useConfigPath points the configuration loader at another directory', function () {
    $app = new Application;
    $app->useConfigPath(__DIR__.'/fixtures/config');
    $app->bootstrapWith([LoadConfiguration::class]);

    expect($app->make('config')->get('app.foo'))->toBe('bar');
});

test('the application configuration is merged over the framework one', function () {
    $app = new Application;
    $app->useConfigPath(__DIR__.'/fixtures/config');
    $app->bootstrapWith([LoadConfiguration::class]);

    $config = $app->make('config');

    // The framework's own config/app.php is merged underneath the application's,
    // so a key the application omits still resolves, and one it sets wins.
    expect($config->get('app.timezone'))->toBe('UTC')
        ->and($config->get('app.foo'))->toBe('bar');

    // A config file the framework does not ship is taken from the application
    // verbatim, nested arrays included.
    expect($config->get('broadcasting.default'))->toBe('overwrite')
        ->and($config->get('broadcasting.custom_option'))->toBe('broadcasting')
        ->and($config->get('broadcasting.connections.reverb'))->toBe(['overwrite' => true])
        ->and($config->get('broadcasting.connections.new'))->toBe(['merge' => true]);
});

describe('eventsAreCached', function () {
    test('uses the container instance when one is bound', function () {
        $app = new Application;
        $app->instance('events.cached', true);
        $files = new FileExistsFake;
        $app->instance('files', $files);

        expect($app->eventsAreCached())->toBeTrue()
            ->and(isset($files->pathRequested))->toBeFalse();
    });

    test('checks the filesystem when nothing is bound', function () {
        $app = new Application;
        $files = new FileExistsFake;
        $app->instance('files', $files);

        expect($app->eventsAreCached())->toBeFalse()
            ->and($files->pathRequested)->toContain('events.php')
            ->and($app->bound('events.cached'))->toBeTrue()
            ->and($app->make('events.cached'))->toBeFalse();
    });
});

test('the core container aliases are registered by default', function () {
    $app = new Application;

    expect($app->isAlias(\Voyager\Contracts\Translation\Translator::class))->toBeTrue()
        ->and($app->getAlias(\Voyager\Contracts\Translation\Translator::class))->toBe('translator')
        ->and($app->isAlias(\Voyager\Contracts\Auth\PasswordBrokerFactory::class))->toBeTrue()
        ->and($app->getAlias(\Voyager\Contracts\Auth\PasswordBrokerFactory::class))->toBe('auth.password')
        ->and($app->isAlias(\Voyager\Contracts\Auth\PasswordBroker::class))->toBeTrue()
        ->and($app->getAlias(\Voyager\Contracts\Auth\PasswordBroker::class))->toBe('auth.password.broker');
});
