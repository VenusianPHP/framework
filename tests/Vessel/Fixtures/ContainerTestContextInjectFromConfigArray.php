<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectFromConfigArray
{
    public $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }
}
