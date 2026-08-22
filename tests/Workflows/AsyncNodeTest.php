<?php

use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\Node;
use Voyager\Workflows\Runtimes\SyncRuntime;
use Voyager\Workflows\SharedBag;

test('the async lifecycle runs prep then exec then post', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public array $order = [];

        public function prepAsync(SharedBag $shared): mixed
        {
            $this->order[] = 'prep';

            return 'prepared';
        }

        public function execAsync(mixed $prepRes): mixed
        {
            $this->order[] = 'exec:'.$prepRes;

            return 'executed';
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $this->order[] = 'post:'.$execRes;
            $shared->result = $execRes;

            return 'next';
        }
    };

    $shared = new SharedBag;

    expect($node->usesRuntime(new $runtime)->runAsync($shared))->toBe('next')
        ->and($node->order)->toBe(['prep', 'exec:prepared', 'post:executed'])
        ->and($shared->result)->toBe('executed');
})->with('async runtimes');

test('lifecycle methods may return a plain value or an awaitable', function (string $runtime) {
    /** @var AsyncRuntime $instance */
    $instance = new $runtime;

    $plain = new class extends AsyncNode
    {
        public function execAsync(mixed $prepRes): mixed
        {
            return 'plain';
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return $execRes;
        }
    };

    $wrapped = new class($instance) extends AsyncNode
    {
        public function __construct(private AsyncRuntime $async)
        {
            parent::__construct(runtime: $async);
        }

        public function execAsync(mixed $prepRes): mixed
        {
            return $this->async->async(fn () => 'wrapped');
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return $execRes;
        }
    };

    expect($plain->usesRuntime($instance)->runAsync(new SharedBag))->toBe('plain')
        ->and($wrapped->runAsync(new SharedBag))->toBe('wrapped');
})->with('async runtimes');

test('exec is retried up to maxRetries before the fallback runs', function (string $runtime) {
    $node = new class(3) extends AsyncNode
    {
        public int $attempts = 0;

        public function execAsync(mixed $prepRes): mixed
        {
            $this->attempts++;

            throw new RuntimeException('attempt '.$this->attempts);
        }

        public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
        {
            return 'fell back after '.$e->getMessage();
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return $execRes;
        }
    };

    expect($node->usesRuntime(new $runtime)->runAsync(new SharedBag))
        ->toBe('fell back after attempt 3')
        ->and($node->attempts)->toBe(3);
})->with('async runtimes');

test('a retry that eventually succeeds never reaches the fallback', function (string $runtime) {
    $node = new class(3) extends AsyncNode
    {
        public int $attempts = 0;

        public function execAsync(mixed $prepRes): mixed
        {
            $this->attempts++;

            if ($this->attempts < 2) {
                throw new RuntimeException('not yet');
            }

            return 'recovered';
        }

        public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
        {
            return 'fallback';
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return $execRes;
        }
    };

    expect($node->usesRuntime(new $runtime)->runAsync(new SharedBag))->toBe('recovered')
        ->and($node->attempts)->toBe(2);
})->with('async runtimes');

test('the default fallback rethrows the final failure', function (string $runtime) {
    $node = new class(2) extends AsyncNode
    {
        public function execAsync(mixed $prepRes): mixed
        {
            throw new RuntimeException('never works');
        }
    };

    expect(fn () => $node->usesRuntime(new $runtime)->runAsync(new SharedBag))
        ->toThrow(RuntimeException::class, 'never works');
})->with('async runtimes');

test('retry backoff goes through the runtime instead of blocking', function () {
    $runtime = new class extends SyncRuntime
    {
        public array $delays = [];

        public function delay(float $seconds): \Voyager\Contracts\Workflows\Awaitable
        {
            $this->delays[] = $seconds;

            return parent::delay(0);
        }
    };

    $node = new class(3, 5) extends AsyncNode
    {
        public function execAsync(mixed $prepRes): mixed
        {
            throw new RuntimeException('always fails');
        }

        public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
        {
            return 'done';
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return $execRes;
        }
    };

    $node->usesRuntime($runtime)->runAsync(new SharedBag);

    expect($runtime->delays)->toBe([5.0, 5.0]);
});

test('an async node falls back to the sync runtime when none is supplied', function () {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return 'ran';
        }
    };

    expect($node->runtime())->toBeInstanceOf(SyncRuntime::class)
        ->and($node->runAsync(new SharedBag))->toBe('ran');
});

test('an async node refuses a synchronous run', function () {
    $node = new AsyncNode;

    expect(fn () => $node->run(new SharedBag))
        ->toThrow(WorkflowRuntimeException::class, "Cannot call sync 'run' on an async node");
});

test('an async node with successors refuses to run in isolation', function () {
    $node = new AsyncNode;
    $node->next(new Node);

    expect(fn () => $node->runAsync(new SharedBag))
        ->toThrow(WorkflowRuntimeException::class, 'Use an AsyncFlow');
});
