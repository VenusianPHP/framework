<?php

namespace Voyager\Database\Instrument;

use OutOfBoundsException;

class MissingAttributeException extends OutOfBoundsException
{
    /**
     * Create a new missing attribute exception instance.
     *
     * @param  \Voyager\Database\Instrument\Model  $model
     * @param  string  $key
     */
    public function __construct($model, $key)
    {
        parent::__construct(sprintf(
            'The attribute [%s] either does not exist or was not retrieved for model [%s].',
            $key, get_class($model)
        ));
    }
}
