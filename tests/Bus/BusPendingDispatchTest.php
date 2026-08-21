<?php

use Tests\Bus\Fixtures\PendingDispatchWithoutDestructor;

beforeEach(function () {
    $this->job = Mockery::mock(stdClass::class);
    $this->pendingDispatch = new PendingDispatchWithoutDestructor($this->job);
});

test('on connection', function () {
    $this->job->shouldReceive('onConnection')->once()->with('test-connection');
    $this->pendingDispatch->onConnection('test-connection');
});

test('on queue', function () {
    $this->job->shouldReceive('onQueue')->once()->with('test-queue');
    $this->pendingDispatch->onQueue('test-queue');
});

test('all on connection', function () {
    $this->job->shouldReceive('allOnConnection')->once()->with('test-connection');
    $this->pendingDispatch->allOnConnection('test-connection');
});

test('all on queue', function () {
    $this->job->shouldReceive('allOnQueue')->once()->with('test-queue');
    $this->pendingDispatch->allOnQueue('test-queue');
});

test('delay', function () {
    $this->job->shouldReceive('delay')->once()->with(60);
    $this->pendingDispatch->delay(60);
});

test('without delay', function () {
    $this->job->shouldReceive('withoutDelay')->once();
    $this->pendingDispatch->withoutDelay();
});

test('after commit', function () {
    $this->job->shouldReceive('afterCommit')->once();
    $this->pendingDispatch->afterCommit();
});

test('before commit', function () {
    $this->job->shouldReceive('beforeCommit')->once();
    $this->pendingDispatch->beforeCommit();
});

test('chain', function () {
    $chain = [new stdClass];
    $this->job->shouldReceive('chain')->once()->with($chain);
    $this->pendingDispatch->chain($chain);
});

test('after response', function () {
    $this->pendingDispatch->afterResponse();

    expect((new ReflectionClass($this->pendingDispatch))->getProperty('afterResponse')->getValue($this->pendingDispatch))->toBeTrue();
});

test('get job', function () {
    expect($this->pendingDispatch->getJob())->toBe($this->job);
});

test('dynamically proxy methods', function () {
    $newJob = Mockery::mock(stdClass::class);
    $this->job->shouldReceive('appendToChain')->once()->with($newJob);
    $this->pendingDispatch->appendToChain($newJob);
});
