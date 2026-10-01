<?php

use Voyager\Contracts\IOPools\WakeReason;
use Voyager\IOPools\Waiter\Wakes\Readable;
use Voyager\IOPools\Waiter\Wakes\Writable;
use Voyager\IOPools\Waiter\Wakes\FileChange;
use Voyager\IOPools\Waiter\Wakes\ProcessExit;
use Voyager\IOPools\Waiter\Wakes\ControlSignal;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;

/** Every backend this machine can load. */
dataset('backends', function () {
    yield 'select' => [fn () => new StreamSelectWaiterBackend()];

    if (extension_loaded('kqueue')) {
        yield 'kqueue' => [fn () => new KqueueWaiterBackend()];
    }

    if (extension_loaded('epoll')) {
        yield 'epoll' => [fn () => new EpollWaiterBackend()];
    }
});

/** @return array{0: resource, 1: resource} */
function streamPair(): array
{
    return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
}

const SECOND_NS = 1_000_000_000;

it('fires a readable stream once data arrives, and not before', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('reader', $wake = new Readable($a));

    expect($backend->wait(0))->toBe([]);

    fwrite($b, 'x');

    expect($backend->wait(SECOND_NS))->toBe(['reader' => [$wake]]);
})->with('backends');

it('fires a writable stream', function ($backend) {
    [$a] = streamPair();
    $backend->add('writer', $wake = new Writable($a));

    expect($backend->wait(SECOND_NS))->toBe(['writer' => [$wake]]);
})->with('backends');

it('fires every owner watching the same stream', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('first', $first = new Readable($a));
    $backend->add('second', $second = new Readable($a));

    fwrite($b, 'x');

    expect($backend->wait(SECOND_NS))->toBe(['first' => [$first], 'second' => [$second]]);
})->with('backends');

it('keeps firing the owners that remain after one leaves', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('first', $first = new Readable($a));
    $backend->add('second', $second = new Readable($a));
    $backend->remove('first', $first);

    fwrite($b, 'x');

    expect($backend->wait(SECOND_NS))->toBe(['second' => [$second]]);
})->with('backends');

it('reports reading and writing on one stream separately', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('duplex', $read = new Readable($a));
    $backend->add('duplex', $write = new Writable($a));

    expect($backend->wait(SECOND_NS))->toBe(['duplex' => [$write]]);

    fwrite($b, 'x');

    expect($backend->wait(SECOND_NS)['duplex'])->toEqualCanonicalizing([$read, $write]);
})->with('backends');

it('stops firing a stream once its wake is removed', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('reader', $wake = new Readable($a));
    $backend->remove('reader', $wake);

    fwrite($b, 'x');

    expect($backend->wait(0))->toBe([]);
})->with('backends');

it('stops watching an open stream once its last wake is removed, so the wait sleeps', function ($backend) {
    [$a] = streamPair();
    $backend->add('writer', $wake = new Writable($a));
    $backend->remove('writer', $wake);

    // A writable socket fires on every wait while the kernel still watches it.
    $started = hrtime(true);

    expect($backend->wait(50_000_000))->toBe([])
        ->and(hrtime(true) - $started)->toBeGreaterThan(40_000_000);
})->with('backends');

it('fires on bytes PHP already buffered even though the kernel has none left', function ($backend) {
    [$a, $b] = streamPair();
    fwrite($b, "one\ntwo\n");
    $backend->add('reader', $wake = new Readable($a));

    // fgets pulls both lines into PHP's buffer and hands back the first.
    expect(fgets($a))->toBe("one\n")
        ->and($backend->wait(0))->toBe(['reader' => [$wake]]);
})->with('backends');

it('returns nothing once the timeout passes', function ($backend) {
    // Both ends stay referenced: a freed peer closes, and end-of-file reads as readable.
    [$a, $b] = streamPair();
    $backend->add('reader', new Readable($a));

    $started = hrtime(true);

    expect($backend->wait(20_000_000))->toBe([])
        ->and(hrtime(true) - $started)->toBeGreaterThanOrEqual(15_000_000);
})->with('backends');

it('removes a stream that was already closed', function ($backend) {
    [$a] = streamPair();
    $backend->add('reader', $wake = new Readable($a));
    fclose($a);

    $backend->remove('reader', $wake);

    expect($backend->wait(0))->toBe([]);
})->with('backends');

it('registers a new stream that reused a closed stream\'s descriptor', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('old', new Readable($a));
    fclose($a);
    fclose($b);

    // The next pair is handed the lowest free descriptors, which the closed pair just gave back.
    [$c, $d] = streamPair();
    $backend->add('new', $wake = new Readable($c));
    fwrite($d, 'x');

    expect($backend->wait(SECOND_NS))->toBe(['new' => [$wake]]);
})->with('backends');

it('declares which wakes it takes directly', function ($backend) {
    $expected = match (true) {
        $backend instanceof KqueueWaiterBackend => [true, true, extension_loaded('pcntl'), true, true],
        default => [true, true, false, false, false],
    };

    expect(array_map($backend->supports(...), [
        WakeReason::READABLE, WakeReason::WRITEABLE, WakeReason::CONTROL_SIGNAL,
        WakeReason::PROCESS_EXIT, WakeReason::FILE_CHANGE,
    ]))->toBe($expected);
})->with('backends');

it('names the descriptor another loop can wait on, or none', function ($backend) {
    $descriptor = $backend->descriptor();

    expect($backend instanceof StreamSelectWaiterBackend ? $descriptor === null : $descriptor >= 0)->toBeTrue()
        ->and($backend->descriptor())->toBe($descriptor);
})->with('backends');

/**
 * Whether $descriptor is readable right now, asked the way a native loop nesting it would:
 * through a second kqueue or epoll set that watches it.
 */
function descriptorReadable(int $descriptor): bool
{
    if (extension_loaded('kqueue')) {
        $outer = kqueue();
        $kev = new kevent();
        EV_SET($kev, $descriptor, EVFILT_READ, EV_ADD, 0, 0, 0);
        $events = [];

        return kevent($outer, [$kev], 1, $events, 1, new timespec()) === 1;
    }

    $outer = epoll_create1(EPOLL_CLOEXEC);
    epoll_ctl($outer, EPOLL_CTL_ADD, $descriptor, EPOLLIN, 0);

    return count(epoll_wait($outer, 1, 0) ?: []) === 1;
}

it('makes its descriptor readable while a watched stream is ready, and quiet once drained', function ($backend) {
    [$a, $b] = streamPair();
    $backend->add('reader', new Readable($a));

    expect(descriptorReadable($backend->descriptor()))->toBeFalse();

    fwrite($b, 'x');

    expect(descriptorReadable($backend->descriptor()))->toBeTrue();

    fread($a, 1);
    $backend->wait(0);

    expect(descriptorReadable($backend->descriptor()))->toBeFalse();
})->with(function () {
    if (extension_loaded('kqueue')) {
        yield 'kqueue' => [fn () => new KqueueWaiterBackend()];
    }

    if (extension_loaded('epoll')) {
        yield 'epoll' => [fn () => new EpollWaiterBackend()];
    }
})->skip(! extension_loaded('kqueue') && ! extension_loaded('epoll'), 'needs ext-kqueue or ext-epoll');

describe('kqueue', function () {
    it('fires a signal and keeps the process alive through it', function () {
        $backend = new KqueueWaiterBackend();
        $backend->add('signals', $wake = new ControlSignal(SIGUSR2));

        posix_kill(getmypid(), SIGUSR2);

        expect($backend->wait(SECOND_NS))->toBe(['signals' => [$wake]]);

        $backend->remove('signals', $wake);
    })->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

    it('fires when a watched process exits', function () {
        $backend = new KqueueWaiterBackend();
        $process = proc_open(['sleep', '0.1'], [], $pipes);
        $backend->add('child', $wake = new ProcessExit(proc_get_status($process)['pid']));

        expect($backend->wait(0))->toBe([])
            ->and($backend->wait(2 * SECOND_NS))->toBe(['child' => [$wake]]);

        proc_close($process);
    });

    it('fires for a process that had already exited when it was watched', function () {
        $backend = new KqueueWaiterBackend();
        $process = proc_open(['true'], [], $pipes);
        $pid = proc_get_status($process)['pid'];
        proc_close($process);

        $backend->add('child', $wake = new ProcessExit($pid));

        expect($backend->wait(SECOND_NS))->toBe(['child' => [$wake]]);
    });

    it('fires when a watched file changes', function () {
        $backend = new KqueueWaiterBackend();
        $path = tempnam(sys_get_temp_dir(), 'waiter');

        try {
            $backend->add('file', $wake = new FileChange($path));

            expect($backend->wait(0))->toBe([]);

            file_put_contents($path, 'changed', FILE_APPEND);

            expect($backend->wait(SECOND_NS))->toBe(['file' => [$wake]]);

            $backend->remove('file', $wake);
        } finally {
            unlink($path);
        }
    });
})->skip(! extension_loaded('kqueue'), 'needs ext-kqueue');