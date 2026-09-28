<?php

use Voyager\Log\FlattenedThrowable;
use Venusian\Tests\Log\Fixtures\Caught;

it('rebuilds a flattened exception with its class, message, code, file, line, trace and previous', function () {
    $e = Caught::underClosure();

    expect(fn () => serialize($e))->toThrow(Exception::class, "Serialization of 'Closure' is not allowed");

    $rebuilt = unserialize(serialize(FlattenedThrowable::from($e)))->rebuild();

    expect($rebuilt)->toBeInstanceOf(DomainException::class)
        ->and($rebuilt->getMessage())->toBe('card declined')
        ->and($rebuilt->getCode())->toBe(402)
        ->and($rebuilt->getFile())->toBe($e->getFile())
        ->and($rebuilt->getLine())->toBe($e->getLine())
        ->and($rebuilt->getTrace())->toBe(array_map(fn (array $frame) => array_diff_key($frame, ['args' => true]), $e->getTrace()))
        ->and($rebuilt->getPrevious())->toBeInstanceOf(RuntimeException::class)
        ->and($rebuilt->getPrevious()->getMessage())->toBe('gateway said no');
});

it('rebuilds a final internal exception through its constructor', function () {
    try {
        echo match (3) { 1 => 'one' };
    } catch (UnhandledMatchError $e) {
    }

    $rebuilt = FlattenedThrowable::from($e)->rebuild();

    expect($rebuilt)->toBeInstanceOf(UnhandledMatchError::class)
        ->and($rebuilt->getMessage())->toBe($e->getMessage())
        ->and($rebuilt->getLine())->toBe($e->getLine());
});
