<?php

namespace Voyager\System\Testing;

use Voyager\Contracts\Console\Kernel;
use Voyager\System\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // MakesHttpRequests, InteractsWithSession, InteractsWithViews and
    // InteractsWithAuthentication are gone with the serving half. Database
    // interaction lands in wave 6.
    use Concerns\InteractsWithContainer,
        Concerns\InteractsWithConsole,
        Concerns\InteractsWithDeprecationHandling,
        Concerns\InteractsWithExceptionHandling,
        Concerns\InteractsWithTime,
        Concerns\InteractsWithTestCaseLifecycle;

    /**
     * The list of trait that this test uses, fetched recursively.
     *
     * @var array<class-string, int>
     */
    protected array $traitsUsedByTest;

    /**
     * Creates the application.
     *
     * @return \Voyager\System\Application
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        if (isset(CachedState::$cachedConfig) &&
            isset($this->traitsUsedByTest[WithCachedConfig::class])) {
            $this->markConfigCached($app);
        }

        if (isset(CachedState::$cachedRoutes) &&
            isset($this->traitsUsedByTest[WithCachedRoutes::class])) {
            $app->booting(fn () => $this->markRoutesCached($app));
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->setUpTheTestEnvironment();
    }

    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refreshApplication()
    {
        $this->app = $this->createApplication();
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     *
     * @throws \Mockery\Exception\InvalidCountException
     */
    protected function tearDown(): void
    {
        $this->tearDownTheTestEnvironment();
    }

    /**
     * Clean up the testing environment before the next test case.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::tearDownAfterClassUsingTestCase();
    }
}
