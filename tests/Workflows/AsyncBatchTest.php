<?php

use Voyager\Workflows\AsyncBatchFlow;
use Voyager\Workflows\AsyncBatchNode;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\AsyncParallelBatchFlow;
use Voyager\Workflows\AsyncParallelBatchNode;
use Voyager\Workflows\SharedBag;

test('a batch node maps exec over every item', function (string $runtime) {
    $node = new class extends AsyncBatchNode
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return ['a', 'b', 'c'];
        }

        public function execAsync(mixed $prepRes): mixed
        {
            return strtoupper($prepRes);
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->results = $execRes;

            return null;
        }
    };

    $shared = new SharedBag;
    $node->usesRuntime(new $runtime)->runAsync($shared);

    expect($shared->results)->toBe(['A', 'B', 'C']);
})->with('async runtimes');

test('a batch node retries each item on its own', function (string $runtime) {
    $node = new class(2) extends AsyncBatchNode
    {
        public array $attempts = [];

        public function prepAsync(SharedBag $shared): mixed
        {
            return ['ok', 'flaky'];
        }

        public function execAsync(mixed $prepRes): mixed
        {
            $this->attempts[$prepRes] = ($this->attempts[$prepRes] ?? 0) + 1;

            if ($prepRes === 'flaky' && $this->attempts[$prepRes] < 2) {
                throw new RuntimeException('flaked');
            }

            return $prepRes;
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->results = $execRes;

            return null;
        }
    };

    $shared = new SharedBag;
    $node->usesRuntime(new $runtime)->runAsync($shared);

    expect($shared->results)->toBe(['ok', 'flaky'])
        ->and($node->attempts)->toBe(['ok' => 1, 'flaky' => 2]);
})->with('async runtimes');

test('a parallel batch node keeps item order in its results', function (string $runtime) {
    $node = new class extends AsyncParallelBatchNode
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [3, 1, 2];
        }

        public function execAsync(mixed $prepRes): mixed
        {
            $this->runtime()->await($this->runtime()->delay($prepRes * 0.005));

            return $prepRes;
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->results = $execRes;

            return null;
        }
    };

    $shared = new SharedBag;
    $node->usesRuntime(new $runtime)->runAsync($shared);

    expect($shared->results)->toBe([3, 1, 2]);
})->with('async runtimes');

test('a parallel batch node overlaps its items', function (string $runtime) {
    $node = new class extends AsyncParallelBatchNode
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [1, 2, 3];
        }

        public function execAsync(mixed $prepRes): mixed
        {
            $this->runtime()->await($this->runtime()->delay(0.03));

            return $prepRes;
        }
    };

    $started = microtime(true);
    $node->usesRuntime(new $runtime)->runAsync(new SharedBag);

    expect(microtime(true) - $started)->toBeLessThan(0.075);
})->with('overlapping async runtimes');

test('a batch flow runs the graph once per param set', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->seen = [...($shared->seen ?? []), $this->params['id']];

            return null;
        }
    };

    $flow = new class($node) extends AsyncBatchFlow
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [['id' => 1], ['id' => 2], ['id' => 3]];
        }
    };

    $shared = new SharedBag;
    $flow->usesRuntime(new $runtime)->runAsync($shared);

    expect($shared->seen)->toBe([1, 2, 3]);
})->with('async runtimes');

test('a nested batch flow still loops its whole param list', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->seen = [...($shared->seen ?? []), $this->params['id']];

            return null;
        }
    };

    $batch = new class($node) extends AsyncBatchFlow
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [['id' => 'x'], ['id' => 'y']];
        }
    };

    $shared = new SharedBag;
    (new AsyncFlow($batch, new $runtime))->runAsync($shared);

    expect($shared->seen)->toBe(['x', 'y']);
})->with('async runtimes');

test('a parallel batch flow runs every branch and isolates node params', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function execAsync(mixed $prepRes): mixed
        {
            $id = $this->params['id'];

            $this->runtime()->await($this->runtime()->delay(0.01));

            return $id === $this->params['id'] ? $id : 'clobbered';
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->seen = [...($shared->seen ?? []), $execRes];

            return null;
        }
    };

    $flow = new class($node) extends AsyncParallelBatchFlow
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [['id' => 1], ['id' => 2], ['id' => 3]];
        }
    };

    $shared = new SharedBag;
    $flow->usesRuntime(new $runtime)->runAsync($shared);

    expect($shared->seen)->toHaveCount(3)
        ->and($shared->seen)->not->toContain('clobbered');
})->with('async runtimes');

test('a parallel batch flow overlaps its branches', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function execAsync(mixed $prepRes): mixed
        {
            return $this->runtime()->await($this->runtime()->delay(0.03));
        }
    };

    $flow = new class($node) extends AsyncParallelBatchFlow
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [['id' => 1], ['id' => 2], ['id' => 3]];
        }
    };

    $started = microtime(true);
    $flow->usesRuntime(new $runtime)->runAsync(new SharedBag);

    expect(microtime(true) - $started)->toBeLessThan(0.075);
})->with('overlapping async runtimes');
