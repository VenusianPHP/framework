<?php

namespace Voyager\Workflows;

/**
 * Maps execAsync() over the items returned by prepAsync(), one at a time.
 *
 * Each item gets the full retry and fallback treatment.
 */
class AsyncBatchNode extends AsyncNode
{
    /**
     * @param mixed $items The resolved items from prepAsync()
     * @throws \Throwable
     */
    public function _execAsync(mixed $items): mixed
    {
        $results = [];

        foreach ($items ?? [] as $key => $item) {
            $results[$key] = parent::_execAsync($item);
        }

        return $results;
    }
}
