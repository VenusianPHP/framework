<?php

namespace Venusian\Tests\Vessel\Fixtures;

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
