<?php

namespace Tests\Database;

use Voyager\Console\Command;
use Voyager\Console\OutputStyle;
use Voyager\Console\View\Components\Factory;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\Console\Seeds\SeedCommand;
use Voyager\Database\Console\Seeds\WithoutModelEvents;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Seeder;
use Voyager\Events\NullDispatcher;
use Voyager\Testing\Assert;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

afterEach(function () {
    SeedCommand::prohibit(false);

    Model::unsetEventDispatcher();
});

test('handle', function () {
    $input = new ArrayInput(['--force' => true, '--database' => 'sqlite']);
    $output = new NullOutput;
    $outputStyle = new OutputStyle($input, $output);

    $seeder = m::mock(Seeder::class);
    $seeder->shouldReceive('setContainer')->once()->andReturnSelf();
    $seeder->shouldReceive('setCommand')->once()->andReturnSelf();
    $seeder->shouldReceive('__invoke')->once();

    $resolver = m::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('getDefaultConnection')->once();
    $resolver->shouldReceive('setDefaultConnection')->once()->with('sqlite');

    $container = m::mock(Vessel::class);
    $container->shouldReceive('call');
    $container->shouldReceive('environment')->once()->andReturn('testing');
    $container->shouldReceive('runningUnitTests')->andReturn('true');
    $container->shouldReceive('make')->with('DatabaseSeeder')->andReturn($seeder);
    $container->shouldReceive('make')->with(OutputStyle::class, m::any())->andReturn(
        $outputStyle
    );
    $container->shouldReceive('make')->with(Factory::class, m::any())->andReturn(
        new Factory($outputStyle)
    );

    $command = new SeedCommand($resolver);
    $command->setVenusian($container);

    // call run to set up IO, then fire manually.
    $command->run($input, $output);
    $command->handle();

    $container->shouldHaveReceived('call')->with([$command, 'handle']);
});

test('without model events', function () {
    $input = new ArrayInput([
        '--force' => true,
        '--database' => 'sqlite',
        '--class' => UserWithoutModelEventsSeeder::class,
    ]);
    $output = new NullOutput;
    $outputStyle = new OutputStyle($input, $output);

    $instance = new UserWithoutModelEventsSeeder();

    $seeder = m::mock($instance);
    $seeder->shouldReceive('setContainer')->once()->andReturnSelf();
    $seeder->shouldReceive('setCommand')->once()->andReturnSelf();

    $resolver = m::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('getDefaultConnection')->once();
    $resolver->shouldReceive('setDefaultConnection')->once()->with('sqlite');

    $container = m::mock(Vessel::class);
    $container->shouldReceive('call');
    $container->shouldReceive('environment')->once()->andReturn('testing');
    $container->shouldReceive('runningUnitTests')->andReturn('true');
    $container->shouldReceive('make')->with(UserWithoutModelEventsSeeder::class)->andReturn($seeder);
    $container->shouldReceive('make')->with(OutputStyle::class, m::any())->andReturn(
        $outputStyle
    );
    $container->shouldReceive('make')->with(Factory::class, m::any())->andReturn(
        new Factory($outputStyle)
    );

    $command = new SeedCommand($resolver);
    $command->setVenusian($container);

    Model::setEventDispatcher($dispatcher = m::mock(Dispatcher::class));

    // call run to set up IO, then fire manually.
    $command->run($input, $output);
    $command->handle();

    Assert::assertSame($dispatcher, Model::getEventDispatcher());

    $container->shouldHaveReceived('call')->with([$command, 'handle']);
});

test('prohibitable', function () {
    $input = new ArrayInput([]);
    $output = new NullOutput;
    $outputStyle = new OutputStyle($input, $output);

    $resolver = m::mock(ConnectionResolverInterface::class);

    $container = m::mock(Vessel::class);
    $container->shouldReceive('call');
    $container->shouldReceive('runningUnitTests')->andReturn('true');
    $container->shouldReceive('make')->with(OutputStyle::class, m::any())->andReturn(
        $outputStyle
    );
    $container->shouldReceive('make')->with(Factory::class, m::any())->andReturn(
        new Factory($outputStyle)
    );

    $command = new SeedCommand($resolver);
    $command->setVenusian($container);

    // call run to set up IO, then fire manually.
    $command->run($input, $output);

    SeedCommand::prohibit();

    Assert::assertSame(Command::FAILURE, $command->handle());
});

class UserWithoutModelEventsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run()
    {
        Assert::assertInstanceOf(NullDispatcher::class, Model::getEventDispatcher());
    }
}
