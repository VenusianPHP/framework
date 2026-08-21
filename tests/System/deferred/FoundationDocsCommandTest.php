<?php

use Orchestra\Testbench\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Voyager\Contracts\Console\Kernel;
use Voyager\MagicAliases\Http;
use Voyager\System\Console\DocsCommand;

uses(TestCase::class);

/**
 * A fresh docs command pinned to version 8.30.12, recording the URL it opens
 * onto the test case's `openedUrl` property.
 */
function docsCommand($testCase): DocsCommand
{
    $testCase->app->forgetInstance(DocsCommand::class);

    return $testCase->app->make(DocsCommand::class)
        ->setVersion('8.30.12')
        ->setUrlOpener(function ($url) use ($testCase) {
            $testCase->openedUrl = $url;
        });
}

beforeEach(function () {
    $this->openedUrl = null;

    Http::preventStrayRequests()->fake([
        'https://laravel.com/docs/8.x/index.json' => Http::response(file_get_contents(__DIR__.'/fixtures/docs.json')),
    ]);

    $this->app[Kernel::class]->registerCommand(docsCommand($this));
});

afterEach(function () {
    putenv('ARTISAN_DOCS_ASK_STRATEGY');
    putenv('ARTISAN_DOCS_OPEN_STRATEGY');
});

test('it can open the Laravel documentation', function () {
    $this->artisan('docs')
        ->expectsQuestion('Which page would you like to open?', '')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/installation')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/installation');
});

test('the autocomplete answer can be given in its original casing', function () {
    $this->artisan('docs')
        ->expectsQuestion('Which page would you like to open?', 'Laravel Dusk')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/dusk')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/dusk');
});

test('the autocomplete answer can be given in lower casing', function () {
    $this->artisan('docs')
        ->expectsQuestion('Which page would you like to open?', 'laravel dusk')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/dusk')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/dusk');
});

test('a section that starts with the input is matched', function () {
    $this->artisan('docs el-col uni')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent-collections#method-unique')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent-collections#method-unique');
});

test('a section is matched fuzzily', function () {
    $this->artisan('docs el-col qery')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent-collections#method-toquery')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent-collections#method-toquery');
});

test('the page to visit can be given directly', function () {
    $this->artisan('docs eloquent\ collections')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent-collections')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent-collections');
});

test('hyphens can stand in for escaped spaces', function () {
    $this->artisan('docs eloquent-collections')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent-collections')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent-collections');
});

test('a match below the minimum score opens the index', function () {
    $this->artisan('docs zag')
        ->expectsOutputToContain('Unable to determine the page you are trying to visit.')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x');
});

test('the minimum score accounts for the input length', function () {
    $this->artisan('docs z')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/localization')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/localization');
});

describe('ask strategies', function () {
    test('a custom ask strategy is used', function () {
        putenv('ARTISAN_DOCS_ASK_STRATEGY='.__DIR__.'/fixtures/always-dusk-ask-strategy.php');

        $this->artisan('docs')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/dusk')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/dusk');
    });

    test('bad syntax falls back to autocomplete', function () {
        putenv('ARTISAN_DOCS_ASK_STRATEGY='.__DIR__.'/fixtures/bad-syntax-strategy.php');

        $this->artisan('docs')
            ->expectsQuestion('Which page would you like to open?', 'laravel dusk')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/dusk')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/dusk');
    });

    test('a bad return value falls back to autocomplete', function () {
        putenv('ARTISAN_DOCS_ASK_STRATEGY='.__DIR__.'/fixtures/bad-return-strategy.php');

        $this->artisan('docs')
            ->expectsQuestion('Which page would you like to open?', 'laravel dusk')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/dusk')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/dusk');
    });

    test('a process interrupt exits with 130', function () {
        putenv('ARTISAN_DOCS_ASK_STRATEGY='.__DIR__.'/fixtures/process-interrupt-strategy.php');

        $this->artisan('docs')->assertExitCode(130);
    });

    test('any other exception bubbles up', function () {
        putenv('ARTISAN_DOCS_ASK_STRATEGY='.__DIR__.'/fixtures/exception-throwing-strategy.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('strategy failed');

        $this->artisan('docs');
    });

    test('a non interrupt process failure bubbles up', function () {
        putenv('ARTISAN_DOCS_ASK_STRATEGY='.__DIR__.'/fixtures/process-failure-strategy.php');

        $this->expectException(ProcessFailedException::class);

        if (PHP_OS_FAMILY === 'Windows') {
            $this->expectExceptionMessage('The command "expected-command" failed.

Exit Code: 1(General error)

Working directory: expected-working-directory');
        } else {
            $this->expectExceptionMessage('The command "\'expected-command\'" failed.

Exit Code: 1(General error)

Working directory: expected-working-directory');
        }

        $this->artisan('docs');
    });
});

describe('guessing the page', function () {
    test('the input is the start of a page title', function () {
        $this->artisan('docs elo')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent');
    });

    test('the input is contained somewhere in a page title', function () {
        $this->artisan('docs quent')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent');
    });

    test('the input matches the top and the tail', function () {
        $this->artisan('docs elo-col')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/eloquent-collections')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/eloquent-collections');
    });

    test('a direct substring outranks an arbitrary match', function () {
        $this->artisan('docs ora')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/filesystem')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/filesystem');
    });

    test('poor spelling is tolerated', function () {
        $this->artisan('docs vewis')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/views')
            ->assertSuccessful();

        expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x/views');
    });
});

describe('opening the URL', function () {
    test('a custom open command can be set through the environment', function () {
        $GLOBALS['open-strategy-output-path'] = __DIR__.'/output.txt';
        putenv('ARTISAN_DOCS_OPEN_STRATEGY='.__DIR__.'/fixtures/open-strategy.php');
        $this->app[Kernel::class]->registerCommand(docsCommand($this)->setUrlOpener(null));

        @unlink($GLOBALS['open-strategy-output-path']);

        $this->artisan('docs installation')
            ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x/installation')
            ->assertSuccessful();

        if (PHP_OS_FAMILY === 'Windows') {
            expect(trim(file_get_contents($GLOBALS['open-strategy-output-path'])))->toBe('"https://laravel.com/docs/8.x/installation?expected-query=1"');
        } else {
            expect(trim(file_get_contents($GLOBALS['open-strategy-output-path'])))->toBe('https://laravel.com/docs/8.x/installation?expected-query=1');
        }

        @unlink($GLOBALS['open-strategy-output-path']);
        unset($GLOBALS['open-strategy-output-path']);
    });

    test('bad syntax in an opener is handled', function () {
        putenv('ARTISAN_DOCS_OPEN_STRATEGY='.__DIR__.'/fixtures/bad-syntax-strategy.php');
        $this->app[Kernel::class]->registerCommand(docsCommand($this)->setUrlOpener(null));

        $this->artisan('docs installation')
            ->expectsOutputToContain('Unable to open the URL with your custom strategy. You will need to open it yourself.')
            ->assertSuccessful();
    });

    test('a bad return type from an opener is handled', function () {
        putenv('ARTISAN_DOCS_OPEN_STRATEGY='.__DIR__.'/fixtures/bad-return-strategy.php');
        $this->app[Kernel::class]->registerCommand(docsCommand($this)->setUrlOpener(null));

        $this->artisan('docs installation')
            ->expectsOutputToContain('Unable to open the URL with your custom strategy. You will need to open it yourself.')
            ->assertSuccessful();
    });

    test('an unknown system is told to open the URL manually', function () {
        $this->app[Kernel::class]->registerCommand(docsCommand($this)->setUrlOpener(null)->setSystemOsFamily('Laravel OS'));

        $this->artisan('docs validation')
            ->expectsOutputToContain('Unable to open the URL on your system. You will need to open it yourself or create a custom opener for your system.')
            ->assertSuccessful();
    });
});

test('a search can be performed against laravel.com', function () {
    $argCache = $_SERVER['argv'];
    $_SERVER['argv'] = explode(' ', 'artisan docs -- here is my search term for the laravel website');
    $this->app[Kernel::class]->registerCommand(docsCommand($this));

    $this->artisan('docs -- here is my search term for the laravel website')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x?q=here%20is%20my%20search%20term%20for%20the%20laravel%20website')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x?q=here%20is%20my%20search%20term%20for%20the%20laravel%20website');

    $_SERVER['argv'] = $argCache;
});

test('the no interaction option opens the index', function () {
    $this->artisan('docs -n')
        ->expectsOutputToContain('Opening the docs to: https://laravel.com/docs/8.x')
        ->assertSuccessful();

    expect($this->openedUrl)->toBe('https://laravel.com/docs/8.x');
});

test('help can be read without instantiating the dependencies', function () {
    $help = (new DocsCommand)->getHelp();

    $this->stringContains('php artisan docs', $help);
});
