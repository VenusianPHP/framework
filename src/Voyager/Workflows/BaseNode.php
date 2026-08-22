<?php

namespace Voyager\Workflows;

use Voyager\Contracts\Workflows\WorkflowLogicException;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;

abstract class BaseNode
{
    /**
     * Runtime parameters for this node
     *
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * Map of action names to successor nodes
     *
     * @var array<string, BaseNode|null>
     */
    protected array $successors = [];

    /**
     * Set runtime parameters for this node.
     *
     * @param  array<string, mixed>  $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Define the successor node for a given action.
     *
     * @param BaseNode|null $node The node to transition to, or null to remove
     * @param string $action The action name (default: 'default')
     * @return BaseNode|null The successor node that was set
     * @throws LogicException If a successor already exists for this action
     */
    public function next(?BaseNode $node, string $action = 'default'): ?BaseNode
    {
        if (isset($this->successors[$action])) {
            throw new WorkflowLogicException("Cannot overwrite existing successor for action '{$action}'.");
        }

        $this->successors[$action] = $node;

        return $node;
    }

    /**
     * Start defining a conditional transition.
     *
     * @param string $action The action name to filter on
     * @return ConditionalTransition A helper for setting the target node
     */
    public function on(string $action): ConditionalTransition
    {
        return new ConditionalTransition($this, $action);
    }

    /**
     * Get all defined successors for this node.
     *
     * @return array<string, BaseNode|null> Map of action names to nodes
     */
    public function getSuccessors(): array
    {
        return $this->successors;
    }

    /**
     * Prepare data before execution.
     *
     * Override this method to perform setup work. The return value
     * is passed to exec().
     *
     * @param SharedBag $shared The shared data store
     * @return mixed Data to pass to exec()
     */
    public function prep(SharedBag $shared): mixed
    {
        return null;
    }

    /**
     * Execute the main logic of this node.
     *
     * Override this method to implement the node's core functionality.
     *
     * @param mixed $prepRes The result from prep()
     * @return mixed The result of execution
     */
    public function exec(mixed $prepRes): mixed
    {
        return null;
    }

    /**
     * Handle results after execution and decide next action.
     *
     * Override this method to process results and return an action
     * that determines which successor to run next.
     *
     * @param SharedBag $shared The shared data store
     * @param mixed $prepRes The result from prep()
     * @param mixed $execRes The result from exec()
     * @return string|null The action name to transition to, or null to stop
     */
    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }

    /**
     * Internal execution wrapper that handles retries.
     *
     * @param mixed $prepRes The result from prep()
     * @return mixed The result of execution
     */
    protected function _exec(mixed $prepRes): mixed
    {
        return $this->exec($prepRes);
    }

    /**
     * Internal run method that executes the full prep -> exec -> post lifecycle.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The action returned by post(), or null to stop
     */
    protected function _run(SharedBag $shared): ?string
    {
        $prepRes = $this->prep($shared);
        $execRes = $this->_exec($prepRes);
        return $this->post($shared, $prepRes, $execRes);
    }

    /**
     * Run this node in isolation.
     *
     * This should only be called on nodes without successors. For nodes
     * that are part of a graph, use a Flow to orchestrate execution.
     *
     * @param SharedBag $shared The shared data store
     * @return string|null The action returned by post()
     * @throws WorkflowRuntimeException If this node has successors
     */
    public function run(SharedBag $shared): ?string
    {
        if (!empty($this->successors)) {
            throw new WorkflowRuntimeException("Cannot run a node that has successors directly. Use a Flow to execute the full graph.");
        }
        return $this->_run($shared);
    }
}