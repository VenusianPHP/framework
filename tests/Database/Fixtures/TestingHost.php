<?php

namespace Venusian\Tests\Database\Fixtures;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\Core\RenderedInstance;

/**
 * The host contract the database testing traits ask for, as a test case provides it: boot `$this->app`
 * before each test and run every used trait's setUp{Trait}() before it and tearDown{Trait}() after it.
 * One instance stands for one test; a Pest test drives several to play several tests in a row.
 */
abstract class TestingHost extends Assert
{
    /** How many times the fixture migration has run up. */
    public static int $migrated = 0;

    public RenderedInstance $app;

    /** @var list<string> */
    private array $tearDowns = [];

    public function __construct(
        private string $database,
        private string $connection = 'sqlite',
    ) {}

    /** Boot the app on the database and run the setUp hooks, as a test case does before a test. */
    public function begin(): static
    {
        $this->app = LogApp::boot();
        $this->app['config']->set("database.connections.{$this->connection}.database", $this->database);
        $this->app['config']->set('database.default', $this->connection);
        $this->app['migrator']->path(dirname(__DIR__).'/Testing/migrations');

        foreach (class_uses_recursive(static::class) as $trait) {
            $name = class_basename($trait);

            if (method_exists($this, $method = 'setUp'.$name)) {
                $this->{$method}();
            }

            if (method_exists($this, $method = 'tearDown'.$name)) {
                $this->tearDowns[] = $method;
            }
        }

        return $this;
    }

    /** Run the tearDown hooks and drop the app, as a test case does after a test. */
    public function end(TestCase $test): void
    {
        try {
            foreach (array_reverse($this->tearDowns) as $method) {
                $this->{$method}();
            }
        } finally {
            $this->tearDowns = [];
            LogApp::tearDown($this->app, $test);
        }
    }

    /** The traits' assertions are protected, as test cases call them on themselves; the Pest test calls them here. */
    public function __call(string $method, array $arguments): mixed
    {
        if (! method_exists($this, $method)) {
            throw new \BadMethodCallException(static::class."::{$method}() does not exist.");
        }

        return $this->{$method}(...$arguments);
    }

    public function db()
    {
        return $this->app['db']->connection($this->connection);
    }
}
