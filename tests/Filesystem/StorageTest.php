<?php

use Voyager\Config\Repository;
use Voyager\Contracts\Filesystem\Filesystem as FilesystemContract;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Core\RenderedInstance;
use Voyager\Filesystem\Filesystem;
use Voyager\Filesystem\FilesystemManager;
use Voyager\Filesystem\OffloadedDisk;
use Voyager\Filesystem\Storage;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\WorkTargetManager;

enum Disk: string { case Other = 'other'; }

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/vf-storage-'.getmypid();
    mkdir($this->root.'/app', 0777, true);

    $this->app = RenderedInstance::setInstance(new RenderedInstance($this->root));
    $this->app->registerInstance('config', new Repository([
        'filesystems' => ['default' => 'local', 'disks' => [
            'local' => ['driver' => 'local', 'root' => $this->root.'/app'],
            'other' => ['driver' => 'local', 'root' => $this->root.'/other'],
        ]],
        'io-pools' => ['work' => ['default' => 'sync']],
    ]));
    $this->app->registerInstance(Loop::class, new EventLoop);
    $this->app->registerInstance('files', new Filesystem);
    $this->app->registerInstance('filesystem', $manager = new FilesystemManager($this->app));
    $this->app->registerInstance('work-targets', $targets = new WorkTargetManager($this->app));
    $this->app->registerInstance(Storage::class, new Storage($manager, $targets));
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->root));
    RenderedInstance::setInstance(null);
});

it('is reachable through storage() and app()', function () {
    expect(storage())->toBeInstanceOf(Storage::class)->and(storage())->toBe(app(Storage::class));
});

it('delegates the default disk', function () {
    expect(storage()->put('a.txt', 'one'))->toBeTrue()
        ->and(storage()->get('a.txt'))->toBe('one')
        ->and(storage()->exists('a.txt'))->toBeTrue()
        ->and(file_exists($this->root.'/app/a.txt'))->toBeTrue()
        ->and(storage()->delete('a.txt'))->toBeTrue();
});

it('names a disk, by string or enum', function () {
    storage()->disk(Disk::Other)->put('b.txt', 'two');

    expect(storage()->disk('other')->get('b.txt'))->toBe('two')
        ->and(storage()->disk('other'))->toBeInstanceOf(FilesystemContract::class);
});

it('offloads through via()', function () {
    $put = storage()->via('sync')->put('c.txt', 'three');

    expect(storage()->via())->toBeInstanceOf(OffloadedDisk::class)
        ->and($put)->toBeInstanceOf(Promise::class)
        ->and($put->wait())->toBeTrue()
        ->and(storage()->via('sync', 'other')->exists('c.txt')->wait())->toBeFalse();
});

it('fake() swaps the disk under a testing root and cleans it', function () {
    mkdir($this->root.'/storage/framework/testing/disks/local', 0777, true);
    file_put_contents($stale = $this->root.'/storage/framework/testing/disks/local/stale.txt', 'x');

    $fake = storage()->fake();
    $fake->put('d.txt', 'faked');

    expect(storage()->disk('local'))->toBe($fake)
        ->and(file_exists($this->root.'/storage/framework/testing/disks/local/d.txt'))->toBeTrue()
        ->and(file_exists($stale))->toBeFalse()                                       // cleaned on fake()
        ->and(file_exists($this->root.'/app/d.txt'))->toBeFalse();                    // the real root untouched
});

it('persistentFake() keeps what was there', function () {
    mkdir($this->root.'/storage/framework/testing/disks/local', 0777, true);
    file_put_contents($this->root.'/storage/framework/testing/disks/local/keep.txt', 'k');

    storage()->persistentFake();

    expect(storage()->exists('keep.txt'))->toBeTrue();
});

it('build() makes an on-demand disk', function () {
    $disk = storage()->build(['driver' => 'local', 'root' => $this->root.'/adhoc']);
    $disk->put('e.txt', 'x');

    expect(file_exists($this->root.'/adhoc/e.txt'))->toBeTrue();
});
