<?php

use Orchestra\Testbench\Concerns\InteractsWithMockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Console\Fixtures\IsolatableInvokableCommand;
use Voyager\Console\Command;
use Voyager\Console\CommandMutex;
use Voyager\System\Application;

uses(InteractsWithMockery::class);

/** Run the command, with or without the `--isolated` switch. */
function runIsolatableCommand(Command $command, bool $withIsolated = true): void
{
    $command->run(new ArrayInput(['--isolated' => $withIsolated]), new NullOutput);
}

beforeEach(function () {
    $this->command = new IsolatableInvokableCommand;

    $this->commandMutex = Mockery::mock(CommandMutex::class);

    $app = new Application;
    $app->instance(CommandMutex::class, $this->commandMutex);
    $this->command->setVenusian($app);
});

afterEach(function () {
    $this->tearDownTheTestEnvironmentUsingMockery();
});

test('an isolated command runs when the mutex is free', function () {
    $this->commandMutex->shouldReceive('create')
        ->andReturn(true)
        ->once();
    $this->commandMutex->shouldReceive('forget')
        ->andReturn(true)
        ->once();

    runIsolatableCommand($this->command);

    expect($this->command->ran)->toEqual(1);
});

test('an isolated command does not run while the mutex is held', function () {
    $this->commandMutex->shouldReceive('create')
        ->andReturn(false)
        ->once();

    runIsolatableCommand($this->command);

    expect($this->command->ran)->toEqual(0);
});

test('an isolated command runs again once the mutex is released', function () {
    $this->commandMutex->shouldReceive('create')
        ->andReturn(true)
        ->twice();
    $this->commandMutex->shouldReceive('forget')
        ->andReturn(true)
        ->twice();

    runIsolatableCommand($this->command);
    runIsolatableCommand($this->command);

    expect($this->command->ran)->toEqual(2);
});

test('a command run without the isolated switch never touches the mutex', function () {
    $this->commandMutex->shouldNotHaveBeenCalled();

    runIsolatableCommand($this->command, false);

    expect($this->command->ran)->toEqual(1);
});
