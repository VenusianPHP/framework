<?php

use Voyager\Queue\Attributes\Backoff;
use Voyager\Queue\Attributes\FailOnTimeout;
use Voyager\Queue\Attributes\MaxExceptions;
use Voyager\Queue\Attributes\Timeout;
use Voyager\Queue\Attributes\Tries;
use Voyager\Queue\NullQueue;

// Payload hooks are static and outlive the app that registered them, so these tests set them aside
// and put back exactly what was there.
beforeEach(function () {
    $hooks = new ReflectionProperty(NullQueue::class, 'createPayloadCallbacks');
    $this->savedPayloadHooks = $hooks->getValue();
    $hooks->setValue(null, []);
});
afterEach(fn () => (new ReflectionProperty(NullQueue::class, 'createPayloadCallbacks'))->setValue(null, $this->savedPayloadHooks));

/** The payload a queue builds for a job, decoded. */
function queuePayload(object $job): array
{
    $queue = new class extends NullQueue {
        public function payloadFor(object $job): array
        {
            return json_decode($this->createPayload($job, 'default'), true);
        }
    };

    return $queue->payloadFor($job);
}

#[Tries(3), Backoff(5, 10), Timeout(30), MaxExceptions(2), FailOnTimeout]
class AttributedJob {}

class PlainJob {}

#[Tries(3), Backoff(5, 10), Timeout(30), MaxExceptions(2)]
class AttributedJobWithProperties
{
    public $tries;
    public $backoff;
    public $timeout;
    public $maxExceptions;

    public function __construct()
    {
        // Set when the job is created: these override the attributes.
        $this->tries = 7;
        $this->backoff = [1, 2];
        $this->timeout = 90;
        $this->maxExceptions = 4;
    }
}

#[Tries(3), Timeout(30)]
class AttributedJobWithDefaults
{
    // Declared defaults are what the class says when nothing set them, so the attribute wins.
    public $tries = 7;
    public $timeout = 90;
}

#[Tries(3), Backoff(5, 10)]
class AttributedJobWithMethods
{
    public $tries = 7;
    public $backoff = 1;

    public function tries(): int
    {
        return 9;
    }

    public function backoff(): array
    {
        return [20, 40];
    }
}

it('reads queue attributes on a plain job into its payload', function () {
    $payload = queuePayload(new AttributedJob);

    expect($payload['maxTries'])->toBe(3)
        ->and($payload['backoff'])->toBe('5,10')
        ->and($payload['timeout'])->toBe(30)
        ->and($payload['maxExceptions'])->toBe(2)
        ->and($payload['failOnTimeout'])->toBeTrue();
});

it('leaves the payload empty when a job declares nothing', function () {
    $payload = queuePayload(new PlainJob);

    expect([$payload['maxTries'], $payload['backoff'], $payload['timeout'], $payload['maxExceptions'], $payload['failOnTimeout']])
        ->toBe([null, null, null, null, false]);
});

it('lets an attribute win over a property left at its declared default', function () {
    $payload = queuePayload(new AttributedJobWithDefaults);

    expect($payload['maxTries'])->toBe(3)
        ->and($payload['timeout'])->toBe(30);
});

it('lets a property set on the job win over its attribute', function () {
    $payload = queuePayload(new AttributedJobWithProperties);

    expect($payload['maxTries'])->toBe(7)
        ->and($payload['backoff'])->toBe('1,2')
        ->and($payload['timeout'])->toBe(90)
        ->and($payload['maxExceptions'])->toBe(4);
});

it('lets tries() and backoff() win over both the property and the attribute', function () {
    $payload = queuePayload(new AttributedJobWithMethods);

    expect($payload['maxTries'])->toBe(9)
        ->and($payload['backoff'])->toBe('20,40');
});
