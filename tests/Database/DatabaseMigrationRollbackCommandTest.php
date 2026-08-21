<?php

use Voyager\Database\Console\Migrations\RollbackCommand;
use Voyager\Database\Migrations\Migrator;
use Voyager\System\Application;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function migrationRollbackRunCommand($command, $input = [])
{
    return $command->run(new ArrayInput($input), new NullOutput);
}

test('rollback command calls migrator with proper arguments', function () {
    $command = new RollbackCommand($migrator = m::mock(Migrator::class));
    $app = new ApplicationDatabaseRollbackStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('rollback')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => 0, 'batch' => 0]);

    migrationRollbackRunCommand($command);
});

test('rollback command calls migrator with step option', function () {
    $command = new RollbackCommand($migrator = m::mock(Migrator::class));
    $app = new ApplicationDatabaseRollbackStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('rollback')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => 2, 'batch' => 0]);

    migrationRollbackRunCommand($command, ['--step' => 2]);
});

test('rollback command can be pretended', function () {
    $command = new RollbackCommand($migrator = m::mock(Migrator::class));
    $app = new ApplicationDatabaseRollbackStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('rollback')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], true);

    migrationRollbackRunCommand($command, ['--pretend' => true, '--database' => 'foo']);
});

test('rollback command can be pretended with step option', function () {
    $command = new RollbackCommand($migrator = m::mock(Migrator::class));
    $app = new ApplicationDatabaseRollbackStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('rollback')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => true, 'step' => 2, 'batch' => 0]);

    migrationRollbackRunCommand($command, ['--pretend' => true, '--database' => 'foo', '--step' => 2]);
});

class ApplicationDatabaseRollbackStub extends Application
{
    public function __construct(array $data = [])
    {
        foreach ($data as $abstract => $instance) {
            $this->instance($abstract, $instance);
        }
    }

    public function environment(array|string ...$environments): string|bool
    {
        return 'development';
    }
}
