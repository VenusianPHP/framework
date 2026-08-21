<?php

namespace Tests\Vessel\Fixtures;

class VariadicPrimitive
{
    /**
     * @var array
     */
    public $params;

    public function __construct(...$params)
    {
        $this->params = $params;
    }
}
