<?php

namespace Voyager\Workflows;

class ConditionalTransition
{
    public function __construct(
        public BaseNode $src,
        public string $action,
    ) {}

    /**
     * Defines the target node for this transition.
     *
     * @param BaseNode|null $target The node to transition to, or null to remove
     * @return BaseNode|null The target node that was set
     */
    public function next(?BaseNode $target): ?BaseNode
    {
        return $this->src->next($target, $this->action);
    }
}