<?php

use Voyager\Console\CommandMutex;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Database\Console\Migrations\MigrateCommand;
use Voyager\Database\Events\SchemaLoaded;
use Voyager\Database\Migrations\Migrator;
use Voyager\System\Application;
use Mockery as m;
use stdClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function migrateCommandRun($command, $input = [])
{
    return $command->run(new ArrayInput($input), new NullOutput);
}

test('basic migrations call migrator with proper arguments', function () {
    $command = new MigrateCommand($migrator = m::mock(Migrator::class), $dispatcher = m::mock(Dispatcher::class));
    $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('hasRunAnyMigrations')->andReturn(true);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('run')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
    $migrator->shouldReceive('getNotes')->andReturn([]);
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

    migrateCommandRun($command);
});

test('migrations can be run with stored schema', function () {
    $command = new MigrateCommand($migrator = m::mock(Migrator::class), $dispatcher = m::mock(Dispatcher::class));
    $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('hasRunAnyMigrations')->andReturn(false);
    $migrator->shouldReceive('resolveConnection')->andReturn($connection = m::mock(stdClass::class));
    $connection->shouldReceive('getName')->andReturn('mysql');
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('deleteRepository')->once();
    $connection->shouldReceive('getSchemaState')->andReturn($schemaState = m::mock(stdClass::class));
    $schemaState->shouldReceive('handleOutputUsing')->andReturnSelf();
    $schemaState->shouldReceive('load')->once()->with(__DIR__.'/stubs/schema.sql');
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(SchemaLoaded::class));
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('run')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
    $migrator->shouldReceive('getNotes')->andReturn([]);
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

    migrateCommandRun($command, ['--schema-path' => __DIR__.'/stubs/schema.sql']);
});

test('migration repository created when necessary', function () {
    $params = [$migrator = m::mock(Migrator::class), $dispatcher = m::mock(Dispatcher::class)];
    $command = $this->getMockBuilder(MigrateCommand::class)->onlyMethods(['callSilent'])->setConstructorArgs($params)->getMock();
    $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('hasRunAnyMigrations')->andReturn(true);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('run')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(false);
    $command->expects($this->once())->method('callSilent')->with($this->equalTo('migrate:install'), $this->equalTo([]));

    migrateCommandRun($command);
});

test('the command may be pretended', function () {
    $command = new MigrateCommand($migrator = m::mock(Migrator::class), $dispatcher = m::mock(Dispatcher::class));
    $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('hasRunAnyMigrations')->andReturn(true);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('run')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => true, 'step' => false]);
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

    migrateCommandRun($command, ['--pretend' => true]);
});

test('the database may be set', function () {
    $command = new MigrateCommand($migrator = m::mock(Migrator::class), $dispatcher = m::mock(Dispatcher::class));
    $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('hasRunAnyMigrations')->andReturn(true);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('run')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

    migrateCommandRun($command, ['--database' => 'foo']);
});

test('step may be set', function () {
    $command = new MigrateCommand($migrator = m::mock(Migrator::class), $dispatcher = m::mock(Dispatcher::class));
    $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('hasRunAnyMigrations')->andReturn(true);
    $migrator->shouldReceive('usingConnection')->once()->andReturnUsing(function ($name, $callback) {
        return $callback();
    });
    $migrator->shouldReceive('setOutput')->once()->andReturn($migrator);
    $migrator->shouldReceive('run')->once()->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => true]);
    $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

    migrateCommandRun($command, ['--step' => true]);
});

class ApplicationDatabaseMigrationStub extends Application
{
    public function __construct(array $data = [])
    {
        $mutex = m::mock(CommandMutex::class);
        $mutex->shouldReceive('create')->andReturn(true);
        $mutex->shouldReceive('release')->andReturn(true);
        $this->instance(CommandMutex::class, $mutex);

        foreach ($data as $abstract => $instance) {
            $this->instance($abstract, $instance);
        }
    }

    public function environment(array|string ...$environments): string|bool
    {
        return 'development';
    }
}
