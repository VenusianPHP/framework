<?php

use Voyager\Contracts\Filesystem\Filesystem;
use Voyager\Filesystem\FilesystemManager;
use Voyager\System\Application;
use League\Flysystem\UnableToReadFile;

test('an exception is thrown on an unsupported driver', function () {
    $filesystem = new FilesystemManager(tap(new Application, function ($app) {
        $app['config'] = ['filesystems.disks.local' => null];
    }));

    $filesystem->disk('local');
})->throws(InvalidArgumentException::class, 'Disk [local] does not have a configured driver.');

test('it can build an on demand disk', function () {
    $filesystem = new FilesystemManager(new Application);

    expect($filesystem->build('my-custom-path'))->toBeInstanceOf(Filesystem::class);

    expect($filesystem->build([
        'driver' => 'local',
        'root' => 'my-custom-path',
        'url' => 'my-custom-url',
        'visibility' => 'public',
    ]))->toBeInstanceOf(Filesystem::class);

    rmdir(__DIR__.'/../../my-custom-path');
});

test('it can build read only disks', function () {
    $filesystem = new FilesystemManager(new Application);

    $disk = $filesystem->build([
        'driver' => 'local',
        'read-only' => true,
        'root' => 'my-custom-path',
        'url' => 'my-custom-url',
        'visibility' => 'public',
    ]);

    file_put_contents(__DIR__.'/../../my-custom-path/path.txt', 'contents');

    // read operations work
    expect($disk->get('path.txt'))->toEqual('contents');
    expect($disk->files())->toEqual(['path.txt']);

    // write operations fail
    expect($disk->put('path.txt', 'contents'))->toBeFalse();
    expect($disk->delete('path.txt'))->toBeFalse();
    expect($disk->deleteDirectory('directory'))->toBeFalse();
    expect($disk->prepend('path.txt', 'data'))->toBeFalse();
    expect($disk->append('path.txt', 'data'))->toBeFalse();
    $handle = fopen('php://memory', 'rw');
    fwrite($handle, 'content');
    expect($disk->writeStream('path.txt', $handle))->toBeFalse();
    fclose($handle);

    unlink(__DIR__.'/../../my-custom-path/path.txt');
    rmdir(__DIR__.'/../../my-custom-path');
});

test('it can build scoped disks', function () {
    try {
        $filesystem = new FilesystemManager(tap(new Application, function ($app) {
            $app['config'] = [
                'filesystems.disks.local' => [
                    'driver' => 'local',
                    'root' => 'to-be-scoped',
                ],
            ];
        }));

        $local = $filesystem->disk('local');
        $scoped = $filesystem->build([
            'driver' => 'scoped',
            'disk' => 'local',
            'prefix' => 'path-prefix',
        ]);

        $scoped->put('dirname/filename.txt', 'file content');
        expect($local->get('path-prefix/dirname/filename.txt'))->toEqual('file content');
        $local->deleteDirectory('path-prefix');
    } finally {
        rmdir(__DIR__.'/../../to-be-scoped');
    }
});

test('it can build a scoped disk from a scoped disk', function () {
    try {
        $filesystem = new FilesystemManager(tap(new Application, function ($app) {
            $app['config'] = [
                'filesystems.disks.local' => [
                    'driver' => 'local',
                    'root' => 'root-to-be-scoped',
                ],
                'filesystems.disks.scoped-from-root' => [
                    'driver' => 'scoped',
                    'disk' => 'local',
                    'prefix' => 'scoped-from-root-prefix',
                ],
            ];
        }));

        $root = $filesystem->disk('local');
        $nestedScoped = $filesystem->build([
            'driver' => 'scoped',
            'disk' => 'scoped-from-root',
            'prefix' => 'nested-scoped-prefix',
        ]);

        $nestedScoped->put('dirname/filename.txt', 'file content');
        expect($root->get('scoped-from-root-prefix/nested-scoped-prefix/dirname/filename.txt'))->toEqual('file content');
        $root->deleteDirectory('scoped-from-root-prefix');
    } finally {
        rmdir(__DIR__.'/../../root-to-be-scoped');
    }
});

test('it can build scoped disks with visibility', function () {
    try {
        $filesystem = new FilesystemManager(tap(new Application, function ($app) {
            $app['config'] = [
                'filesystems.disks.local' => [
                    'driver' => 'local',
                    'root' => 'to-be-scoped',
                    'visibility' => 'public',
                ],
            ];
        }));

        $scoped = $filesystem->build([
            'driver' => 'scoped',
            'disk' => 'local',
            'prefix' => 'path-prefix',
            'visibility' => 'private',
        ]);

        $scoped->put('dirname/filename.txt', 'file content');

        expect($scoped->getVisibility('dirname/filename.txt'))->toEqual('private');
    } finally {
        unlink(__DIR__.'/../../to-be-scoped/path-prefix/dirname/filename.txt');
        rmdir(__DIR__.'/../../to-be-scoped/path-prefix/dirname');
        rmdir(__DIR__.'/../../to-be-scoped/path-prefix');
        rmdir(__DIR__.'/../../to-be-scoped');
    }
})->skip(fn () => ! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'], true), 'Requires Linux or Darwin.');

test('it can build scoped disks with throw', function () {
    set_error_handler(static fn (): bool => true);

    try {
        $filesystem = new FilesystemManager(tap(new Application, function ($app) {
            $app['config'] = [
                'filesystems.disks.local' => [
                    'driver' => 'local',
                    'root' => 'to-be-scoped',
                    'throw' => false,
                ],
            ];
        }));

        $scoped = $filesystem->build([
            'driver' => 'scoped',
            'disk' => 'local',
            'prefix' => 'path-prefix',
            'throw' => true,
        ]);

        $scoped->get('dirname/filename.txt');
    } finally {
        restore_error_handler();
        if (is_dir(__DIR__.'/../../to-be-scoped')) {
            rmdir(__DIR__.'/../../to-be-scoped');
        }
    }
})->throws(UnableToReadFile::class);

test('it can build inline scoped disks', function () {
    try {
        $filesystem = new FilesystemManager(new Application);

        $scoped = $filesystem->build([
            'driver' => 'scoped',
            'disk' => [
                'driver' => 'local',
                'root' => 'to-be-scoped',
            ],
            'prefix' => 'path-prefix',
        ]);

        $scoped->put('dirname/filename.txt', 'file content');
        expect(is_dir(__DIR__.'/../../to-be-scoped/path-prefix'))->toBeTrue();
        expect(file_get_contents(__DIR__.'/../../to-be-scoped/path-prefix/dirname/filename.txt'))->toEqual('file content');
    } finally {
        unlink(__DIR__.'/../../to-be-scoped/path-prefix/dirname/filename.txt');
        rmdir(__DIR__.'/../../to-be-scoped/path-prefix/dirname');
        rmdir(__DIR__.'/../../to-be-scoped/path-prefix');
        rmdir(__DIR__.'/../../to-be-scoped');
    }
});

// test('it keeps track of adapter decoration', function () {
//     try {
//         $filesystem = new FilesystemManager(tap(new Application, function ($app) {
//             $app['config'] = [
//                 'filesystems.disks.local' => [
//                     'driver' => 'local',
//                     'root' => 'to-be-scoped',
//                 ],
//             ];
//         }));
//
//         $scoped = $filesystem->build([
//             'driver' => 'scoped',
//             'disk' => 'local',
//             'prefix' => 'path-prefix',
//         ]);
//
//         expect($scoped->getAdapter())->toBeInstanceOf(PathPrefixedAdapter::class);
//     } finally {
//         rmdir(__DIR__.'/../../to-be-scoped');
//     }
// });
