<?php

use Voyager\Filesystem\FileChunk;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Filesystem\OffloadedDisk;
use Voyager\Filesystem\OffloadedFiles;
use Voyager\Filesystem\Offloading\FilesystemCall;
use Voyager\Contracts\IOPools\WorkerPools\RemoteException;
use Symfony\Component\Finder\SplFileInfo;
use Venusian\Tests\Log\Fixtures\RecordingPool;
use Venusian\Tests\Filesystem\Fixtures\FilesApp;

beforeEach(fn () => $this->app = FilesApp::boot());
afterEach(fn () => FilesApp::tearDown($this->app, $this));

/** Runs the loop until $count chunks of $name have been dispatched, or five seconds pass. */
function chunks(object $test, string $name, int $count, ?Promise $also = null): array
{
    $heard = [];
    $loop = $test->app->get(Loop::class);

    // Stopping the loop shuts the pool down, so an offloaded call still out would be cut off:
    // the run ends once the chunks are in and $also has settled.
    $done = fn (): bool => count($heard) >= $count && (is_null($also) || $also->settled());

    $test->app->get('signals')->listen($name, function (FileChunk $chunk) use (&$heard, $done, $loop) {
        $heard[] = $chunk;

        if ($done()) {
            $loop->stop();
        }
    });

    $also?->finally(function () use ($done, $loop) {
        if ($done()) {
            $loop->stop();
        }
    });

    $loop->at(5, fn () => $loop->stop());
    $loop->run();

    return $heard;
}

it('answers local filesystem calls from a worker, as the blocking calls would', function () {
    $root = $this->app['test.root'];
    $files = $this->app['files']->via();

    expect($files)->toBeInstanceOf(OffloadedFiles::class)
        ->and($files->makeDirectory("{$root}/notes", 0755, true)->wait())->toBeTrue()
        ->and($files->put("{$root}/notes/a.txt", 'alpha')->wait())->toBe(5)
        ->and($files->append("{$root}/notes/a.txt", '!')->wait())->toBe(1)
        ->and($files->get("{$root}/notes/a.txt")->wait())->toBe('alpha!')
        ->and($files->readRange("{$root}/notes/a.txt", 1, 3)->wait())->toBe('lph')
        ->and($files->hash("{$root}/notes/a.txt", 'sha1')->wait())->toBe(sha1('alpha!'))
        ->and($files->copy("{$root}/notes/a.txt", "{$root}/notes/b.txt")->wait())->toBeTrue()
        ->and($files->move("{$root}/notes/b.txt", "{$root}/notes/c.txt")->wait())->toBeTrue()
        ->and($files->glob("{$root}/notes/*.txt")->wait())->toBe(["{$root}/notes/a.txt", "{$root}/notes/c.txt"])
        ->and($files->delete("{$root}/notes/c.txt")->wait())->toBeTrue()
        ->and($files->exists("{$root}/notes/c.txt")->wait())->toBeFalse()
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
});

it('hands back the files a worker found as Finder\'s SplFileInfo', function () {
    $root = $this->app['test.root'];
    $this->app['files']->ensureDirectoryExists("{$root}/found/deeper");
    file_put_contents("{$root}/found/one.txt", '1');
    file_put_contents("{$root}/found/deeper/two.txt", '2');

    $found = $this->app['files']->via()->allFiles("{$root}/found")->wait();

    expect($found)->each->toBeInstanceOf(SplFileInfo::class)
        ->and(array_map(fn (SplFileInfo $file) => $file->getRelativePathname(), $found))->toBe(['deeper/two.txt', 'one.txt'])
        ->and($found[1]->getContents())->toBe('1');
});

it('answers disk calls from a worker, on the disk it builds from this one\'s config', function () {
    $disk = storage()->disk('local');
    $offloaded = $disk->via();

    expect($offloaded)->toBeInstanceOf(OffloadedDisk::class)
        ->and($offloaded->put('docs/readme.md', '# hello')->wait())->toBeTrue()
        ->and($offloaded->append('docs/readme.md', 'more')->wait())->toBeTrue()
        ->and($offloaded->get('docs/readme.md')->wait())->toBe("# hello\nmore")
        ->and($offloaded->size('docs/readme.md')->wait())->toBe(12)
        ->and($offloaded->checksum('docs/readme.md')->wait())->toBe($disk->checksum('docs/readme.md'))
        ->and($offloaded->copy('docs/readme.md', 'docs/copy.md')->wait())->toBeTrue()
        ->and($offloaded->files('docs')->wait())->toBe(['docs/copy.md', 'docs/readme.md'])
        ->and($offloaded->delete(['docs/copy.md'])->wait())->toBeTrue()
        ->and($offloaded->exists('docs/copy.md')->wait())->toBeFalse()
        ->and($disk->get('docs/readme.md'))->toBe("# hello\nmore");
});

it('has the worker open a file on this machine itself for putFile', function () {
    $source = $this->app['test.root'].'/upload.json';
    file_put_contents($source, '{"a":1}');

    $stored = storage()->disk('local')->via()->putFile('uploads', $source)->wait();

    expect($stored)->toMatch('#^uploads/[A-Za-z0-9]{40}\.json$#')
        ->and(storage()->disk('local')->get($stored))->toBe('{"a":1}');
});

it('completes one path\'s calls in the order they were made, however many workers there are', function () {
    $path = $this->app['test.root'].'/ordered.txt';
    $files = $this->app['files']->via();

    $files->put($path, '');
    $appends = array_map(fn (int $n): Promise => $files->append($path, "{$n},"), range(1, 20));
    $read = $files->get($path);

    expect($read->wait())->toBe(implode(',', range(1, 20)).',');
    array_map(fn (Promise $append) => $append->wait(), $appends);
});

it('makes a blocking call on a path wait for the offloaded calls made on it', function () {
    $path = $this->app['test.root'].'/handed-over.txt';

    $this->app['files']->via()->put($path, 'offloaded write');
    storage()->disk('local')->via()->put('handed-over.txt', 'offloaded disk write');

    expect($this->app['files']->get($path))->toBe('offloaded write')
        ->and(storage()->disk('local')->get('handed-over.txt'))->toBe('offloaded disk write');
});

it('rejects with the worker\'s exception when the blocking call would throw', function () {
    $missing = $this->app['test.root'].'/missing.txt';

    expect(fn () => $this->app['files']->via()->get($missing)->wait())
        ->toThrow(RemoteException::class, "File does not exist at path {$missing}.");
});

it('refuses arguments that aren\'t data before anything is sent', function () {
    $refused = $this->app['files']->via()->delete([new ArrayObject()]);

    expect(fn () => $refused->wait())
        ->toThrow(InvalidArgumentException::class, "delete() can't run in a worker: its arguments must be strings, numbers, booleans, null or arrays of them, and ArrayObject was given.")
        ->and($this->app->get('process-workers')->workerCount())->toBe(0);
});

it('refuses to offload a disk whose config can\'t cross to a worker', function () {
    $disk = storage()->build(['driver' => 'local', 'root' => $this->app['test.root'], 'resolver' => fn () => null]);

    expect(fn () => $disk->via())
        ->toThrow(InvalidArgumentException::class, "This disk can't be offloaded: a worker builds it from its config, and the config holds something that can't cross (Serialization of 'Closure' is not allowed).");
});

it('offloads to the thread pool when it is on, and to the pool named', function () {
    $threads = new RecordingPool($this->app->get(Loop::class));
    $this->app->registerInstance('thread-workers', $threads);
    $path = $this->app['test.root'].'/threaded.txt';

    $this->app['files']->via()->put($path, 'on a thread')->wait();
    $this->app['files']->via('process')->put($path, 'in a process')->wait();

    expect($threads->gigs)->toHaveCount(1)
        ->and($threads->gigs[0])->toBeInstanceOf(FilesystemCall::class)
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0)
        ->and(file_get_contents($path))->toBe('in a process');
});

it('says which pool is missing', function () {
    expect(fn () => $this->app['files']->via('thread'))
        ->toThrow(InvalidArgumentException::class, 'The thread pool is off: enable io-pools.pool_workers.threads to offload to it.')
        ->and(fn () => $this->app['files']->via('gpu'))
        ->toThrow(InvalidArgumentException::class, 'There is no "gpu" pool: offload to \'thread\' or \'process\', or name none for the thread pool when it is on and the process pool otherwise.');
});

it('runs offloaded calls and streams on a real thread pool', function () {
    $pool = new Voyager\IOPools\WorkerPools\Thread\ThreadWorkerPool(
        $this->app->get(Loop::class), 2, dirname(__DIR__, 2), Voyager\IOPools\WorkerPools\WorkerPoolManager::autoloader(),
    );
    $this->app->registerInstance('thread-workers', $pool);
    $path = $this->app['test.root'].'/threaded.txt';

    $files = $this->app['files']->via('thread');
    $files->put($path, 'written on a thread')->wait();

    // Counted before the loop runs: a pool stops its workers when run() ends.
    expect($pool->workerCount())->toBeGreaterThan(0)
        ->and($this->app->get('process-workers')->workerCount())->toBe(0);

    $read = $files->stream($path, 8);
    $heard = chunks($this, "file:{$path}", 3);

    expect(implode('', array_map(fn (FileChunk $chunk) => $chunk->bytes, $heard)))->toBe('written on a thread')
        ->and($read->wait())->toBe(19);
})->skip(! PHP_ZTS || ! extension_loaded('parallel'), 'needs a ZTS build with ext-parallel');

it('streams a file as chunk mail, in order, the last one marked', function () {
    $path = $this->app['test.root'].'/large.bin';
    $contents = random_bytes(2_500_000);
    file_put_contents($path, $contents);

    $read = $this->app['files']->via()->stream($path, 1_048_576);
    $heard = chunks($this, "file:{$path}", 3);

    expect(array_map(fn (FileChunk $chunk) => [$chunk->offset, strlen($chunk->bytes), $chunk->last], $heard))
        ->toBe([[0, 1_048_576, false], [1_048_576, 1_048_576, false], [2_097_152, 402_848, true]])
        ->and(implode('', array_map(fn (FileChunk $chunk) => $chunk->bytes, $heard)) === $contents)->toBeTrue()
        ->and($read->wait())->toBe(2_500_000);
});

it('streams a disk file under the disk\'s name, and an empty file as one last chunk', function () {
    storage()->disk('local')->put('empty.txt', '');

    $read = storage()->disk('local')->via()->stream('empty.txt');
    $heard = chunks($this, 'disk:local:empty.txt', 1);

    expect($heard)->toHaveCount(1)
        ->and($heard[0]->bytes)->toBe('')
        ->and($heard[0]->last)->toBeTrue()
        ->and($read->wait())->toBe(0);
});

it('holds the path while it streams: a write made meanwhile lands after the last chunk', function () {
    $path = $this->app['test.root'].'/steady.txt';
    file_put_contents($path, str_repeat('a', 30));
    $files = $this->app['files']->via();

    $files->stream($path, 10);
    $put = $files->put($path, str_repeat('b', 30));
    $heard = chunks($this, "file:{$path}", 3, also: $put);

    expect(implode('', array_map(fn (FileChunk $chunk) => $chunk->bytes, $heard)))->toBe(str_repeat('a', 30))
        ->and($put->fulfilled())->toBeTrue()
        ->and($this->app['files']->get($path))->toBe(str_repeat('b', 30));
});

it('offloads the default disk from Storage, faked or not', function () {
    storage()->fake('local');

    storage()->via()->put('faked.txt', 'into the fake')->wait();

    expect(storage()->disk('local')->get('faked.txt'))->toBe('into the fake')
        ->and(file_exists($this->app->storagePath('framework/testing/disks/local/faked.txt')))->toBeTrue();
});
