<?php

namespace Voyager\System\Testing\Concerns;

use Carbon\CarbonImmutable;
use Voyager\Console\Application as Computer;
use Voyager\Database\Instrument\Model;
use Voyager\System\Bootstrap\HandleExceptions;
use Voyager\System\Bootstrap\RegisterProviders;
use Voyager\System\Console\AboutCommand;
use Voyager\System\Testing\DatabaseMigrations;
use Voyager\System\Testing\DatabaseTransactions;
use Voyager\System\Testing\DatabaseTruncation;
use Voyager\System\Testing\RefreshDatabase;
use Voyager\System\Testing\WithFaker;
use Voyager\Http\Client\Response;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\EncodedHtmlString;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\MagicAliases\ParallelTesting;
use Voyager\NutsAndBolts\Once;
use Voyager\NutsAndBolts\Sleep;
use Voyager\NutsAndBolts\DataObjects\Str;
use Mockery;
use Mockery\Exception\InvalidCountException;
use PHPUnit\Metadata\Annotation\Parser\Registry as PHPUnitRegistry;
use Throwable;

trait InteractsWithTestCaseLifecycle
{
    /**
     * The Voyager application instance.
     *
     * @var \Voyager\System\Application
     */
    protected $app;

    /**
     * The callbacks that should be run after the application is created.
     *
     * @var array
     */
    protected $afterApplicationCreatedCallbacks = [];

    /**
     * The callbacks that should be run before the application is destroyed.
     *
     * @var array
     */
    protected $beforeApplicationDestroyedCallbacks = [];

    /**
     * The exception thrown while running an application destruction callback.
     *
     * @var \Throwable
     */
    protected $callbackException;

    /**
     * Indicates if we have made it through the base setUp function.
     *
     * @var bool
     */
    protected $setUpHasRun = false;

    /**
     * Setup the test environment.
     *
     * @internal
     *
     * @return void
     */
    protected function setUpTheTestEnvironment(): void
    {
        MagicAlias::clearResolvedInstances();

        if (! $this->app) {
            $this->refreshApplication();

            ParallelTesting::callSetUpTestCaseCallbacks($this);
        }

        $this->setUpTraits();

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }

        if (class_exists(Model::class)) {
            Model::setEventDispatcher($this->app['events']);
        }

        $this->setUpHasRun = true;
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @internal
     *
     * @return void
     */
    protected function tearDownTheTestEnvironment(): void
    {
        if ($this->app) {
            $this->callBeforeApplicationDestroyedCallbacks();

            ParallelTesting::callTearDownTestCaseCallbacks($this);

            $this->app->flush();

            $this->app = null;
        }

        $this->setUpHasRun = false;

        if (property_exists($this, 'serverVariables')) {
            $this->serverVariables = [];
        }

        if (property_exists($this, 'defaultHeaders')) {
            $this->defaultHeaders = [];
        }

        if (class_exists('Mockery')) {
            if ($container = Mockery::getContainer()) {
                $this->addToAssertionCount($container->mockery_getExpectationCount());
            }

            try {
                Mockery::close();
            } catch (InvalidCountException $e) {
                if (! Str::contains($e->getMethodName(), ['doWrite', 'askQuestion'])) {
                    throw $e;
                }
            }
        }

        if (class_exists(Carbon::class)) {
            Carbon::setTestNow();
        }

        if (class_exists(CarbonImmutable::class)) {
            CarbonImmutable::setTestNow();
        }

        $this->afterApplicationCreatedCallbacks = [];
        $this->beforeApplicationDestroyedCallbacks = [];

        if (property_exists($this, 'originalExceptionHandler')) {
            $this->originalExceptionHandler = null;
        }

        if (property_exists($this, 'originalDeprecationHandler')) {
            $this->originalDeprecationHandler = null;
        }

        // Entries are commented where the component has not been ported yet, in
        // upstream's alphabetical order, so landing one is a matter of
        // uncommenting. The web middleware, cookie, view and mail entries are
        // gone for good.
        AboutCommand::flushState();
        Computer::forgetBootstrappers();
        EncodedHtmlString::flushState();
        HandleExceptions::flushState($this);
        // Migrator::withoutMigrations([]);          // wave 6
        // Factory::flushState();                    // wave 6
        Once::flush();
        // Queue::createPayloadUsing(null);          // wave 5
        RegisterProviders::flushState();
        Response::flushState();
        Sleep::fake(false);
        // Validator::flushState();                  // wave 4
        // WorkCommand::flushState();                // wave 5

        if ($this->callbackException) {
            throw $this->callbackException;
        }
    }

    /**
     * Boot the testing helper traits.
     *
     * @return array
     */
    protected function setUpTraits()
    {
        $uses = $this->traitsUsedByTest ?? array_flip(class_uses_recursive(static::class));

        if (isset($uses[RefreshDatabase::class])) {
            $this->refreshDatabase();
        }

        if (isset($uses[DatabaseMigrations::class])) {
            $this->runDatabaseMigrations();
        }

        if (isset($uses[DatabaseTruncation::class])) {
            $this->truncateDatabaseTables();
        }

        if (isset($uses[DatabaseTransactions::class])) {
            $this->beginDatabaseTransaction();
        }

        if (isset($uses[WithFaker::class])) {
            $this->setUpFaker();
        }

        foreach ($uses as $trait) {
            if (method_exists($this, $method = 'setUp'.class_basename($trait))) {
                $this->{$method}();
            }

            if (method_exists($this, $method = 'tearDown'.class_basename($trait))) {
                $this->beforeApplicationDestroyed(fn () => $this->{$method}());
            }
        }

        return $uses;
    }

    /**
     * Clean up the testing environment before the next test case.
     *
     * @internal
     *
     * @return void
     */
    public static function tearDownAfterClassUsingTestCase()
    {
        if (class_exists(PHPUnitRegistry::class)) {
            (function () {
                $this->classDocBlocks = [];
                $this->methodDocBlocks = [];
            })->call(PHPUnitRegistry::getInstance());
        }
    }

    /**
     * Register a callback to be run after the application is created.
     *
     * @param  callable  $callback
     * @return void
     */
    public function afterApplicationCreated(callable $callback)
    {
        $this->afterApplicationCreatedCallbacks[] = $callback;

        if ($this->setUpHasRun) {
            $callback();
        }
    }

    /**
     * Register a callback to be run before the application is destroyed.
     *
     * @param  callable  $callback
     * @return void
     */
    protected function beforeApplicationDestroyed(callable $callback)
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

    /**
     * Execute the application's pre-destruction callbacks.
     *
     * @return void
     */
    protected function callBeforeApplicationDestroyedCallbacks()
    {
        foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                if (! $this->callbackException) {
                    $this->callbackException = $e;
                }
            }
        }
    }
}
