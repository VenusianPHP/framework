<?php

use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\IOPools\ProcessPoolFrame as Frame;

function frame(array $payload): string
{
    return Frame::encode($payload);
}

it('waits for the second half of a frame', function () {
    $whole = frame(['ok' => true]);
    $buffer = substr($whole, 0, 9);

    expect(Frame::take($buffer))->toBeNull();

    $buffer .= substr($whole, 9);

    expect(Frame::take($buffer))->toBe(['ok' => true])
        ->and($buffer)->toBe('');
});

it('takes two frames arriving in one buffer', function () {
    $buffer = frame(['n' => 1]).frame(['n' => 2]);

    expect(Frame::take($buffer))->toBe(['n' => 1]);
    expect(Frame::take($buffer))->toBe(['n' => 2])
        ->and($buffer)->toBe('');
});

it('takes a payload that contains a newline', function () {
    $payload = ['text' => "hello\nworld"];
    $buffer = frame($payload);

    expect(Frame::take($buffer))->toBe($payload)
        ->and($buffer)->toBe('');
});

it('takes a 1MB payload', function () {
    $payload = ['blob' => str_repeat('x', 1024 * 1024)];
    $buffer = frame($payload);

    expect(Frame::take($buffer))->toBe($payload)
        ->and($buffer)->toBe('');
});

it('waits on a magic that has only half arrived', function () {
    $buffer = substr(frame(['ok' => true]), 0, 2);

    expect(Frame::take($buffer))->toBeNull()
        ->and($buffer)->toBe(substr(Frame::MAGIC, 0, 2));
});

it('refuses stray output instead of reading it as a length', function () {
    // what a compile warning on the worker's stdout looks like ahead of the first frame
    $buffer = "\nWarning: something in pool-worker on line 8\n".frame(['hello' => 1]);

    expect(fn () => Frame::take($buffer))->toThrow(EventLoopException::class, 'Warning: something in pool-worker');
});
