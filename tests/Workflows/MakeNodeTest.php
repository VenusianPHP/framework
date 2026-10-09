<?php

use Voyager\Workflows\Node;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;
use Voyager\Filesystem\Filesystem;
use Voyager\Contracts\Console\Kernel;
use Venusian\Tests\Log\Fixtures\LogApp;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

// The app directory is a fresh one under App\, and each class name is new, so a
// generated class never collides with another test's.
beforeEach(function () {
    $this->app = LogApp::boot();
    $this->root = sys_get_temp_dir().'/venusian-make-node-'.bin2hex(random_bytes(6));
    $this->suffix = bin2hex(random_bytes(4));
    mkdir($this->root);
    $this->app->useAppPath($this->root);
    (new ReflectionProperty($this->app, 'namespace'))->setValue($this->app, 'App\\');

    $this->make = function (string ...$arguments): array {
        $output = new BufferedOutput();
        $status = $this->app->make(Kernel::class)->handle(new ArgvInput(['computer', 'make:node', ...$arguments]), $output);

        return [$status, $output->fetch()];
    };
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
    LogApp::tearDown($this->app, $this);
});

it('writes a Node into App\Workflows that runs as written', function () {
    [$status, $output] = ($this->make)('FetchApod'.$this->suffix);

    $path = $this->root.'/Workflows/FetchApod'.$this->suffix.'.php';
    require $path;
    $class = 'App\\Workflows\\FetchApod'.$this->suffix;

    expect($status)->toBe(0)
        ->and($output)->toContain('created successfully')
        ->and(get_parent_class($class))->toBe(Node::class)
        ->and((new $class)->run(new SharedBag()))->toBeNull();
});

it('writes an AsyncNode with --async, in a subdirectory when the name has one', function () {
    [$status] = ($this->make)('Apod/Download'.$this->suffix, '--async');

    require $this->root.'/Workflows/Apod/Download'.$this->suffix.'.php';
    $class = 'App\\Workflows\\Apod\\Download'.$this->suffix;

    expect($status)->toBe(0)
        ->and(get_parent_class($class))->toBe(AsyncNode::class)
        ->and((new $class)->runAsync(new SharedBag()))->toBeNull();
});

it('leaves an existing node untouched unless forced', function () {
    ($this->make)('Blink'.$this->suffix);
    file_put_contents($path = $this->root.'/Workflows/Blink'.$this->suffix.'.php', 'edited');

    [, $output] = ($this->make)('Blink'.$this->suffix, '--async');
    $kept = file_get_contents($path);
    ($this->make)('Blink'.$this->suffix, '--async', '--force');

    expect($output)->toContain('Node already exists.')
        ->and($kept)->toBe('edited')
        ->and(file_get_contents($path))->toContain('extends AsyncNode');
});
