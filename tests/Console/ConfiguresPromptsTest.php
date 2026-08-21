<?php

use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Console\Fixtures\PromptingCommand;
use Voyager\Console\Application;
use Voyager\Console\Command;
use Voyager\Console\OutputStyle;
use Voyager\Console\View\Components\Factory;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * Run a command against a mocked application, letting the caller set the
 * expectations on the view-component factory the prompt falls back to.
 */
function runPromptingCommand(Command $command, callable $expectations): void
{
    $command->setVenusian($application = Mockery::mock(Application::class));

    $application->shouldReceive('make')->withArgs(fn ($abstract) => $abstract === OutputStyle::class)->andReturn($outputStyle = Mockery::mock(OutputStyle::class));
    $application->shouldReceive('make')->withArgs(fn ($abstract) => $abstract === Factory::class)->andReturn($factory = Mockery::mock(Factory::class));
    $application->shouldReceive('runningUnitTests')->andReturn(false);
    $application->shouldReceive('call')->with([$command, 'handle'])->andReturnUsing(fn ($callback) => call_user_func($callback));
    $outputStyle->shouldReceive('newLinesWritten')->andReturn(1);

    $expectations($factory);

    $command->run(new ArrayInput([]), new NullOutput);
}

test('the select fallback asks a single choice and casts the answer back', function ($prompt, $expectedOptions, $expectedDefault, $return, $expectedReturn) {
    Prompt::fallbackWhen(true);

    $command = new PromptingCommand($prompt);

    runPromptingCommand($command, fn ($components) => $components
        ->expects('choice')
        ->with('Test', $expectedOptions, $expectedDefault)
        ->andReturn($return)
    );

    expect($command->answer)->toBe($expectedReturn);
})->with([
    'list with no default' => [fn () => select('Test', ['a', 'b', 'c']), ['a', 'b', 'c'], null, 'b', 'b'],
    'numeric keys with no default' => [fn () => select('Test', [1 => 'a', 2 => 'b', 3 => 'c']), [1 => 'a', 2 => 'b', 3 => 'c'], null, '2', 2],
    'assoc with no default' => [fn () => select('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C']), ['a' => 'A', 'b' => 'B', 'c' => 'C'], null, 'b', 'b'],
    'list with default' => [fn () => select('Test', ['a', 'b', 'c'], 'b'), ['a', 'b', 'c'], 'b', 'b', 'b'],
    'numeric keys with default' => [fn () => select('Test', [1 => 'a', 2 => 'b', 3 => 'c'], 2), [1 => 'a', 2 => 'b', 3 => 'c'], 2, '2', 2],
    'assoc with default' => [fn () => select('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C'], 'b'), ['a' => 'A', 'b' => 'B', 'c' => 'C'], 'b', 'b', 'b'],
]);

test('the multiselect fallback asks a multiple choice and casts the answers back', function ($prompt, $expectedOptions, $expectedDefault, $return, $expectedReturn) {
    Prompt::fallbackWhen(true);

    $command = new PromptingCommand($prompt);

    runPromptingCommand($command, fn ($components) => $components
        ->expects('choice')
        ->with('Test', $expectedOptions, $expectedDefault, null, true)
        ->andReturn($return)
    );

    expect($command->answer)->toBe($expectedReturn);
})->with([
    'list with no default' => [fn () => multiselect('Test', ['a', 'b', 'c']), ['None', 'a', 'b', 'c'], 'None', ['None'], []],
    'numeric keys with no default' => [fn () => multiselect('Test', [1 => 'a', 2 => 'b', 3 => 'c']), ['' => 'None', 1 => 'a', 2 => 'b', 3 => 'c'], 'None', [''], []],
    'assoc with no default' => [fn () => multiselect('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C']), ['' => 'None', 'a' => 'A', 'b' => 'B', 'c' => 'C'], 'None', [''], []],
    'list with default' => [fn () => multiselect('Test', ['a', 'b', 'c'], ['b', 'c']), ['None', 'a', 'b', 'c'], 'b,c', ['b', 'c'], ['b', 'c']],
    'numeric keys with default' => [fn () => multiselect('Test', [1 => 'a', 2 => 'b', 3 => 'c'], [2, 3]), ['' => 'None', 1 => 'a', 2 => 'b', 3 => 'c'], '2,3', ['2', '3'], [2, 3]],
    'assoc with default' => [fn () => multiselect('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C'], ['b', 'c']), ['' => 'None', 'a' => 'A', 'b' => 'B', 'c' => 'C'], 'b,c', ['b', 'c'], ['b', 'c']],
    'required list with no default' => [fn () => multiselect('Test', ['a', 'b', 'c'], required: true), ['a', 'b', 'c'], null, ['b', 'c'], ['b', 'c']],
    'required numeric keys with no default' => [fn () => multiselect('Test', [1 => 'a', 2 => 'b', 3 => 'c'], required: true), [1 => 'a', 2 => 'b', 3 => 'c'], null, ['2', '3'], [2, 3]],
    'required assoc with no default' => [fn () => multiselect('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C'], required: true), ['a' => 'A', 'b' => 'B', 'c' => 'C'], null, ['b', 'c'], ['b', 'c']],
    'required list with default' => [fn () => multiselect('Test', ['a', 'b', 'c'], ['b', 'c'], required: true), ['a', 'b', 'c'], 'b,c', ['b', 'c'], ['b', 'c']],
    'required numeric keys with default' => [fn () => multiselect('Test', [1 => 'a', 2 => 'b', 3 => 'c'], [2, 3], required: true), [1 => 'a', 2 => 'b', 3 => 'c'], '2,3', ['2', '3'], [2, 3]],
    'required assoc with default' => [fn () => multiselect('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C'], ['b', 'c'], required: true), ['a' => 'A', 'b' => 'B', 'c' => 'C'], 'b,c', ['b', 'c'], ['b', 'c']],
]);
