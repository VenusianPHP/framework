<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;
use Voyager\Testing\ParallelTesting as ParallelTestingManager;

/**
 * @method static void setUpProcess(callable $callback)
 * @method static void setUpTestCase(callable $callback)
 * @method static void setUpTestDatabase(callable $callback)
 * @method static void tearDownProcess(callable $callback)
 * @method static void tearDownTestCase(callable $callback)
 * @method static void callSetUpProcessCallbacks()
 * @method static void callSetUpTestCaseCallbacks(\PHPUnit\Framework\TestCase $testCase)
 * @method static void callSetUpTestDatabaseCallbacks(string $database)
 * @method static void callTearDownProcessCallbacks()
 * @method static void callTearDownTestCaseCallbacks(\PHPUnit\Framework\TestCase $testCase)
 * @method static int|false token()
 * @method static void resolveOptionsUsing(?\Closure $resolver)
 * @method static void resolveTokenUsing(?\Closure $resolver)
 *
 * @see \Voyager\Testing\ParallelTesting
 */
class ParallelTesting extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return ParallelTestingManager::class;
    }
}
