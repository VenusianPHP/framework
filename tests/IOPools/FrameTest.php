<?php

use Voyager\IOPools\ProcessPoolFrame as Frame;

function frame(array $payload): string
{
    $body = serialize($payload);

    return pack('N', strlen($body)).$body;
}

it('waits for the second half of a frame', function () {
    $whole = pack('N', strlen($body = serialize(['ok' => true]))).$body;
    $buffer = substr($whole, 0, 5);

    expect(Frame::take($buffer))->toBeNull();

    $buffer .= substr($whole, 5);

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
