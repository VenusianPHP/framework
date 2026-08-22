<?php

use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\Flow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Records the order in which graph members ran, on the shared bag.
 */
function asyncTrail(SharedBag $shared): array
{
    return $shared->trail ?? [];
}

test('an async flow walks async and sync nodes in one graph', function (string $runtime) {
    $first = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'async'];

            return 'onwards';
        }
    };

    $second = new class extends Node
    {
        public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
        {
            $shared->trail = [...asyncTrail($shared), 'sync'];

            return 'finished';
        }
    };

    $first->next($second, 'onwards');

    $shared = new SharedBag;
    $flow = new AsyncFlow($first, new $runtime);

    expect($flow->runAsync($shared))->toBe('finished')
        ->and(asyncTrail($shared))->toBe(['async', 'sync']);
})->with('async runtimes');

test('an async flow runs its own prep and post around orchestration', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'node'];

            return 'inner';
        }
    };

    $flow = new class($node) extends AsyncFlow
    {
        public function prepAsync(SharedBag $shared): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'flow prep'];

            return 'prepared';
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'flow post:'.$prepRes];

            return 'outer';
        }
    };

    $shared = new SharedBag;

    expect($flow->usesRuntime(new $runtime)->runAsync($shared))->toBe('outer')
        ->and(asyncTrail($shared))->toBe(['flow prep', 'node', 'flow post:prepared']);
})->with('async runtimes');

test('a flow hands its runtime to every async member it orchestrates', function () {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->seen = $this->runtime();

            return null;
        }
    };

    $runtime = new \Voyager\Workflows\Runtimes\FiberRuntime;
    $shared = new SharedBag;

    (new AsyncFlow($node, $runtime))->runAsync($shared);

    expect($shared->seen)->toBe($runtime);
});

test('a nested async flow runs its full lifecycle', function (string $runtime) {
    $inner = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'inner node'];

            return null;
        }
    };

    $innerFlow = new class($inner) extends AsyncFlow
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'inner flow post'];

            return 'continue';
        }
    };

    $last = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [...asyncTrail($shared), 'last'];

            return 'done';
        }
    };

    $innerFlow->next($last, 'continue');

    $shared = new SharedBag;

    expect((new AsyncFlow($innerFlow, new $runtime))->runAsync($shared))->toBe('done')
        ->and(asyncTrail($shared))->toBe(['inner node', 'inner flow post', 'last']);
})->with('async runtimes');

test('the walk stops when no successor matches the action', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            return 'nowhere';
        }
    };

    $node->next(new AsyncNode, 'somewhere');

    expect((new AsyncFlow($node, new $runtime))->runAsync(new SharedBag))->toBe('nowhere');
})->with('async runtimes');

test('params reach every node in the walk', function (string $runtime) {
    $node = new class extends AsyncNode
    {
        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->params = $this->params;

            return null;
        }
    };

    $flow = new AsyncFlow($node, new $runtime);
    $flow->setParams(['tenant' => 'acme']);

    $shared = new SharedBag;
    $flow->runAsync($shared);

    expect($shared->params)->toBe(['tenant' => 'acme']);
})->with('async runtimes');

test('a synchronous flow refuses to orchestrate async nodes', function () {
    $flow = new Flow(new AsyncNode);

    expect(fn () => $flow->run(new SharedBag))
        ->toThrow(WorkflowRuntimeException::class, 'Use AsyncFlow instead');
});

test('an async flow refuses a synchronous run', function () {
    expect(fn () => (new AsyncFlow(new AsyncNode))->run(new SharedBag))
        ->toThrow(WorkflowRuntimeException::class, "Cannot call sync 'run' on an AsyncFlow");
});
