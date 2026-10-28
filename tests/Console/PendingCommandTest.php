<?php

use Mockery\Exception\InvalidCountException;
use Mockery\Exception\InvalidOrderException;
use PHPUnit\Framework\AssertionFailedError;
use Venusian\Tests\Console\Fixtures\PendingCommands;
use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\Console\Testing\InteractsWithConsole;
use Voyager\Contracts\Console\Kernel;

// Laravel v13.37.0 tests/Integration/Testing/ArtisanCommandTest, on Voyager's console kernel.
uses(InteractsWithConsole::class);

beforeEach(function () {
    $this->app = LogApp::boot();

    foreach (PendingCommands::all() as $command) {
        $this->app->registerSingleton($command);
    }

    $this->app->make(Kernel::class)->addCommands(PendingCommands::all());
});

afterEach(fn () => LogApp::tearDown($this->app, $this));

/** Verify the PendingCommand's mock expectations now, so an unmet one throws inside the test. */
function verifyMockeryExpectationsNow(): void
{
    Mockery::close();
}

/** Run $callback, then close Mockery while ignoring the count failures PendingCommand's mocks would raise at teardown. */
function ignoringMockOnceExceptions(callable $callback): void
{
    try {
        $callback();
    } finally {
        try {
            Mockery::close();
        } catch (InvalidCountException) {
            // Ignore mock exception from PendingCommand::expectsOutput().
        }
    }
}

test('console command that passes', function () {
    $this->artisan('exit', ['code' => 0])->assertOk();
});

test('console command that fails', function () {
    $this->expectExceptionObject(new AssertionFailedError('Expected status code 0 but received 1.'));

    $this->artisan('exit', ['code' => 1])->assertOk();
});

test('console command that passes with output', function () {
    $this->artisan('survey')
        ->expectsQuestion('What is your name?', 'Taylor Otwell')
        ->expectsQuestion('Which language do you prefer?', 'PHP')
        ->expectsOutput('Your name is Taylor Otwell and you prefer PHP.')
        ->doesntExpectOutput('Your name is Taylor Otwell and you prefer Ruby.')
        ->assertExitCode(0);
});

test('console command that passes with repeating output', function () {
    $this->artisan('slim')
        ->expectsQuestion('Who?', 'Taylor')
        ->expectsQuestion('What?', 'Taylor')
        ->expectsQuestion('Huh?', 'Taylor')
        ->expectsOutput('Taylor')
        ->doesntExpectOutput('Otwell')
        ->expectsOutput('Taylor')
        ->expectsOutput('Taylor')
        ->assertExitCode(0);
});

test('console command that fails from unexpected output', function () {
    $this->expectExceptionObject(new AssertionFailedError('Output "Your name is Taylor Otwell and you prefer PHP." was printed.'));

    $this->artisan('survey')
        ->expectsQuestion('What is your name?', 'Taylor Otwell')
        ->expectsQuestion('Which language do you prefer?', 'PHP')
        ->doesntExpectOutput('Your name is Taylor Otwell and you prefer PHP.')
        ->assertExitCode(0);
});

test('console command that fails from unexpected output substring', function () {
    $this->expectExceptionObject(new AssertionFailedError('Output "Taylor Otwell" was printed.'));

    $this->artisan('contains')
        ->doesntExpectOutputToContain('Taylor Otwell')
        ->assertExitCode(0);
});

test('console command that fails from zero as unexpected output', function () {
    $this->expectExceptionObject(new AssertionFailedError('Output "0" was printed.'));

    $this->artisan('zero')
        ->doesntExpectOutput('0')
        ->assertExitCode(0);
});

test('console command that fails from zero as unexpected output substring', function () {
    $this->expectExceptionObject(new AssertionFailedError('Output "0" was printed.'));

    $this->artisan('zero')
        ->doesntExpectOutputToContain('0')
        ->assertExitCode(0);
});

test('console command that fails from missing output', function () {
    $this->expectExceptionObject(new AssertionFailedError('Output "Your name is Taylor Otwell and you prefer PHP." was not printed.'));

    ignoringMockOnceExceptions(function () {
        $this->artisan('survey')
            ->expectsQuestion('What is your name?', 'Taylor Otwell')
            ->expectsQuestion('Which language do you prefer?', 'Ruby')
            ->expectsOutput('Your name is Taylor Otwell and you prefer PHP.')
            ->assertExitCode(0);
    });
});

test('console command that fails from exit code mismatch', function () {
    $this->expectExceptionObject(new AssertionFailedError('Expected status code 1 but received 0.'));

    $this->artisan('survey')
        ->expectsQuestion('What is your name?', 'Taylor Otwell')
        ->expectsQuestion('Which language do you prefer?', 'PHP')
        ->assertExitCode(1);
});

test('console command that fails from unordered output', function () {
    $this->expectException(InvalidOrderException::class);

    ignoringMockOnceExceptions(function () {
        $this->artisan('slim')
            ->expectsQuestion('Who?', 'Taylor')
            ->expectsQuestion('What?', 'Danger')
            ->expectsQuestion('Huh?', 'Otwell')
            ->expectsOutput('Taylor')
            ->expectsOutput('Otwell')
            ->expectsOutput('Danger')
            ->assertExitCode(0);
    });
});

test('console command that passes if the output contains', function () {
    $this->artisan('contains')
        ->expectsOutputToContain('Taylor Otwell')
        ->assertExitCode(0);
});

test('console command that passes if outputs something', function () {
    $this->artisan('contains')
        ->expectsOutput()
        ->assertExitCode(0);
});

test('console command that passes if outputs is something and is the expected output', function () {
    $this->artisan('contains')
        ->expectsOutput()
        ->expectsOutput('My name is Taylor Otwell')
        ->assertExitCode(0);
});

test('console command that fail if doesnt output something', function () {
    $this->expectException(InvalidCountException::class);

    $this->artisan('exit', ['code' => 0])
        ->expectsOutput()
        ->assertExitCode(0);

    verifyMockeryExpectationsNow();
});

test('console command that fail if doesnt output something and is not the expected output', function () {
    $this->expectException(AssertionFailedError::class);

    ignoringMockOnceExceptions(function () {
        $this->artisan('exit', ['code' => 0])
            ->expectsOutput()
            ->expectsOutput('My name is Taylor Otwell')
            ->assertExitCode(0);
    });
});

test('console command that passes if does not output anything', function () {
    $this->artisan('exit', ['code' => 0])
        ->doesntExpectOutput()
        ->assertExitCode(0);
});

test('console command that passes if does not output anything and is not the expected output', function () {
    $this->artisan('exit', ['code' => 0])
        ->doesntExpectOutput()
        ->doesntExpectOutput('My name is Taylor Otwell')
        ->assertExitCode(0);
});

test('console command that passes if expects output and there is interactions', function () {
    $this->artisan('interactions', ['--no-interaction' => true])
        ->expectsOutput()
        ->expectsQuestion('What is your name?', 'Taylor Otwell')
        ->expectsChoice('Which language do you prefer?', 'PHP', ['PHP', 'PHP', 'PHP'])
        ->expectsConfirmation('Do you want to continue?', true)
        ->assertExitCode(0);
});

test('console command that fails if doesnt expect output but there is interactions', function () {
    $this->expectException(InvalidCountException::class);

    $this->artisan('interactions', ['--no-interaction' => true])
        ->doesntExpectOutput()
        ->expectsQuestion('What is your name?', 'Taylor Otwell')
        ->expectsChoice('Which language do you prefer?', 'PHP', ['PHP', 'PHP', 'PHP'])
        ->expectsConfirmation('Do you want to continue?', true)
        ->assertExitCode(0);

    verifyMockeryExpectationsNow();
});

test('console command that fails if doesnt expect output but outputs something', function () {
    $this->expectException(InvalidCountException::class);

    $this->artisan('contains')
        ->doesntExpectOutput()
        ->assertExitCode(0);

    verifyMockeryExpectationsNow();
});

test('console command that fails if doesnt expect output and does expect output', function () {
    $this->expectException(InvalidCountException::class);

    $this->artisan('contains')
        ->doesntExpectOutput()
        ->doesntExpectOutput('My name is Taylor Otwell')
        ->assertExitCode(0);

    verifyMockeryExpectationsNow();
});

test('console command that fails if the output does not contain', function () {
    $this->expectExceptionObject(new AssertionFailedError('Output does not contain "Otwell Taylor".'));

    ignoringMockOnceExceptions(function () {
        $this->artisan('contains')
            ->expectsOutputToContain('Otwell Taylor')
            ->assertExitCode(0);
    });
});

test('pending command can be tapped', function () {
    $newEngland = ['Connecticut', 'Maine', 'Massachusetts', 'New Hampshire', 'Rhode Island', 'Vermont'];

    $this->artisan('new-england')
        ->expectsOutput('The region of New England consists of the following states:')
        ->tap(function ($command) use ($newEngland) {
            foreach ($newEngland as $state) {
                $command->expectsOutput($state);
            }
        })
        ->assertExitCode(0);
});

test('console command returns code with disabled mocking', function () {
    $this->withoutMockingConsoleOutput();

    expect($this->artisan('exit', ['code' => 0]))->toBe(0);
});

test('mock console command returns pending command with disabled mocking', function () {
    $this->withoutMockingConsoleOutput();

    $this->mockArtisan('exit', ['code' => 0])->assertOk();
});

test('real console command returns code with enabled mocking', function () {
    expect($this->realArtisan('exit', ['code' => 0]))->toBe(0);
});
