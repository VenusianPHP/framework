<?php

use Voyager\Database\Console\Migrations\InstallCommand;
use Voyager\Database\Migrations\MigrationRepositoryInterface;
use Voyager\System\Application;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function migrationInstallRunCommand($command, $options = [])
{
    return $command->run(new ArrayInput($options), new NullOutput);
}

test('fire calls repository to install', function () {
    $command = new InstallCommand($repo = m::mock(MigrationRepositoryInterface::class));
    $command->setVenusian(new Application);
    $repo->shouldReceive('setSource')->once()->with('foo');
    $repo->shouldReceive('createRepository')->once();
    $repo->shouldReceive('repositoryExists')->once()->andReturn(false);

    migrationInstallRunCommand($command, ['--database' => 'foo']);
});

test('fire calls repository to install exists', function () {
    $command = new InstallCommand($repo = m::mock(MigrationRepositoryInterface::class));
    $command->setVenusian(new Application);
    $repo->shouldReceive('setSource')->once()->with('foo');
    $repo->shouldReceive('repositoryExists')->once()->andReturn(true);

    migrationInstallRunCommand($command, ['--database' => 'foo']);
});
