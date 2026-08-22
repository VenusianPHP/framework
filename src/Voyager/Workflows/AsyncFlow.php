<?php

namespace Voyager\Workflows;

use Throwable;
use Voyager\Contracts\Workflows\AsyncRunnable;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\RuntimeAware;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\Concerns\ResolvesAsyncRuntime;

/**
 * Orchestrates a graph that contains async nodes.
 *
 * A graph walk is sequential by nature, since the next node is unknown until
 * the current one returns its action. Overlap therefore comes from parallel
 * batch members and from fan-out inside a single node, not from this walk.
 */
class AsyncFlow extends Flow implements AsyncRunnable
{
    use ResolvesAsyncRuntime;

    /**
     * Whether each walk gets its own copy of every node it visits.
     *
     * Nodes carry their params as state, so branches that may run at the same
     * time need separate instances. The cost is that node state is no longer
     * observable from outside the flow, which is why this stays off by default.
     */
    protected bool $isolatesNodes = false;

    /**
     * @param BaseNode|null $startNode The node where execution begins
     * @param AsyncRuntime|null $runtime Runtime handed to every member of the graph
     */
    public function __construct(?BaseNode $startNode = null, ?AsyncRuntime $runtime = null)
    {
        parent::__construct($startNode);

        $this->runtime = $runtime;
    }

    /**
     * Prepare data before orchestration.
     *
     * @param SharedBag $shared The shared data store
     * @return mixed Data to pass to postAsync(), or an Awaitable of it
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return null;
    }

    /**
     * Process results after orchestration completes.
     *
     * @param SharedBag $shared The shared data store
     * @param mixed $prepRes The resolved result from prepAsync()
     * @param mixed $execRes The final action from orchestration
     * @return mixed Unchanged result, or an Awaitable of it
     */
    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        return $execRes;
    }

    /**
     * Walk the graph, awaiting async members and running sync ones in place.
     *
     * @param SharedBag $shared The shared data store
     * @param array|null $params Optional runtime parameters
     * @return string|null The final action
     * @throws Throwable
     */
    public function _orchestrateAsync(SharedBag $shared, ?array $params = null): ?string
    {
        $runtime = $this->runtime();
        $current = $this->startNode;
        $p = $params ?? $this->params;
        $lastAction = null;

        if ($this->isolatesNodes && $current) {
            $current = clone $current;
        }

        while ($current) {
            $current->setParams($p);

            if ($current instanceof RuntimeAware) {
                $current->usesRuntime($runtime);
            }

            $lastAction = $current instanceof AsyncRunnable
                ? $runtime->await($current->_runAsync($shared))
                : $current->_run($shared);

            $current = $this->getNextNode($current, $lastAction);

            if ($this->isolatesNodes && $current) {
                $current = clone $current;
            }
        }

        return $lastAction;
    }

    /**
     * Internal async run method covering the full flow lifecycle.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The final action
     * @throws Throwable
     */
    public function _runAsync(SharedBag $shared): ?string
    {
        $runtime = $this->runtime();

        $prepRes = $runtime->await($this->prepAsync($shared));
        $result = $runtime->await($this->_orchestrateAsync($shared, $this->params));

        return $runtime->await($this->postAsync($shared, $prepRes, $result));
    }

    /**
     * Run this async flow.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The final action
     * @throws Throwable
     */
    public function runAsync(SharedBag $shared): ?string
    {
        return $this->_runAsync($shared);
    }

    /**
     * Sync run is not supported for async flows.
     *
     * @param SharedBag $shared The shared data store
     * @throws WorkflowRuntimeException Always thrown
     */
    public function run(SharedBag $shared): never
    {
        throw new WorkflowRuntimeException("Cannot call sync 'run' on an AsyncFlow. Use 'runAsync' instead.");
    }

    /**
     * Internal sync run should not be called.
     *
     * @param SharedBag $shared The shared data store
     * @throws WorkflowRuntimeException Always thrown
     */
    protected function _run(SharedBag $shared): never
    {
        throw new WorkflowRuntimeException("Internal error: _run should not be called on AsyncFlow.");
    }
}
