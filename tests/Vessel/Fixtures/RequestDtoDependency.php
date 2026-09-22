<?php

namespace Venusian\Tests\Vessel\Fixtures;

class RequestDtoDependency implements RequestDtoDependencyContract
{
    public int $userId;

    public function __construct()
    {
        $this->userId = $_SERVER['__withFactory.userId'];
    }
}
