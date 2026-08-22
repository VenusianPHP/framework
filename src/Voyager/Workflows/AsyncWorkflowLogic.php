<?php

namespace Voyager\Workflows;

use Throwable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\Concerns\ResolvesAsyncRuntime;

/**
 * The asynchronous counterpart to the prep -> exec -> post lifecycle.
 *
 * Every lifecycle method may return either a plain value or an Awaitable; the
 * runtime normalises whichever it gets. That is what lets the same node run
 * unchanged on every runtime.
 */
trait AsyncWorkflowLogic
{
    use ResolvesAsyncRuntime;

    protected int $maxRetries = 1;
    protected int $waitSeconds = 0;

    /**
     * Prepare data before async execution.
     *
     * @param SharedBag $shared The shared data store
     * @return mixed Data to pass to execAsync(), or an Awaitable of it
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return null;
    }

    /**
     * Execute the main async logic of this node.
     *
     * @param mixed $prepRes The resolved result from prepAsync()
     * @return mixed The result of execution, or an Awaitable of it
     */
    public function execAsync(mixed $prepRes): mixed
    {
        return null;
    }

    /**
     * Handle results after async execution and decide the next action.
     *
     * @param SharedBag $shared The shared data store
     * @param mixed $prepRes The resolved result from prepAsync()
     * @param mixed $execRes The resolved result from execAsync()
     * @return mixed The action name to transition to, or an Awaitable of it
     */
    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        return null;
    }

    /**
     * Handle the case when all async retries have been exhausted.
     *
     * @param mixed $prepRes The resolved result from prepAsync()
     * @param Throwable $e The exception thrown by the final attempt
     * @return mixed The fallback result, or an Awaitable of it
     * @throws Throwable
     */
    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        throw $e;
    }

    /**
     * Internal async execution wrapper that handles retries.
     *
     * Backoff goes through the runtime so waiting never blocks sibling work.
     *
     * @param mixed $prepRes The resolved result from prepAsync()
     * @throws Throwable
     */
    public function _execAsync(mixed $prepRes): mixed
    {
        $runtime = $this->runtime();

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $runtime->await($this->execAsync($prepRes));
            } catch (Throwable $e) {
                if ($attempt === $this->maxRetries - 1) {
                    return $runtime->await($this->execFallbackAsync($prepRes, $e));
                }

                if ($this->waitSeconds > 0) {
                    $runtime->await($runtime->delay($this->waitSeconds));
                }
            }
        }

        return null;
    }

    /**
     * Internal async run method that executes the full lifecycle.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The action from postAsync()
     */
    public function _runAsync(SharedBag $shared): ?string
    {
        $runtime = $this->runtime();

        $prepRes = $runtime->await($this->prepAsync($shared));
        $execRes = $runtime->await($this->_execAsync($prepRes));

        return $runtime->await($this->postAsync($shared, $prepRes, $execRes));
    }

    /**
     * Run this async node in isolation.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The action from postAsync()
     * @throws WorkflowRuntimeException If this node has successors
     */
    public function runAsync(SharedBag $shared): ?string
    {
        if (!empty($this->successors)) {
            throw new WorkflowRuntimeException("Cannot run an async node that has successors directly. Use an AsyncFlow to execute the full graph.");
        }

        return $this->_runAsync($shared);
    }

    /**
     * Sync run is not supported for async nodes.
     *
     * @param SharedBag $shared The shared data store
     * @throws WorkflowRuntimeException Always thrown
     */
    public function run(SharedBag $shared): never
    {
        throw new WorkflowRuntimeException("Cannot call sync 'run' on an async node. Use 'runAsync' instead.");
    }
}
