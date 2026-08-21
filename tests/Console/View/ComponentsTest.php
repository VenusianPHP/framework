<?php

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Voyager\Console\OutputStyle;
use Voyager\Console\View\Components;
use Voyager\Database\Migrations\MigrationResult;

test('alert shouts the message', function () {
    $output = new BufferedOutput;

    (new Components\Alert($output))->render('The application is in the [production] environment');

    expect($output->fetch())->toContain('THE APPLICATION IS IN THE [PRODUCTION] ENVIRONMENT.');
});

test('a bullet list renders one bullet per item', function () {
    $output = new BufferedOutput;

    (new Components\BulletList($output))->render([
        'ls -la',
        'php computer inspire',
    ]);

    expect($output->fetch())
        ->toContain('⇂ ls -la')
        ->toContain('⇂ php computer inspire');
});

test('success renders a labelled line', function () {
    $output = new BufferedOutput;

    (new Components\Success($output))->render('The application is in the [production] environment');

    expect($output->fetch())->toContain('SUCCESS  The application is in the [production] environment.');
});

test('error renders a labelled line', function () {
    $output = new BufferedOutput;

    (new Components\Error($output))->render('The application is in the [production] environment');

    expect($output->fetch())->toContain('ERROR  The application is in the [production] environment.');
});

test('info renders a labelled line', function () {
    $output = new BufferedOutput;

    (new Components\Info($output))->render('The application is in the [production] environment');

    expect($output->fetch())->toContain('INFO  The application is in the [production] environment.');
});

test('warn renders a labelled line', function () {
    $output = new BufferedOutput;

    (new Components\Warn($output))->render('The application is in the [production] environment');

    expect($output->fetch())->toContain('WARN  The application is in the [production] environment.');
});

test('confirm asks the question with the given default', function () {
    $output = Mockery::mock(OutputStyle::class);

    $output->shouldReceive('confirm')
        ->with('Question?', false)
        ->once()
        ->andReturnTrue();

    expect((new Components\Confirm($output))->render('Question?'))->toBeTrue();

    $output->shouldReceive('confirm')
        ->with('Question?', true)
        ->once()
        ->andReturnTrue();

    expect((new Components\Confirm($output))->render('Question?', true))->toBeTrue();
});

test('choice asks a choice question and returns the answer', function () {
    $output = Mockery::mock(OutputStyle::class);

    $output->shouldReceive('askQuestion')
        ->with(Mockery::type(ChoiceQuestion::class))
        ->once()
        ->andReturn('a');

    expect((new Components\Choice($output))->render('Question?', ['a', 'b']))->toBe('a');
});

test('a task renders its label and outcome', function ($result, $label) {
    $output = new BufferedOutput;

    (new Components\Task($output))->render('My task', fn () => $result->value);

    expect($output->fetch())
        ->toContain('My task')
        ->toContain($label);
})->with([
    'success' => [MigrationResult::Success, 'DONE'],
    'failure' => [MigrationResult::Failure, 'FAIL'],
    'skipped' => [MigrationResult::Skipped, 'SKIPPED'],
]);

test('a two column detail renders both columns', function () {
    $output = new BufferedOutput;

    (new Components\TwoColumnDetail($output))->render('First', 'Second');

    expect($output->fetch())
        ->toContain('First')
        ->toContain('Second');
});

test('a two column detail preserves trailing punctuation in the value', function () {
    $output = new BufferedOutput;

    (new Components\TwoColumnDetail($output))->render('Key', 'value!');

    expect($output->fetch())->toContain('value!');
});
