<?php

use Voyager\Database\Console\Migrations\MigrateMakeCommand;
use Voyager\Database\Migrations\MigrationCreator;
use Voyager\System\Application;
use Voyager\NutsAndBolts\Composer;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function migrationMakeCommandRun($command, $input = [])
{
    return $command->run(new ArrayInput($input), new NullOutput);
}

test('basic create dumps autoload', function () {
    $command = new MigrateMakeCommand(
        $creator = m::mock(MigrationCreator::class),
        $composer = m::mock(Composer::class)
    );
    $app = new Application;
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $creator->shouldReceive('create')->once()
        ->with('create_foo', __DIR__.DIRECTORY_SEPARATOR.'migrations', 'foo', true)
        ->andReturn(__DIR__.'/migrations/2021_04_23_110457_create_foo.php');

    migrationMakeCommandRun($command, ['name' => 'create_foo']);
});

test('basic create gives creator proper arguments', function () {
    $command = new MigrateMakeCommand(
        $creator = m::mock(MigrationCreator::class),
        m::mock(Composer::class)->shouldIgnoreMissing()
    );
    $app = new Application;
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $creator->shouldReceive('create')->once()
        ->with('create_foo', __DIR__.DIRECTORY_SEPARATOR.'migrations', 'foo', true)
        ->andReturn(__DIR__.'/migrations/2021_04_23_110457_create_foo.php');

    migrationMakeCommandRun($command, ['name' => 'create_foo']);
});

test('basic create gives creator proper arguments when name is studly case', function () {
    $command = new MigrateMakeCommand(
        $creator = m::mock(MigrationCreator::class),
        m::mock(Composer::class)->shouldIgnoreMissing()
    );
    $app = new Application;
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $creator->shouldReceive('create')->once()
        ->with('create_foo', __DIR__.DIRECTORY_SEPARATOR.'migrations', 'foo', true)
        ->andReturn(__DIR__.'/migrations/2021_04_23_110457_create_foo.php');

    migrationMakeCommandRun($command, ['name' => 'CreateFoo']);
});

test('basic create gives creator proper arguments when table is set', function () {
    $command = new MigrateMakeCommand(
        $creator = m::mock(MigrationCreator::class),
        m::mock(Composer::class)->shouldIgnoreMissing()
    );
    $app = new Application;
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $creator->shouldReceive('create')->once()
        ->with('create_foo', __DIR__.DIRECTORY_SEPARATOR.'migrations', 'users', true)
        ->andReturn(__DIR__.'/migrations/2021_04_23_110457_create_foo.php');

    migrationMakeCommandRun($command, ['name' => 'create_foo', '--create' => 'users']);
});

test('basic create gives creator proper arguments when create table pattern is found', function () {
    $command = new MigrateMakeCommand(
        $creator = m::mock(MigrationCreator::class),
        m::mock(Composer::class)->shouldIgnoreMissing()
    );
    $app = new Application;
    $app->useDatabasePath(__DIR__);
    $command->setVenusian($app);
    $creator->shouldReceive('create')->once()
        ->with('create_users_table', __DIR__.DIRECTORY_SEPARATOR.'migrations', 'users', true)
        ->andReturn(__DIR__.'/migrations/2021_04_23_110457_create_users_table.php');

    migrationMakeCommandRun($command, ['name' => 'create_users_table']);
});

test('can specify path to create migrations in', function () {
    $command = new MigrateMakeCommand(
        $creator = m::mock(MigrationCreator::class),
        m::mock(Composer::class)->shouldIgnoreMissing()
    );
    $app = new Application;
    $command->setVenusian($app);
    $app->setBasePath('/home/laravel');
    $creator->shouldReceive('create')->once()
        ->with('create_foo', '/home/laravel/vendor/laravel-package/migrations', 'users', true)
        ->andReturn('/home/laravel/vendor/laravel-package/migrations/2021_04_23_110457_create_foo.php');
    migrationMakeCommandRun($command, ['name' => 'create_foo', '--path' => 'vendor/laravel-package/migrations', '--create' => 'users']);
});
