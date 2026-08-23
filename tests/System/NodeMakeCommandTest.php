<?php

use Voyager\Config\Repository as Config;
use Voyager\Filesystem\Filesystem;
use Voyager\System\Application;
use Voyager\System\Console\NodeMakeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function destroyTempNodeApp(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($path);
}

function makeTempNodeApp(): Application
{
    $basePath = sys_get_temp_dir().'/venusian-make-node-'.uniqid();
    mkdir($basePath.'/app/Workflows', 0777, true);

    file_put_contents($basePath.'/composer.json', json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    $app = new Application($basePath);
    $app->instance('env', 'testing');
    $app->instance('files', new Filesystem);
    $app->instance('config', new Config);

    return $app;
}

test('make:node writes a Node under app/Workflows', function () {
    $app = makeTempNodeApp();

    try {
        $command = new NodeMakeCommand($app['files']);
        $command->setVenusian($app);

        $status = $command->run(new ArrayInput(['name' => 'ReadSensor']), new NullOutput);

        $path = $app->basePath('app/Workflows/ReadSensor.php');

        expect($status)->toBe(0)
            ->and(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)->toContain('namespace App\\Workflows;')
            ->and($contents)->toContain('class ReadSensor extends Node')
            ->and($contents)->toContain('use Voyager\\Workflows\\Node;')
            ->and($contents)->toContain('use Voyager\\Workflows\\SharedBag;')
            ->and($contents)->toContain('public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string');
    } finally {
        destroyTempNodeApp($app->basePath());
    }
});

test('make:node --async writes an AsyncNode under app/Workflows', function () {
    $app = makeTempNodeApp();

    try {
        $command = new NodeMakeCommand($app['files']);
        $command->setVenusian($app);

        $status = $command->run(new ArrayInput(['name' => 'ReadSensor', '--async' => true]), new NullOutput);

        $path = $app->basePath('app/Workflows/ReadSensor.php');

        expect($status)->toBe(0)
            ->and(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)->toContain('class ReadSensor extends AsyncNode')
            ->and($contents)->toContain('use Voyager\\Workflows\\AsyncNode;')
            ->and($contents)->toContain('public function execAsync(mixed $prepRes): mixed');
    } finally {
        destroyTempNodeApp($app->basePath());
    }
});
