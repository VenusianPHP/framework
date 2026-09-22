<?php

use Voyager\Contracts\IOPools\MailCollection;
use Voyager\Contracts\IOPools\Receivable;
use Voyager\Filesystem\FileChunk;
use Voyager\Filesystem\FileStreamResource;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\ProcessPool;

beforeEach(function () {
    // the pool worker boots THIS repo; its local disk root is storage/app
    $this->root = dirname(__DIR__, 2);
    $this->name = 'vf-stream-'.getmypid().'.bin';
    $this->file = $this->root.'/storage/app/'.$this->name;
    file_put_contents($this->file, $this->payload = random_bytes(2_500_000));     // 2.5 MB → 3 chunks at 1 MB

    $this->handler = new class implements Receivable {
        public array $chunks = [];
        public function handOff(MailCollection $mail): void { foreach ($mail->mail() as $m) if ($m instanceof FileChunk) $this->chunks[] = $m; }
    };
    $this->loop = new EventLoop(mail_handler: $this->handler);
    $this->pool = new ProcessPool($this->loop, [PHP_BINARY, $this->root.'/src/Voyager/IOPools/bin/pool-worker', $this->root.'/vendor/autoload.php', $this->root], 2);
});

afterEach(function () {
    $this->pool->shutDown();
    @unlink($this->file);
});

it('delivers the file as ordered chunks, each in a turn, and settles with the byte count', function () {
    $stream = new FileStreamResource($this->loop, $this->pool, 'local', $this->name);

    $total = null;
    $stream->done()->then(function (int $n) use (&$total) { $total = $n; });
    $this->loop->run();

    $chunks = $this->handler->chunks;
    expect($total)->toBe(2_500_000)
        ->and(count($chunks))->toBe(3)
        ->and(array_map(fn (FileChunk $c) => $c->offset, $chunks))->toBe([0, 1 << 20, 2 << 20])
        ->and($chunks[2]->last)->toBeTrue()
        ->and(implode('', array_map(fn (FileChunk $c) => $c->bytes, $chunks)))->toBe($this->payload);
});

it('keeps the loop turning while chunks arrive', function () {
    $stream = new FileStreamResource($this->loop, $this->pool, 'local', $this->name, chunk: 500_000);
    $beats = 0;
    $ticker = $this->loop->every(0.005, function () use (&$beats, &$ticker, $stream) { $beats++; if ($stream->done()->settled()) $ticker->cancel(); }, 'beats');

    $this->loop->run();

    expect($beats)->toBeGreaterThan(3)->and(count($this->handler->chunks))->toBe(5);
});

it('rejects done() and leaves the loop when the file is missing', function () {
    $stream = new FileStreamResource($this->loop, $this->pool, 'local', 'nope-'.getmypid().'.bin');

    expect(fn () => $this->loop->await($stream->done()))->toThrow(\Voyager\Contracts\IOPools\RemoteException::class);
});
