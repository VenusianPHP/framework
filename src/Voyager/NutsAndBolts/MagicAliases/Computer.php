<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\Contracts\Console\Kernel as ConsoleKernelContract;
use Voyager\MagicAliases\MagicAlias;

/**
 * Venusian's equivalent of Laravel's `Artisan` facade.
 *
 * @method static int handle(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface|null $output = null)
 * @method static void terminate(\Symfony\Component\Console\Input\InputInterface $input, int $status)
 * @method static \Voyager\System\Console\ClosureCommand command(string $signature, \Closure $callback)
 * @method static void registerCommand(\Symfony\Component\Console\Command\Command $command)
 * @method static int call(string $command, array $parameters = [], \Symfony\Component\Console\Output\OutputInterface|null $outputBuffer = null)
 * @method static \Voyager\System\Bus\PendingDispatch queue(string $command, array $parameters = [])
 * @method static array all()
 * @method static string output()
 * @method static void bootstrap()
 *
 * @see \Voyager\Contracts\Console\Kernel
 */
class Computer extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return ConsoleKernelContract::class;
    }
}
