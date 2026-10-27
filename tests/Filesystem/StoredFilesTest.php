<?php

use Voyager\Filesystem\File;
use Voyager\Filesystem\UploadedFile;
use Venusian\Tests\Filesystem\Fixtures\FilesApp;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

beforeEach(fn () => $this->app = FilesApp::boot());
afterEach(fn () => FilesApp::tearDown($this->app, $this));

/** A file on this machine holding $contents, named $name. */
function localFile(string $name, string $contents): string
{
    $path = test()->app['test.root'].'/'.$name;
    file_put_contents($path, $contents);

    return $path;
}

it('stores a File, an UploadedFile and a path under a hashed name with the right extension', function (File|UploadedFile|string $file) {
    $disk = storage()->disk('local');

    $stored = $disk->putFile('uploads', $file);

    expect($stored)->toMatch('#^uploads/[A-Za-z0-9]{40}\.json$#')
        ->and($disk->get($stored))->toBe('{"a":1}');
})->with([
    'File' => fn () => new File(localFile('data.json', '{"a":1}')),
    'UploadedFile' => fn () => UploadedFile::fake()->createWithContent('data.json', '{"a":1}'),
    'path' => fn () => localFile('data.json', '{"a":1}'),
]);

it('stores a file under the name it is given', function () {
    $disk = storage()->disk('local');

    expect($disk->putFileAs('uploads', new File(localFile('data.json', 'x')), 'named.json'))->toBe('uploads/named.json')
        ->and($disk->get('uploads/named.json'))->toBe('x');
});

it('routes a File handed to put() through putFile()', function () {
    $disk = storage()->disk('local');

    $stored = $disk->put('uploads', new File(localFile('data.json', 'routed')));

    expect($stored)->toStartWith('uploads/')
        ->and($disk->get($stored))->toBe('routed');
});

it('stores an uploaded file on a named disk through the container', function () {
    $file = UploadedFile::fake()->createWithContent('photo.txt', 'pixels');

    $stored = $file->store('avatars', 'public');
    $named = $file->storeAs('avatars', 'me.txt', ['disk' => 'public']);

    expect(storage()->disk('public')->get($stored))->toBe('pixels')
        ->and($named)->toBe('avatars/me.txt')
        ->and(storage()->disk('public')->get('avatars/me.txt'))->toBe('pixels');
});

it('takes a Symfony uploaded file as its own', function () {
    $base = new SymfonyUploadedFile(localFile('report.txt', 'r'), 'report.txt', 'text/plain', null, true);

    $file = UploadedFile::createFromBase($base, true);

    expect($file)->toBeInstanceOf(UploadedFile::class)
        ->and($file->getClientOriginalName())->toBe('report.txt')
        ->and($file->get())->toBe('r')
        ->and(UploadedFile::createFromBase($file))->toBe($file);
});
