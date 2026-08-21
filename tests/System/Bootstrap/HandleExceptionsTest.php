<?php

use Monolog\Handler\NullHandler;
use Tests\System\Stubs\CustomNullHandler;
use Voyager\Config\Repository as Config;
use Voyager\Log\LogManager;
use Voyager\NutsAndBolts\DataObjects\Env;
use Voyager\System\Application;
use Voyager\System\Bootstrap\HandleExceptions;

/** A HandleExceptions bootstrapper already pointed at the given application. */
function handleExceptionsFor($app): HandleExceptions
{
    return tap(new HandleExceptions, function ($instance) use ($app) {
        (new ReflectionClass($instance))->getProperty('app')->setValue($instance, $app);
    });
}

/** The deprecation message every case reports, and where it came from. */
const DEPRECATION_MESSAGE = 'str_contains(): Passing null to parameter #2 ($needle) of type string is deprecated';
const DEPRECATION_FILE = '/home/user/laravel/routes/web.php';
const DEPRECATION_LINE = 17;

/** The `<message> in <file> on line <line>` form the logger receives without a trace. */
function deprecationWarning(): string
{
    return sprintf('%s in %s on line %s', DEPRECATION_MESSAGE, DEPRECATION_FILE, DEPRECATION_LINE);
}

beforeEach(function () {
    $this->app = Mockery::mock(Application::setInstance(new Application));

    $this->app->instance('config', $this->config = new Config);
});

afterEach(function () {
    Application::setInstance(null);
    HandleExceptions::flushState($this);
});

/** Bind a LogManager double onto the application and let deprecations through. */
function expectDeprecationsAreLogged($app): Mockery\MockInterface
{
    $logger = Mockery::mock(LogManager::class);
    $app->instance(LogManager::class, $logger);
    $app->expects('runningUnitTests')->andReturn(false);
    $app->expects('hasBeenBootstrapped')->andReturn(true);

    return $logger;
}

test('a php deprecation is reported to the deprecations channel', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->with('deprecations')->andReturnSelf();
    $logger->expects('warning')->with(deprecationWarning());

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

test('a php deprecation can carry a stack trace', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $this->config->set('logging.deprecations', [
        'channel' => 'null',
        'trace' => true,
    ]);

    $logger->expects('channel')->with('deprecations')->andReturnSelf();
    $logger->expects('warning')->with(
        Mockery::on(fn (string $message) => (bool) preg_match(
            <<<REGEXP
            #ErrorException: str_contains\(\): Passing null to parameter \#2 \(\\\$needle\) of type string is deprecated in /home/user/laravel/routes/web\.php:17
            Stack trace:
            \#0 .*helpers.php\(.*\): Voyager\\\\System\\\\Bootstrap\\\\HandleExceptions.*
            \#1 .*HandleExceptions\.php\(.*\): with.*
            \#2 .*HandleExceptions\.php\(.*\): Voyager\\\\System\\\\Bootstrap\\\\HandleExceptions->handleDeprecation.*
            \#3 .*HandleExceptionsTest\.php\(.*\): Voyager\\\\System\\\\Bootstrap\\\\HandleExceptions->handleError.*
            [\s\S]*#i
            REGEXP,
            $message
        ))
    );

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

test('a null channel becomes the null driver', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $this->config->set('logging.deprecations', [
        'channel' => null,
        'trace' => false,
    ]);

    $logger->expects('channel')->with('deprecations')->andReturnSelf();
    $logger->expects('warning')->with(deprecationWarning());

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );

    expect($this->config->get('logging.channels.deprecations'))->toEqual([
        'driver' => 'monolog',
        'handler' => NullHandler::class,
    ]);
});

test('a user deprecation is reported to the deprecations channel', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->with('deprecations')->andReturnSelf();
    $logger->expects('warning')->with(deprecationWarning());

    handleExceptionsFor($this->app)->handleError(
        E_USER_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

test('a user deprecation can carry a stack trace', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $this->config->set('logging.deprecations', [
        'channel' => 'null',
        'trace' => true,
    ]);

    $logger->expects('channel')->with('deprecations')->andReturnSelf();
    $logger->expects('warning')->with(
        Mockery::on(fn (string $message) => (bool) preg_match(
            <<<REGEXP
            #ErrorException: str_contains\(\): Passing null to parameter \#2 \(\\\$needle\) of type string is deprecated in /home/user/laravel/routes/web\.php:17
            Stack trace:
            \#0 .*helpers.php\(.*\): Voyager\\\\System\\\\Bootstrap\\\\HandleExceptions.*
            \#1 .*HandleExceptions\.php\(.*\): with.*
            \#2 .*HandleExceptions\.php\(.*\): Voyager\\\\System\\\\Bootstrap\\\\HandleExceptions->handleDeprecation.*
            \#3 .*HandleExceptionsTest\.php\(.*\): Voyager\\\\System\\\\Bootstrap\\\\HandleExceptions->handleError.*
            [\s\S]*#i
            REGEXP,
            $message
        ))
    );

    handleExceptionsFor($this->app)->handleError(
        E_USER_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

test('an error is converted to an ErrorException and never logged', function () {
    $logger = Mockery::mock(LogManager::class);
    $this->app->instance(LogManager::class, $logger);

    $logger->shouldNotReceive('channel');
    $logger->shouldNotReceive('warning');

    handleExceptionsFor($this->app)->handleError(
        E_ERROR,
        'Something went wrong',
        '/home/user/laravel/src/Providers/AppServiceProvider.php',
        17
    );
})->throws(ErrorException::class, 'Something went wrong');

test('an existing driver is copied onto the deprecations channel', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->andReturnSelf();
    $logger->expects('warning');

    $this->config->set('logging.channels.stack', [
        'driver' => 'stack',
        'channels' => ['single'],
        'ignore_exceptions' => false,
    ]);
    $this->config->set('logging.deprecations', 'stack');

    handleExceptionsFor($this->app)->handleError(
        E_USER_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );

    expect($this->config->get('logging.channels.deprecations'))->toEqual([
        'driver' => 'stack',
        'channels' => ['single'],
        'ignore_exceptions' => false,
    ]);
});

test('a missing deprecations channel falls back to the null handler', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->andReturnSelf();
    $logger->expects('warning');

    handleExceptionsFor($this->app)->handleError(
        E_USER_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );

    expect($this->config->get('logging.channels.deprecations.handler'))->toEqual(NullHandler::class);
});

test('a missing null channel falls back to the null handler', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->andReturnSelf();
    $logger->expects('warning');

    handleExceptionsFor($this->app)->handleError(
        E_USER_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );

    expect($this->config->get('logging.channels.deprecations.handler'))->toEqual(NullHandler::class);
});

test('an existing null channel is not overridden', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->andReturnSelf();
    $logger->expects('warning');

    $this->config->set('logging.channels.null', [
        'driver' => 'monolog',
        'handler' => CustomNullHandler::class,
    ]);

    handleExceptionsFor($this->app)->handleError(
        E_USER_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );

    expect($this->config->get('logging.channels.deprecations.handler'))->toEqual(CustomNullHandler::class);
});

test('no deprecations channel is configured until a deprecation is sent', function () {
    expect($this->config->get('logging.deprecations'))->toEqual(null)
        ->and($this->config->get('logging.channels.deprecations'))->toEqual(null);
});

test('a deprecation is ignored when the logger cannot be resolved', function () {
    $this->app->expects('runningUnitTests')->andReturn(false);
    $this->app->expects('hasBeenBootstrapped')->andReturn(true);

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

test('a deprecation is ignored when logging itself fails', function () {
    $logger = expectDeprecationsAreLogged($this->app);

    $logger->expects('channel')->with('deprecations')->andThrow(new Error('Class "Monolog\Logger" not found'));

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

test('deprecation logging is skipped while running unit tests', function () {
    $resolved = false;
    $this->app->bind(LogManager::class, function () use (&$resolved) {
        $resolved = true;

        throw new RuntimeException;
    });
    $this->app->expects('runningUnitTests')->andReturn(true);
    $this->app->expects('hasBeenBootstrapped')->andReturn(true);

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );

    expect($resolved)->toBeFalse();
});

test('deprecation logging while testing can be forced through the environment', function () {
    $logger = Mockery::mock(LogManager::class);
    $logger->expects('channel')->andReturnSelf();
    $logger->expects('warning');
    $this->app->instance(LogManager::class, $logger);
    $this->app->expects('runningUnitTests')->andReturn(true);
    $this->app->expects('hasBeenBootstrapped')->andReturn(true);

    Env::getRepository()->set('LOG_DEPRECATIONS_WHILE_TESTING', true);

    handleExceptionsFor($this->app)->handleError(
        E_DEPRECATED,
        DEPRECATION_MESSAGE,
        DEPRECATION_FILE,
        DEPRECATION_LINE
    );
});

describe('the application reference', function () {
    test('forgetApp clears it', function () {
        $instance = handleExceptionsFor($this->app);

        $appResolver = fn () => (new ReflectionClass($instance))->getProperty('app')->getValue($instance);

        expect($appResolver())->not->toBeNull();

        HandleExceptions::forgetApp();

        expect($appResolver())->toBeNull();
    });

    test('bootstrapping a new application replaces the previous one', function () {
        $instance = handleExceptionsFor($this->app);

        $appResolver = fn () => (new ReflectionClass($instance))->getProperty('app')->getValue($instance);

        expect($appResolver())->toBe($this->app);

        $instance->bootstrap($newApp = tap(Mockery::mock(Application::class), function ($app) {
            $app->expects('environment')->andReturn(true);
        }));

        expect($appResolver())->not->toBe($this->app)
            ->and($appResolver())->toBe($newApp);
    });
});
