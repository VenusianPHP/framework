<?php

use parallel\Runtime;
use Venusian\Tests\IOPools\Fixtures\AddNumbers;
use Venusian\Tests\IOPools\Fixtures\EchoingJob;
use Venusian\Tests\IOPools\Fixtures\ExplodingJob;
use Voyager\IOPools\ThreadRuntime;

// Hooks may run before a skip is honoured, so they guard themselves too.
beforeEach(function () {
    if (! extension_loaded('parallel')) {
        return;
    }

    $this->root = dirname(__DIR__, 2);
    $this->path = '/tmp/vf-rt-'.getmypid().'-'.uniqid().'.sock';
    $this->server = stream_socket_server('unix://'.$this->path);
    $this->runtime = new Runtime($this->root.'/vendor/autoload.php');

    // every test starts from a greeted thread
    $this->hello = $this->runtime->run(ThreadRuntime::hello(...), [$this->path, 'pool:7', $this->root]);
    $this->bell = stream_socket_accept($this->server, 5.0);
});

afterEach(function () {
    if (! extension_loaded('parallel')) {
        return;
    }

    $this->runtime->kill();
    fclose($this->bell);
    fclose($this->server);
    @unlink($this->path);
});

describe('thread runtime', function () {
it('says its name first, newline-terminated', function () {
    expect(fgets($this->bell))->toBe("pool:7\n")
        ->and($this->hello->value())->toBeTrue();
});

it('runs a gig and hands back a serialized ok envelope', function () {
    fgets($this->bell);

    $future = $this->runtime->run(ThreadRuntime::work(...), [$this->path, serialize(new AddNumbers(2, 3))]);

    expect(unserialize($future->value()))->toBe(['ok' => true, 'value' => 5]);
});

it('rings the bell once per gig, on the connection it already has', function () {
    fgets($this->bell);

    foreach ([1, 2, 3] as $n) {
        $this->runtime->run(ThreadRuntime::work(...), [$this->path, serialize(new AddNumbers($n, 0))])->value();
    }

    stream_set_blocking($this->bell, false);
    usleep(20_000);

    // PHPUnit records a timeout warning even when @ suppresses it. Swallow it for this call only.
    set_error_handler(static fn (): bool => true);
    try {
        $again = stream_socket_accept($this->server, 0.05);
    } finally {
        restore_error_handler();
    }

    expect(fread($this->bell, 64))->toBe('!!!')
        ->and($again)->toBeFalse();      // never reconnected
});

it('rings the bell when the gig throws, and the envelope says so', function () {
    fgets($this->bell);

    $future = $this->runtime->run(ThreadRuntime::work(...), [$this->path, serialize(new ExplodingJob('nope'))]);
    $envelope = unserialize($future->value());

    expect($envelope['ok'])->toBeFalse()
        ->and($envelope['class'])->toBe(RuntimeException::class)
        ->and(fread($this->bell, 8))->toBe('!');
});

it('keeps a gig\'s echo out of its answer', function () {
    fgets($this->bell);

    $future = $this->runtime->run(ThreadRuntime::work(...), [$this->path, serialize(new EchoingJob)]);

    expect(unserialize($future->value()))->toBe(['ok' => true, 'value' => 'clean']);
});

it('fails the envelope when the gig is not something it can run', function () {
    fgets($this->bell);

    $future = $this->runtime->run(ThreadRuntime::work(...), [$this->path, serialize('not a gig')]);
    $envelope = unserialize($future->value());

    expect($envelope['ok'])->toBeFalse()
        ->and($envelope['message'])->toContain('ShouldPool');
});
})->skip(! extension_loaded('parallel'), 'ext-parallel not loaded');
