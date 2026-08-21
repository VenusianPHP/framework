<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/**
 * @method static mixed dispatch(mixed $command)
 * @method static mixed dispatchSync(mixed $command, mixed $handler = null)
 * @method static mixed dispatchNow(mixed $command, mixed $handler = null)
 * @method static bool hasCommandHandler(mixed $command)
 * @method static mixed getCommandHandler(mixed $command)
 * @method static mixed dispatchToQueue(mixed $command)
 * @method static void dispatchAfterResponse(mixed $command, mixed $handler = null)
 * @method static \Voyager\Bus\Dispatcher pipeThrough(array $pipes)
 * @method static \Voyager\Bus\Dispatcher map(array $map)
 * @method static mixed findBatch(string $batchId)
 * @method static mixed batch(array|\Voyager\NutsAndBolts\Collection $jobs)
 * @method static mixed chain(array|\Voyager\NutsAndBolts\Collection|null $jobs = null)
 *
 * @see \Voyager\Bus\Dispatcher
 */
class Bus extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'bus';
    }
}
