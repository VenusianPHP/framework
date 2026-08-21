<?php

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Console\Fixtures\CommandInteractsWithIO;
use Voyager\Console\OutputStyle;

test('withProgressBar walks an iterable, handing the callback the value, bar and key', function ($iterable) {
    $command = new CommandInteractsWithIO;
    $bufferedOutput = new BufferedOutput;
    $output = Mockery::mock(OutputStyle::class, [new ArgvInput, $bufferedOutput])->makePartial();
    $command->setOutput($output);

    $output->shouldReceive('createProgressBar')
        ->once()
        ->with(count($iterable))
        ->andReturnUsing(function ($steps) use ($bufferedOutput) {
            // we can't mock ProgressBar because it's final, so return a real one
            return new ProgressBar($bufferedOutput, $steps);
        });

    $calledTimes = 0;
    $result = $command->withProgressBar($iterable, function ($value, $bar, $key) use (&$calledTimes, $iterable) {
        expect($bar)->toBeInstanceOf(ProgressBar::class)
            ->and($value)->toBe(array_values($iterable)[$calledTimes])
            ->and($key)->toBe(array_keys($iterable)[$calledTimes]);
        $calledTimes++;
    });

    expect($calledTimes)->toBe(count($iterable))
        ->and($result)->toBe($iterable);
})->with([
    'list' => [['a', 'b', 'c']],
    'keyed' => [['foo' => 'a', 'bar' => 'b', 'baz' => 'c']],
]);

test('withProgressBar accepts a step count and hands the callback the bar', function () {
    $command = new CommandInteractsWithIO;
    $bufferedOutput = new BufferedOutput;
    $output = Mockery::mock(OutputStyle::class, [new ArgvInput, $bufferedOutput])->makePartial();
    $command->setOutput($output);

    $totalSteps = 5;

    $output->shouldReceive('createProgressBar')
        ->once()
        ->with($totalSteps)
        ->andReturnUsing(function ($steps) use ($bufferedOutput) {
            // we can't mock ProgressBar because it's final, so return a real one
            return new ProgressBar($bufferedOutput, $steps);
        });

    $called = false;
    $command->withProgressBar($totalSteps, function ($bar) use (&$called) {
        expect($bar)->toBeInstanceOf(ProgressBar::class);
        $called = true;
    });

    expect($called)->toBeTrue();
});
