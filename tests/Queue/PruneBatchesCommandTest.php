<?php

namespace Tests\Queue;

use Voyager\Bus\BatchRepository;
use Voyager\Bus\DatabaseBatchRepository;
use Voyager\System\Application;
use Voyager\Queue\Console\PruneBatchesCommand;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class PruneBatchesCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testAllowPruningAllUnfinishedBatches()
    {
        $container = new Application;
        $container->instance(BatchRepository::class, $repo = m::spy(DatabaseBatchRepository::class));

        $command = new PruneBatchesCommand;
        $command->setVenusian($container);

        $command->run(new ArrayInput(['--unfinished' => 0]), new NullOutput());

        $repo->shouldHaveReceived('pruneUnfinished')->once();
    }

    public function testAllowPruningAllCancelledBatches()
    {
        $container = new Application;
        $container->instance(BatchRepository::class, $repo = m::spy(DatabaseBatchRepository::class));

        $command = new PruneBatchesCommand;
        $command->setVenusian($container);

        $command->run(new ArrayInput(['--cancelled' => 0]), new NullOutput());

        $repo->shouldHaveReceived('pruneCancelled')->once();
    }
}
