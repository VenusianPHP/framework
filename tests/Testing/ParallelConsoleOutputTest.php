<?php

use Voyager\Testing\ParallelConsoleOutput;
use Symfony\Component\Console\Output\BufferedOutput;

test('write', function () {
    $original = new BufferedOutput;
    $output = new ParallelConsoleOutput($original);

    $output->write('Running phpunit in 12 processes with laravel/laravel.');
    expect($original->fetch())->toBeEmpty();

    $output->write('Configuration read from phpunit.xml.dist');
    expect($original->fetch())->toBeEmpty();

    $output->write('... 3/3 (100%)');
    expect($original->fetch())->toBe('... 3/3 (100%)');
});
