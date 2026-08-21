<?php

use Voyager\Bus\BatchRepository;
use Voyager\Bus\DatabaseBatchRepository;
use Voyager\System\Application;
use Voyager\Queue\Console\PruneBatchesCommand;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

test('allow pruning all unfinished batches', function () {
    $container = new Application;
    $container->instance(BatchRepository::class, $repo = m::spy(DatabaseBatchRepository::class));

    $command = new PruneBatchesCommand;
    $command->setVenusian($container);

    $command->run(new ArrayInput(['--unfinished' => 0]), new NullOutput());

    $repo->shouldHaveReceived('pruneUnfinished')->once();
});

test('allow pruning all cancelled batches', function () {
    $container = new Application;
    $container->instance(BatchRepository::class, $repo = m::spy(DatabaseBatchRepository::class));

    $command = new PruneBatchesCommand;
    $command->setVenusian($container);

    $command->run(new ArrayInput(['--cancelled' => 0]), new NullOutput());

    $repo->shouldHaveReceived('pruneCancelled')->once();
});
