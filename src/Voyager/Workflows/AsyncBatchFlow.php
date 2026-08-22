<?php

namespace Voyager\Workflows;

/**
 * Runs the graph once per param set returned by prepAsync(), one at a time.
 *
 * The batch loop lives in _runAsync() rather than runAsync(), so nesting this
 * flow inside another AsyncFlow still runs the whole param list.
 */
class AsyncBatchFlow extends AsyncFlow
{
    /**
     * @param SharedBag $shared The shared data store
     * @return string|null The action from postAsync()
     */
    public function _runAsync(SharedBag $shared): ?string
    {
        $runtime = $this->runtime();
        $paramList = $runtime->await($this->prepAsync($shared)) ?? [];

        foreach ($paramList as $batchParams) {
            $runtime->await($this->_orchestrateAsync($shared, array_merge($this->params, $batchParams)));
        }

        return $runtime->await($this->postAsync($shared, $paramList, null));
    }
}
