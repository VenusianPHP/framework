<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Tests\Console\Fixtures\AliasedCommand;
use Tests\Console\Fixtures\ArgumentsAndOptionsCommand;
use Tests\Console\Fixtures\HandlingCommand;
use Tests\Console\Fixtures\HiddenAwareCommand;
use Tests\Console\Fixtures\HiddenPropertyCommand;
use Voyager\Console\Application;
use Voyager\Console\Command;
use Voyager\Console\OutputStyle;
use Voyager\Console\View\Components\Factory;

test('calling a class command resolves it through the application', function () {
    $command = new HandlingCommand;

    $application = Mockery::mock(Application::class);
    $command->setVenusian($application);

    $input = new ArrayInput([]);
    $output = new NullOutput;
    $outputStyle = Mockery::mock(OutputStyle::class);
    $application->shouldReceive('make')->with(OutputStyle::class, ['input' => $input, 'output' => $output])->andReturn($outputStyle);
    $application->shouldReceive('make')->with(Factory::class, ['output' => $outputStyle])->andReturn(Mockery::mock(Factory::class));

    $application->shouldReceive('call')->with([$command, 'handle'])->andReturnUsing(function () use ($command, $application) {
        $commandCalled = Mockery::mock(Command::class);

        $application->shouldReceive('make')->once()->with(Command::class)->andReturn($commandCalled);

        $commandCalled->shouldReceive('setApplication')->once()->with(null);
        $commandCalled->shouldReceive('setVenusian')->once()->with($application);
        $commandCalled->shouldReceive('run')->once();

        $command->call(Command::class);
    });
    $application->shouldReceive('runningUnitTests')->andReturn(true);

    $command->run($input, $output);
});

test('arguments and options declared by class are readable after a run', function () {
    $command = new ArgumentsAndOptionsCommand;

    $application = app();
    $command->setVenusian($application);

    $input = new ArrayInput([
        'argument-one' => 'test-first-argument',
        'argument-two' => 'test-second-argument',
        '--option-one' => 'test-first-option',
        '--option-two' => 'test-second-option',
    ]);
    $output = new NullOutput;

    $command->run($input, $output);

    expect($command->argument('argument-one'))->toBe('test-first-argument')
        ->and($command->argument('argument-two'))->toBe('test-second-argument')
        ->and($command->argument('argument-three'))->toBe('third-argument-default')
        ->and($command->option('option-one'))->toBe('test-first-option')
        ->and($command->option('option-two'))->toBe('test-second-option')
        ->and($command->option('option-three'))->toBe('third-option-default');
});

test('the input setter overwrites the input', function () {
    $input = Mockery::mock(InputInterface::class);
    $input->shouldReceive('hasArgument')->once()->with('foo')->andReturn(false);

    $command = new Command;
    $command->setInput($input);

    expect($command->hasArgument('foo'))->toBeFalse();
});

test('the output setter overwrites the output', function () {
    $output = Mockery::mock(OutputStyle::class);
    $output->shouldReceive('writeln')->once()->withArgs(function (...$args) {
        return $args[0] === '<info>foo</info>';
    });

    $command = new Command;
    $command->setOutput($output);

    $command->info('foo');
});

describe('hidden', function () {
    test('setHidden hides the command from both the child and the parent', function () {
        $command = new HiddenAwareCommand;

        expect($command->isHidden())->toBeFalse()
            ->and($command->parentIsHidden())->toBeFalse();

        $command->setHidden(true);

        expect($command->isHidden())->toBeTrue()
            ->and($command->parentIsHidden())->toBeTrue();
    });

    test('the hidden property seeds the command and can be unset', function () {
        $command = new HiddenPropertyCommand;

        expect($command->isHidden())->toBeTrue()
            ->and($command->parentIsHidden())->toBeTrue();

        $command->setHidden(false);

        expect($command->isHidden())->toBeFalse()
            ->and($command->parentIsHidden())->toBeFalse();
    });
});

test('the aliases property is applied to the command', function () {
    expect((new AliasedCommand)->getAliases())->toBe(['bar:baz', 'baz:qux']);
});

describe('choice', function () {
    test('is single select by default', function () {
        $output = Mockery::mock(OutputStyle::class);
        $output->shouldReceive('askQuestion')->once()->withArgs(function (ChoiceQuestion $question) {
            return $question->isMultiselect() === false;
        });

        $command = new Command;
        $command->setOutput($output);

        $command->choice('Do you need further help?', ['yes', 'no']);
    });

    test('can be made multiselect', function () {
        $output = Mockery::mock(OutputStyle::class);
        $output->shouldReceive('askQuestion')->once()->withArgs(function (ChoiceQuestion $question) {
            return $question->isMultiselect() === true;
        });

        $command = new Command;
        $command->setOutput($output);

        $command->choice('Select all that apply.', ['option-1', 'option-2', 'option-3'], null, null, true);
    });
});
