<?php

namespace Voyager\Workflows;

use Voyager\Contracts\Workflows\AsyncRunnable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;

/**
 * Orchestrates a graph of nodes.
 *
 * A Flow manages the execution of a directed graph of nodes. It starts
 * from a designated start node and follows transitions based on the
 * action returned by each node's post() method.
 */
class Flow extends BaseNode
{
    /**
     * @param BaseNode|null $startNode The node where execution begins
     */
    public function __construct(protected ?BaseNode $startNode = null) {}

    /**
     * Set the starting node for this flow.
     *
     * @param BaseNode $startNode The node where execution should begin
     * @return BaseNode The start node (for chaining)
     */
    public function start(BaseNode $startNode): BaseNode
    {
        $this->startNode = $startNode;
        return $startNode;
    }

    /**
     * Determine the next node based on the current node and action.
     *
     * @param BaseNode|null $current The current node
     * @param string|null $action The action returned by the current node
     * @return BaseNode|null The next node to execute, or null to stop
     */
    protected function getNextNode(?BaseNode $current, ?string $action): ?BaseNode
    {
        if (!$current) return null;

        $actionKey = $action ?? 'default';
        $successors = $current->getSuccessors();

        if (!array_key_exists($actionKey, $successors)) {
            // Flow stops because no successor is defined for this action.
            return null;
        }

        return $successors[$actionKey];
    }

    /**
     * Orchestrate the execution of nodes in the graph.
     *
     * @param SharedBag $shared The shared data store
     * @param array|null $params Optional runtime parameters
     * @return string|null The final action returned by the last node
     * @throws WorkflowRuntimeException If an async node is encountered
     */
    protected function _orchestrate(SharedBag $shared, ?array $params = null): ?string
    {
        $current = $this->startNode;
        $p = $params ?? $this->params;
        $lastAction = null;

        while ($current) {
            $current->setParams($p);
            if ($current instanceof AsyncRunnable) {
                throw new WorkflowRuntimeException("Synchronous Flow cannot contain async nodes. Use AsyncFlow instead.");
            }
            $lastAction = $current->_run($shared);
            $current = $this->getNextNode($current, $lastAction);
        }
        return $lastAction;
    }

    /**
     * Internal run method that orchestrates the flow.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The final action
     */
    protected function _run(SharedBag $shared): ?string
    {
        $prepResult = $this->prep($shared);
        $orchestrationResult = $this->_orchestrate($shared);
        return $this->post($shared, $prepResult, $orchestrationResult);
    }

    /**
     * Process results after orchestration completes.
     *
     * @param SharedBag $shared The shared data store
     * @param mixed $prepRes The result from prep()
     * @param mixed $execRes The result from orchestration (the final action)
     * @return string|null Unchanged result
     */
    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return $execRes;
    }
}