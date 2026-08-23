<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Voyager\Events\Dispatcher;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\ServiceProvider;
use Tests\System\Stubs\VendorPublishCommandTestProvider;
use Voyager\System\Application;
use Voyager\System\Console\VendorPublishCommand;
use Voyager\System\Events\VendorTagPublished;

function destroyTempVendorPublishApp(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($path);
}

afterEach(function () {
    ServiceProvider::$publishes = [];
    ServiceProvider::$publishGroups = [];
});

test('VendorTagPublished accepts a null tag when publishing by provider', function () {
    $paths = ['/from/config.php' => '/to/config.php'];

    $event = new VendorTagPublished(null, $paths);

    expect($event->tag)->toBeNull()
        ->and($event->paths)->toBe($paths);
});

test('vendor:publish --provider copies files and dispatches VendorTagPublished with a null tag', function () {
    $basePath = sys_get_temp_dir().'/venusian-vendor-publish-'.uniqid();
    mkdir($basePath.'/src', 0777, true);
    mkdir($basePath.'/config', 0777, true);
    file_put_contents($basePath.'/src/windows.php', "<?php\n\nreturn [];\n");

    $app = new Application($basePath);
    $app->instance('env', 'testing');
    $app->instance('files', new Filesystem);
    $events = new Dispatcher($app);
    $app->instance('events', $events);

    $dispatched = [];
    $events->listen(VendorTagPublished::class, function (VendorTagPublished $event) use (&$dispatched) {
        $dispatched[] = $event;
    });

    (new VendorPublishCommandTestProvider($app))->boot();

    $command = new VendorPublishCommand($app['files']);
    $command->setVenusian($app);

    try {
        $status = $command->run(new ArrayInput([
            '--provider' => VendorPublishCommandTestProvider::class,
        ]), new NullOutput);

        $published = $app->configPath('windows.php');

        expect($status)->toBe(0)
            ->and(file_exists($published))->toBeTrue()
            ->and($dispatched)->toHaveCount(1)
            ->and($dispatched[0]->tag)->toBeNull()
            ->and($dispatched[0]->paths)->toHaveKey($basePath.'/src/windows.php');
    } finally {
        destroyTempVendorPublishApp($basePath);
    }
});
