<?php

use Voyager\Workflows\Flow;
use Voyager\Workflows\Node;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;
use Voyager\Contracts\Workflows\WorkflowLogicException;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;

/** A node that appends its name to the bag's trail and answers $action. */
function step(string $name, ?string $action = null): Node
{
    return new class($name, $action) extends Node {
        public function __construct(private string $name, private ?string $action) { parent::__construct(); }

        public function prep(SharedBag $shared): mixed
        {
            return $shared->trail ?? [];
        }

        public function exec(mixed $trail): mixed
        {
            return [...$trail, $this->name];
        }

        public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
        {
            $shared->trail = $execRes;

            return $this->action;
        }
    };
}

it('runs prep, exec and post, and hands back the action post() chose', function () {
    $bag = new SharedBag();

    expect(step('only', 'done')->run($bag))->toBe('done')
        ->and($bag->trail)->toBe(['only']);
});

it('walks a flow along the actions its nodes return', function () {
    $start = step('start', 'approve');
    $start->on('approve')->next(step('publish'));
    $start->on('reject')->next(step('archive'));
    $bag = new SharedBag();

    new Flow($start)->run($bag);

    expect($bag->trail)->toBe(['start', 'publish']);
});

it('follows the default successor and stops where none is defined for the action', function () {
    $start = step('start');
    $start->next(step('second', 'unknown'))->next(step('never'));
    $bag = new SharedBag();

    new Flow($start)->run($bag);

    expect($bag->trail)->toBe(['start', 'second']);
});

it('retries exec() and falls back once the attempts are spent', function () {
    $node = new class(3) extends Node {
        public int $attempts = 0;

        public function exec(mixed $prepRes): mixed
        {
            $this->attempts++;

            throw new RuntimeException("attempt {$this->attempts}");
        }

        public function execFallback(mixed $prepRes, Throwable $e): mixed
        {
            return 'fell back after '.$e->getMessage();
        }

        public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
        {
            $shared->result = $execRes;

            return null;
        }
    };
    $bag = new SharedBag();

    $node->run($bag);

    expect($node->attempts)->toBe(3)
        ->and($bag->result)->toBe('fell back after attempt 3');
});

it('hands a flow\'s params to every node it runs', function () {
    $node = new class extends Node {
        public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
        {
            $shared->seen = $this->params;

            return null;
        }
    };
    $flow = new Flow($node);
    $flow->setParams(['user' => 7]);
    $bag = new SharedBag();

    $flow->run($bag);

    expect($bag->seen)->toBe(['user' => 7]);
});

it('refuses to overwrite a successor, to run a node with successors, and to walk async nodes', function () {
    $start = step('start');
    $start->next(step('next'));

    expect(fn () => $start->next(step('again')))->toThrow(WorkflowLogicException::class, "Cannot overwrite existing successor for action 'default'.")
        ->and(fn () => $start->run(new SharedBag()))->toThrow(WorkflowRuntimeException::class, 'Cannot run a node that has successors directly. Use a Flow to execute the full graph.')
        ->and(fn () => new Flow(new AsyncNode())->run(new SharedBag()))->toThrow(WorkflowRuntimeException::class, 'Synchronous Flow cannot contain async nodes. Use AsyncFlow instead.')
        ->and(fn () => new AsyncNode()->run(new SharedBag()))->toThrow(WorkflowRuntimeException::class, "Cannot call sync 'run' on an async node. Use 'runAsync' instead.");
});
