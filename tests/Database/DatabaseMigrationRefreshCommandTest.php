<?php

namespace Venusian\Tests\Database;

use Voyager\Contracts\Signals\SignalDispatcher as Dispatcher;
use Voyager\Database\Console\Migrations\MigrateCommand;
use Voyager\Database\Console\Migrations\RefreshCommand;
use Voyager\Database\Console\Migrations\ResetCommand;
use Voyager\Database\Console\Migrations\RollbackCommand;
use Voyager\Database\Events\DatabaseRefreshed;
use Voyager\Core\RenderedInstance;
use Mockery as m;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function dbRefreshRunCommand($command, $input = [])
{
    return $command->run(new ArrayInput($input), new NullOutput);
}

afterEach(function () {
    RefreshCommand::prohibit(false);

    parent::tearDown();
});

test('refresh command calls commands with proper arguments', function () {
    $command = new RefreshCommand;

    $app = new ApplicationDatabaseRefreshStub(['path.database' => __DIR__]);
    $app->registerInstance(Dispatcher::class, $dispatcher = $events = m::mock());
    $console = m::mock(ConsoleApplication::class)->makePartial();
    $console->__construct();
    $command->setVenusian($app);
    $command->setApplication($console);

    $resetCommand = m::mock(ResetCommand::class);
    $migrateCommand = m::mock(MigrateCommand::class);

    $console->shouldReceive('find')->with('migrate:reset')->andReturn($resetCommand);
    $console->shouldReceive('find')->with('migrate')->andReturn($migrateCommand);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(DatabaseRefreshed::class));

    $quote = DIRECTORY_SEPARATOR === '\\' ? '"' : "'";
    $resetCommand->shouldReceive('run')->with(new InputMatcher("--force=1 {$quote}migrate:reset{$quote}"), m::any());
    $migrateCommand->shouldReceive('run')->with(new InputMatcher('--force=1 migrate'), m::any());

    dbRefreshRunCommand($command);
});

test('refresh command calls commands with step', function () {
    $command = new RefreshCommand;

    $app = new ApplicationDatabaseRefreshStub(['path.database' => __DIR__]);
    $app->registerInstance(Dispatcher::class, $dispatcher = $events = m::mock());
    $console = m::mock(ConsoleApplication::class)->makePartial();
    $console->__construct();
    $command->setVenusian($app);
    $command->setApplication($console);

    $rollbackCommand = m::mock(RollbackCommand::class);
    $migrateCommand = m::mock(MigrateCommand::class);

    $console->shouldReceive('find')->with('migrate:rollback')->andReturn($rollbackCommand);
    $console->shouldReceive('find')->with('migrate')->andReturn($migrateCommand);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(DatabaseRefreshed::class));

    $quote = DIRECTORY_SEPARATOR === '\\' ? '"' : "'";
    $rollbackCommand->shouldReceive('run')->with(new InputMatcher("--step=2 --force=1 {$quote}migrate:rollback{$quote}"), m::any());
    $migrateCommand->shouldReceive('run')->with(new InputMatcher('--force=1 migrate'), m::any());

    dbRefreshRunCommand($command, ['--step' => 2]);
});

test('refresh command exits when prohibited', function () {
    $command = new RefreshCommand;

    $app = new ApplicationDatabaseRefreshStub(['path.database' => __DIR__]);
    $app->registerInstance(Dispatcher::class, $dispatcher = $events = m::mock());
    $console = m::mock(ConsoleApplication::class)->makePartial();
    $console->__construct();
    $command->setVenusian($app);
    $command->setApplication($console);

    RefreshCommand::prohibit();

    $code = dbRefreshRunCommand($command);

    $this->assertSame(1, $code);

    $console->shouldNotHaveBeenCalled();
    $dispatcher->shouldNotReceive('dispatch');
});

class InputMatcher extends m\Matcher\MatcherAbstract
{
    /**
     * @param  \Symfony\Component\Console\Input\ArrayInput  $actual
     * @return bool
     */
    public function match(&$actual)
    {
        return (string) $actual == $this->_expected;
    }

    public function __toString()
    {
        return '';
    }
}

class ApplicationDatabaseRefreshStub extends RenderedInstance
{
    public function __construct(array $data = [])
    {
        foreach ($data as $abstract => $instance) {
            $this->registerInstance($abstract, $instance);
        }
    }

    public function environment(array|string ...$environments): string|bool
    {
        return 'development';
    }
}
