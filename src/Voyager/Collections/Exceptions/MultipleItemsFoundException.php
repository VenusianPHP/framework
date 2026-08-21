<?php

namespace Voyager\NutsAndBolts\Exceptions;

use Throwable;
use Voyager\Contracts\System\VenusianFrameworkException;

class MultipleItemsFoundException extends VenusianFrameworkException
{
    /**
     * The number of items found.
     */
    public int $count;

    /**
     * Create a new exception instance.
     */
    public function __construct(int $count, int $code = 0, ?Throwable $previous = null)
    {
        $this->count = $count;

        parent::__construct("$count items were found.", $code, $previous);
    }

    /**
     * Get the number of items found.
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
