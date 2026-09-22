<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Core\RenderedInstance;
use Voyager\Filesystem\DiskGig;
use Voyager\Filesystem\FileGig;
use Voyager\Filesystem\Filesystem;
use Voyager\Filesystem\FilesystemManager;
use Voyager\Filesystem\OffloadedDisk;
use Voyager\Filesystem\OffloadedFiles;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\WorkTargetManager;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/vf-offload-'.getmypid();
    mkdir($this->root, 0777, true);

    $this->app = RenderedInstance::setInstance(new RenderedInstance($this->root));
    $this->app->registerInstance('config', new Repository([
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $this->root]]],
        'io-pools' => ['work' => ['default' => 'sync']],
    ]));
    $this->app->registerInstance(Loop::class, new EventLoop);
    $this->app->registerInstance('files', new Filesystem);
    $this->app->registerInstance('filesystem', new FilesystemManager($this->app));
    $this->app->registerInstance('work-targets', new WorkTargetManager($this->app));
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->root));
    RenderedInstance::setInstance(null);
});

it('a disk gig calls the named method on the named disk', function () {
    (new DiskGig('local', 'put', ['a.txt', 'hello']))->handle();

    expect((new DiskGig('local', 'get', ['a.txt']))->handle())->toBe('hello')
        ->and(file_get_contents($this->root.'/a.txt'))->toBe('hello');
});

it('a file gig calls the native filesystem', function () {
    (new FileGig('put', [$this->root.'/b.txt', 'native']))->handle();

    expect((new FileGig('get', [$this->root.'/b.txt']))->handle())->toBe('native');
});

it('via() hands back a proxy whose calls are promises, and the disk itself stays blocking', function () {
    $disk = app('filesystem')->disk('local');

    $put = $disk->via()->put('c.txt', 'proxied');

    expect($disk->via())->toBeInstanceOf(OffloadedDisk::class)
        ->and($put)->toBeInstanceOf(Promise::class)
        ->and($put->wait())->toBeTrue()
        ->and($disk->get('c.txt'))->toBe('proxied')                      // blocking path sees it
        ->and($disk->via()->exists('c.txt')->wait())->toBeTrue();
});

it('via() names its target', function () {
    $disk = app('filesystem')->disk('local');

    expect($disk->via('defer')->put('d.txt', 'later')->wait())->toBeTrue()
        ->and(fn () => $disk->via('telepathy'))->toThrow(InvalidArgumentException::class);
});

it('the native filesystem offloads too', function () {
    $files = app('files');

    expect($files->via())->toBeInstanceOf(OffloadedFiles::class)
        ->and($files->via()->put($this->root.'/e.txt', 'x')->wait())->toBe(1)
        ->and($files->via()->get($this->root.'/e.txt')->wait())->toBe('x');
});

it('refuses to offload a stream', function () {
    $disk = app('filesystem')->disk('local');

    expect(fn () => $disk->via()->readStream('c.txt'))->toThrow(BadMethodCallException::class, 'cannot cross a worker');
});

it('an on-demand disk cannot be offloaded, because a worker cannot find it by name', function () {
    $disk = app('filesystem')->build(['driver' => 'local', 'root' => $this->root]);

    expect(fn () => $disk->via())->toThrow(LogicException::class, 'not a configured disk');
});
