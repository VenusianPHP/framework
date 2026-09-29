<?php

namespace Voyager\Workflows;

use Voyager\Contracts\Workflows\AsyncRuntime;

/**
 * Runs the graph once per param set returned by prepAsync(), all in flight.
 *
 * Branches get their own copy of each node they visit, since params live on
 * node state and concurrent branches would otherwise overwrite each other. The
 * SharedBag is still one object across every branch.
 */
class AsyncParallelBatchFlow extends AsyncFlow
{
    protected bool $isolatesNodes = true;

    /**
     * @param BaseNode|null $startNode The node where execution begins
     * @param int|null $concurrency Maximum branches in flight, or null for no limit
     * @param AsyncRuntime|null $runtime Runtime handed to every member of the graph
     */
    public function __construct(
        ?BaseNode $startNode = null,
        protected ?int $concurrency = null,
        ?AsyncRuntime $runtime = null,
    ) {
        parent::__construct($startNode, $runtime);
    }

    /**
     * @param SharedBag $shared The shared data store
     * @return string|null The action from postAsync()
     */
    public function _runAsync(SharedBag $shared): ?string
    {
        $runtime = $this->runtime();
        $paramList = $runtime->await($this->prepAsync($shared)) ?? [];
        $branches = [];

        foreach ($paramList as $key => $batchParams) {
            $branches[$key] = fn () => $this->_orchestrateAsync($shared, array_merge($this->params, $batchParams));
        }

        $runtime->await($runtime->all($branches, $this->concurrency));

        return $runtime->await($this->postAsync($shared, $paramList, null));
    }
}
