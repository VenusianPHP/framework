<?php

use Voyager\Config\Repository as Config;
use Voyager\Filesystem\Filesystem;
use Voyager\System\Application;
use Voyager\System\Console\SketchMakeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function destroyTempSketchApp(string $path): void
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

test('make:sketch writes a class under app/Runner/Sketches extending the app base', function () {
    $basePath = sys_get_temp_dir().'/venusian-make-sketch-'.uniqid();
    mkdir($basePath.'/app/Runner/Sketches', 0777, true);

    file_put_contents($basePath.'/composer.json', json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    try {
        $app = new Application($basePath);
        $app->instance('env', 'testing');
        $app->instance('files', new Filesystem);
        $app->instance('config', new Config);

        $command = new SketchMakeCommand($app['files']);
        $command->setVenusian($app);

        $status = $command->run(new ArrayInput(['name' => 'HelloWorld']), new NullOutput);

        $path = $basePath.'/app/Runner/Sketches/HelloWorld.php';

        expect($status)->toBe(0)
            ->and(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)->toContain('namespace App\\Runner\\Sketches;')
            ->and($contents)->toContain('class HelloWorld extends Sketch')
            ->and($contents)->toContain('use Voyager\\Contracts\\Sketches\\SketchLoopResult;');
    } finally {
        destroyTempSketchApp($basePath);
    }
});
