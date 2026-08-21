<?php

use Voyager\Database\Console\Migrations\ResetCommand;
use Voyager\Database\Migrations\Migrator;
use Voyager\System\Application;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function databaseMigrationResetCommandRunCommand($command, $input = [])
{
    return $command->run(new ArrayInput($input), new NullOutput);
}

afterEach(function () {
    ResetCommand::prohibit(false);
});

test('reset command calls migrator with proper arguments', function () {
    $command = new ResetCommand($migrator = m::mock(Migrator::class));
    $app = new ApplicationDatabaseResetStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('usingConnection')->once()->with(null, m::type(Closure::class))->andReturnUsing(function ($connection, $callback) {
        $callback();
    });
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('reset')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], false);

    databaseMigrationResetCommandRunCommand($command);
});

test('reset command can be pretended', function () {
    $command = new ResetCommand($migrator = m::mock(Migrator::class));
    $app = new ApplicationDatabaseResetStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('usingConnection')->once()->with('foo', m::type(Closure::class))->andReturnUsing(function ($connection, $callback) {
        $callback();
    });
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('reset')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], true);

    databaseMigrationResetCommandRunCommand($command, ['--pretend' => true, '--database' => 'foo']);
});

test('refresh command exits when prohibited', function () {
    $command = new ResetCommand($migrator = m::mock(Migrator::class));

    $app = new ApplicationDatabaseResetStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);

    ResetCommand::prohibit();

    $code = databaseMigrationResetCommandRunCommand($command);

    $this->assertSame(1, $code);

    $migrator->shouldNotHaveBeenCalled();
});

class ApplicationDatabaseResetStub extends Application
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
