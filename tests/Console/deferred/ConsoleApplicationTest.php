<?php

use Composer\Autoload\ClassLoader;
use Orchestra\Testbench\Concerns\InteractsWithMockery;
use Orchestra\Testbench\Foundation\Application as Testbench;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\Console\Fixtures\CommandWithAliasViaAttribute;
use Tests\Console\Fixtures\CommandWithAliasViaProperty;
use Tests\Console\Fixtures\CommandWithNoAliasViaAttribute;
use Tests\Console\Fixtures\CommandWithNoAliasViaProperty;
use Tests\Console\Fixtures\FakeCommandWithArrayInputPrompting;
use Tests\Console\Fixtures\FakeCommandWithInputPrompting;
use Tests\Console\Fixtures\TestKernel;
use Voyager\Console\Application;
use Voyager\Console\Command;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\System\Application as ApplicationContract;
use Voyager\Events\Dispatcher as EventsDispatcher;
use Voyager\Filesystem\Filesystem;
use Voyager\System\Application as FoundationApplication;

use function Orchestra\Testbench\default_skeleton_path;
use function Voyager\Filesystem\join_paths;

uses(InteractsWithMockery::class);

/**
 * A console Application with the named protected methods mocked out.
 */
function mockConsole(array $methods): Mockery\MockInterface
{
    $app = Mockery::mock(ApplicationContract::class, ['version' => '6.0']);
    $events = Mockery::mock(Dispatcher::class, ['dispatch' => null]);

    $computer = Mockery::mock(Application::class, [$app, $events, 'test-version'])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    foreach ($methods as $method) {
        $computer->shouldReceive($method)->byDefault();
    }

    return $computer;
}

/** A console Application on top of a real System application rooted at $directory. */
function consoleApplicationFor(FoundationApplication $app): Application
{
    return new Application(
        $app,
        Mockery::mock(Dispatcher::class, ['dispatch' => null]),
        'testing'
    );
}

afterEach(function () {
    $this->tearDownTheTestEnvironmentUsingMockery();
});

describe('add', function () {
    test('adding a Voyager command hands it the application', function () {
        $computer = mockConsole(['addToParent']);
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('setVenusian')->once()->with(Mockery::type(ApplicationContract::class));
        $computer->shouldReceive('addToParent')->once()->with($command)->andReturn($command);

        expect($computer->add($command))->toBe($command);
    });

    test('adding a plain Symfony command does not hand it the application', function () {
        $computer = mockConsole(['addToParent']);
        $command = Mockery::mock(SymfonyCommand::class);
        $command->shouldReceive('setVenusian')->never();
        $computer->shouldReceive('addToParent')->once()->with($command)->andReturn($command);

        expect($computer->add($command))->toBe($command);
    });

    test('resolve adds a command resolved from the application', function () {
        $computer = mockConsole(['addToParent']);
        $command = Mockery::mock(SymfonyCommand::class);
        $computer->getVenusian()->shouldReceive('make')->once()->with('foo')->andReturn(Mockery::mock(SymfonyCommand::class));
        $computer->shouldReceive('addToParent')->once()->with($command)->andReturn($command);

        expect($computer->resolve('foo'))->toBe($command);
    });
});

describe('command aliases', function () {
    test('an aliased command is reachable under both names', function ($class) {
        $container = new FoundationApplication;
        $computer = new Application($container, new EventsDispatcher($container), $container->version());
        $computer->resolve($class);
        $computer->setContainerCommandLoader();

        expect($computer->get('command-name'))->toBeInstanceOf($class)
            ->and($computer->get('command-alias'))->toBeInstanceOf($class)
            ->and($computer->all())->toHaveKey('command-name')
            ->and($computer->all())->toHaveKey('command-alias');
    })->with([
        'via attribute' => [CommandWithAliasViaAttribute::class],
        'via property' => [CommandWithAliasViaProperty::class],
    ]);

    test('an unaliased command is only reachable under its name', function ($class) {
        $container = new FoundationApplication;
        $computer = new Application($container, new EventsDispatcher($container), $container->version());
        $computer->resolve($class);
        $computer->setContainerCommandLoader();

        expect($computer->get('command-name'))->toBeInstanceOf($class);

        try {
            $computer->get('command-alias');
            $this->fail();
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(CommandNotFoundException::class);
        }

        expect($computer->all())->toHaveKey('command-name')
            ->and($computer->all())->not->toHaveKey('command-alias');
    })->with([
        'via attribute' => [CommandWithNoAliasViaAttribute::class],
        'via property' => [CommandWithNoAliasViaProperty::class],
    ]);
});

test('a fully string command line calls the same as an array one', function () {
    $computer = new Application(
        Mockery::mock(ApplicationContract::class, ['version' => '6.0']),
        Mockery::mock(Dispatcher::class, ['dispatch' => null]),
        'testing'
    );

    $codeOfCallingArrayInput = $computer->call('help', [
        '--raw' => true,
        '--format' => 'txt',
        '--no-interaction' => true,
        '--env' => 'testing',
    ]);

    $outputOfCallingArrayInput = $computer->output();

    $codeOfCallingStringInput = $computer->call(
        'help --raw --format=txt --no-interaction --env=testing'
    );

    $outputOfCallingStringInput = $computer->output();

    expect($codeOfCallingArrayInput)->toBe($codeOfCallingStringInput)
        ->and($outputOfCallingArrayInput)->toBe($outputOfCallingStringInput);
});

describe('prompting for missing input', function () {
    test('a missing required argument is prompted for', function () {
        $computer = consoleApplicationFor($app = new FoundationApplication(__DIR__));

        $computer->addCommands([$command = new FakeCommandWithInputPrompting]);

        $command->setVenusian($app);

        $exitCode = $computer->call('fake-command-for-testing');

        expect($command->prompted)->toBeTrue()
            ->and($command->argument('name'))->toBe('foo')
            ->and($exitCode)->toBe(0);
    });

    test('a supplied required argument is not prompted for', function () {
        $computer = consoleApplicationFor(new FoundationApplication(__DIR__));

        $computer->addCommands([$command = new FakeCommandWithInputPrompting]);

        $exitCode = $computer->call('fake-command-for-testing', [
            'name' => 'foo',
        ]);

        expect($command->prompted)->toBeFalse()
            ->and($command->argument('name'))->toBe('foo')
            ->and($exitCode)->toBe(0);
    });

    test('missing required array arguments are prompted for', function () {
        $computer = consoleApplicationFor($app = new FoundationApplication(__DIR__));

        $computer->addCommands([$command = new FakeCommandWithArrayInputPrompting]);

        $command->setVenusian($app);

        $exitCode = $computer->call('fake-command-for-testing-array');

        expect($command->prompted)->toBeTrue()
            ->and($command->argument('names'))->toBe(['foo'])
            ->and($exitCode)->toBe(0);
    });

    test('supplied required array arguments are not prompted for', function () {
        $computer = consoleApplicationFor(new FoundationApplication(__DIR__));

        $computer->addCommands([$command = new FakeCommandWithArrayInputPrompting]);

        $exitCode = $computer->call('fake-command-for-testing-array', [
            'names' => ['foo', 'bar', 'baz'],
        ]);

        expect($command->prompted)->toBeFalse()
            ->and($command->argument('names'))->toBe(['foo', 'bar', 'baz'])
            ->and($exitCode)->toBe(0);
    });

    test('call accepts a command instance', function () {
        $computer = consoleApplicationFor($app = new FoundationApplication(__DIR__));

        $computer->addCommands([$command = new FakeCommandWithInputPrompting]);

        $command->setVenusian($app);

        $exitCode = $computer->call($command);

        expect($command->prompted)->toBeTrue()
            ->and($command->argument('name'))->toBe('foo')
            ->and($exitCode)->toBe(0);
    });
});

// Upstream marks this case #[RunInSeparateProcess]; Pest has no equivalent.
test('load registers command classes but ignores PHPUnit test files', function () {
    $files = new Filesystem;

    $files->ensureDirectoryExists(join_paths(default_skeleton_path(), 'app', 'Console', 'Commands'), 0755, true);

    try {
        $files->put(
            join_paths(default_skeleton_path(), 'app', 'Console', 'Commands', 'ExampleCommand.php'),
            '<?php namespace App\Console\Commands; class ExampleCommand extends \Voyager\Console\Command { protected ?string $signature = "example"; public function handle() {} }'
        );

        $files->put(
            join_paths(default_skeleton_path(), 'app', 'Console', 'Commands', 'ExampleCommandTest.php'),
            '<?php namespace App\Console\Commands; class ExampleCommandTest extends \Voyager\Console\Command { protected ?string $signature = "example-test"; public function handle() {} }'
        );

        $files->put(
            join_paths(default_skeleton_path(), 'app', 'Console', 'Commands', 'ExampleCommandUnitTest.php'),
            '<?php namespace App\Console\Commands; class ExampleCommandUnitTest extends \PHPUnit\Framework\TestCase { public function test_command() { $this->assertTrue(true); } }'
        );

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('App\\', [default_skeleton_path('app')]);
        }

        $app = Testbench::create(default_skeleton_path());
        $events = new EventsDispatcher($app);
        $app->instance('events', $events);

        $kernel = new TestKernel($app, $events);

        $commands = $kernel->getRegisteredCommands();

        expect($commands)->toContain('App\Console\Commands\ExampleCommand')
            ->and($commands)->toContain('App\Console\Commands\ExampleCommandTest')
            ->and($commands)->not->toContain('App\Console\Commands\ExampleCommandUnitTest');

        Testbench::flushState($this);
    } finally {
        $files->cleanDirectory(default_skeleton_path('app', 'Console', 'Commands'));
    }
});
