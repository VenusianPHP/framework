<?php

use Voyager\Filesystem\Storage;
use Voyager\Filesystem\Filesystem;
use Voyager\Filesystem\FilesystemAdapter;
use Voyager\Filesystem\FilesystemManager;
use Venusian\Tests\Filesystem\Fixtures\FilesApp;

beforeEach(fn () => $this->app = FilesApp::boot());
afterEach(fn () => FilesApp::tearDown($this->app, $this));

it('binds the local filesystem, the manager, the default disk and Storage', function () {
    expect($this->app['files'])->toBeInstanceOf(Filesystem::class)
        ->and($this->app['filesystem'])->toBeInstanceOf(FilesystemManager::class)
        ->and($this->app['filesystem.disk'])->toBeInstanceOf(FilesystemAdapter::class)
        ->and(storage())->toBeInstanceOf(Storage::class)
        ->and(storage())->toBe($this->app->get(Storage::class));
});

it('writes, reads, lists, copies, moves and deletes local files', function () {
    $files = $this->app['files'];
    $root = $this->app['test.root'];
    $files->ensureDirectoryExists("{$root}/notes");

    expect($files->put("{$root}/notes/a.txt", 'alpha'))->toBe(5)
        ->and($files->get("{$root}/notes/a.txt"))->toBe('alpha')
        ->and($files->append("{$root}/notes/a.txt", '!'))->toBe(1)
        ->and($files->readRange("{$root}/notes/a.txt", 1, 3))->toBe('lph')
        ->and($files->copy("{$root}/notes/a.txt", "{$root}/notes/b.txt"))->toBeTrue()
        ->and($files->move("{$root}/notes/b.txt", "{$root}/notes/c.txt"))->toBeTrue()
        ->and(array_map(fn ($file) => $file->getFilename(), $files->files("{$root}/notes")))->toBe(['a.txt', 'c.txt'])
        ->and($files->delete("{$root}/notes/c.txt"))->toBeTrue()
        ->and($files->exists("{$root}/notes/c.txt"))->toBeFalse();
});

it('writes, reads and lists through the local disk', function () {
    $disk = storage()->disk('local');

    expect($disk->put('docs/readme.md', '# hello'))->toBeTrue()
        ->and($disk->get('docs/readme.md'))->toBe('# hello')
        ->and($disk->exists('docs/readme.md'))->toBeTrue()
        ->and($disk->readRange('docs/readme.md', 2, 5))->toBe('hello')
        ->and($disk->files('docs'))->toBe(['docs/readme.md'])
        ->and($disk->path('docs/readme.md'))->toBe($this->app['test.root'].'/local/docs/readme.md');
});

it('stores a file on this machine under a random name with its extension', function () {
    $source = $this->app['test.root'].'/upload.json';
    file_put_contents($source, '{"a":1}');

    $stored = storage()->disk('local')->putFile('uploads', $source);

    expect($stored)->toMatch('#^uploads/[A-Za-z0-9]{40}\.json$#')
        ->and(storage()->disk('local')->get($stored))->toBe('{"a":1}');
});

it('calls the default disk\'s operations on Storage itself', function () {
    storage()->put('direct.txt', 'on the default disk');

    expect(storage()->get('direct.txt'))->toBe('on the default disk')
        ->and(storage()->disk()->get('direct.txt'))->toBe('on the default disk');
});

it('fakes a disk with an empty one under storage/framework/testing', function () {
    storage()->disk('local')->put('real.txt', 'kept');

    $fake = storage()->fake('local');
    $fake->put('fake.txt', 'faked');

    expect(storage()->disk('local'))->toBe($fake)
        ->and(storage()->disk('local')->exists('real.txt'))->toBeFalse()
        ->and($fake->path('fake.txt'))->toBe($this->app->storagePath('framework/testing/disks/local/fake.txt'))
        ->and(storage()->fake('local')->exists('fake.txt'))->toBeFalse()
        ->and(storage()->persistentFake('local')->exists('fake.txt'))->toBeFalse();
});
