<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\Contracts\Console\Kernel;

beforeEach(fn () => $this->app = LogApp::boot());

afterEach(fn () => LogApp::tearDown($this->app, $this));

/**
 * Every command the framework ships, by name: each #[AsCommand] class under src/Voyager.
 *
 * @return array<string, string> command name => source file
 */
function shippedCommands(): array
{
    $commands = [];
    $files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src/Voyager')), '/\.php$/');

    foreach ($files as $file) {
        if (preg_match("/#\\[AsCommand\\((?:name:\\s*)?'([^']+)'/", file_get_contents($file), $match)) {
            $commands[$match[1]] = substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1);
        }
    }

    ksort($commands);

    return $commands;
}

/** Runs one command through the console kernel and returns [exit status, output]. */
function computer($app, array $input): array
{
    $input = new ArrayInput($input);
    $input->setInteractive(false);
    $status = $app->make(Kernel::class)->handle($input, $output = new BufferedOutput);

    return [$status, $output->fetch()];
}

it('registers every command the framework ships', function () {
    $unregistered = [];

    foreach (shippedCommands() as $name => $file) {
        [$status, $output] = computer($this->app, ['command' => 'help', 'command_name' => $name]);

        if ($status !== 0) {
            $unregistered[$name] = $file.': '.trim(strtok($output, "\n"));
        }
    }

    expect($unregistered)->toBe([]);
});

it('generates code whose imports all resolve, from every make command that was switched off', function () {
    $dir = sys_get_temp_dir().'/venusian-make-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $this->app->useAppPath($dir);
    // The framework repository is not an app: give the generators the namespace an app's composer.json would.
    (fn () => $this->namespace = 'App\\')->call($this->app);

    try {
        foreach ([
            ['command' => 'make:event', 'name' => 'OrderShipped'],
            ['command' => 'make:gig', 'name' => 'ResizeImage'],
            ['command' => 'make:job', 'name' => 'SendReport'],
            ['command' => 'make:job', 'name' => 'SendBatch', '--batched' => true],
            ['command' => 'make:job-middleware', 'name' => 'RateLimited'],
            ['command' => 'make:listener', 'name' => 'NotifyCustomer', '--event' => 'OrderShipped'],
            ['command' => 'make:listener', 'name' => 'QueueCustomer', '--event' => 'OrderShipped', '--queued' => true],
            ['command' => 'make:observer', 'name' => 'OrderObserver'],
            ['command' => 'event:generate'],
        ] as $input) {
            [$status, $output] = computer($this->app, $input);
            expect($status)->toBe(0, $input['command'].': '.$output);
        }

        $files = iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)), '/\.php$/'));
        expect($files)->toHaveCount(8);

        foreach ($files as $file) {
            preg_match_all('/^use (function )?([^; ]+)(?: as \w+)?;/m', $source = file_get_contents($file), $imports, PREG_SET_ORDER);

            foreach ($imports as [, $function, $name]) {
                if (str_starts_with($name, 'App\\')) {
                    continue;
                }

                expect($function ? function_exists($name) : (class_exists($name) || interface_exists($name) || trait_exists($name)))
                    ->toBeTrue(basename($file).' imports missing '.$name);
            }

            expect($source)->not->toMatch('/Illuminate|\{\{/');
        }
    } finally {
        $this->app['files']->deleteDirectory($dir);
    }
});
