<?php

use Voyager\Console\Command;
use Voyager\Console\OutputStyle;
use Voyager\Vessel\ControlPanel;
use Voyager\Database\Seeder;
use Mockery as m;
use Mockery\Mock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class TestSeeder extends Seeder
{
    public function run()
    {
        //
    }
}

class TestDepsSeeder extends Seeder
{
    public function run(Mock $someDependency, $someParam = '')
    {
        //
    }
}

test('call resolve the class and calls run', function () {
    $seeder = new TestSeeder;
    $seeder->setContainer($container = m::mock(ControlPanel::class));
    $output = new OutputStyle(new ArrayInput([]), new NullOutput);
    $command = m::mock(Command::class);
    $command->shouldReceive('getOutput')->times(3)->andReturn($output);
    $seeder->setCommand($command);
    $container->shouldReceive('make')->once()->with('ClassName')->andReturn($child = m::mock(Seeder::class));
    $child->shouldReceive('setContainer')->once()->with($container)->andReturn($child);
    $child->shouldReceive('setCommand')->once()->with($command)->andReturn($child);
    $child->shouldReceive('__invoke')->once();

    $seeder->call('ClassName');
});

test('set container', function () {
    $seeder = new TestSeeder;
    $container = m::mock(ControlPanel::class);
    $this->assertEquals($seeder->setContainer($container), $seeder);
});

test('set command', function () {
    $seeder = new TestSeeder;
    $command = m::mock(Command::class);
    $this->assertEquals($seeder->setCommand($command), $seeder);
});

test('inject dependencies on run method', function () {
    $container = m::mock(ControlPanel::class);
    $container->shouldReceive('call');

    $seeder = new TestDepsSeeder;
    $seeder->setContainer($container);

    $seeder->__invoke();

    $container->shouldHaveReceived('call')->once()->with([$seeder, 'run'], []);
});

test('send params on call method with deps', function () {
    $container = m::mock(ControlPanel::class);
    $container->shouldReceive('call');

    $seeder = new TestDepsSeeder;
    $seeder->setContainer($container);

    $seeder->__invoke(['test1', 'test2']);

    $container->shouldHaveReceived('call')->once()->with([$seeder, 'run'], ['test1', 'test2']);
});
