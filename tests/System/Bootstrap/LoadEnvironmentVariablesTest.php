<?php

use Voyager\System\Application;
use Voyager\System\Bootstrap\LoadEnvironmentVariables;

/** An application that reports the given environment file under tests/System/fixtures. */
function environmentApplicationFor(string $file): Mockery\MockInterface
{
    $app = Mockery::mock(Application::class);

    $app->shouldReceive('configurationIsCached')
        ->once()->with()->andReturn(false);
    $app->shouldReceive('runningInConsole')
        ->once()->with()->andReturn(false);
    $app->shouldReceive('environmentPath')
        ->once()->with()->andReturn(__DIR__.'/../fixtures');
    $app->shouldReceive('environmentFile')
        ->once()->with()->andReturn($file);

    return $app;
}

afterEach(function () {
    unset($_ENV['FOO'], $_SERVER['FOO']);
    putenv('FOO');
});

test('the environment file is loaded into every accessor', function () {
    $this->expectOutputString('');

    (new LoadEnvironmentVariables)->bootstrap(environmentApplicationFor('.env'));

    expect(env('FOO'))->toBe('BAR')
        ->and(getenv('FOO'))->toBe('BAR')
        ->and($_ENV['FOO'])->toBe('BAR')
        ->and($_SERVER['FOO'])->toBe('BAR');
});

test('a missing environment file fails silently', function () {
    $this->expectOutputString('');

    set_error_handler(static fn (): bool => true);

    try {
        (new LoadEnvironmentVariables)->bootstrap(environmentApplicationFor('BAD_FILE'));
    } finally {
        restore_error_handler();
    }
});
