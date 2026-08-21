<?php

namespace Tests\System\Stubs;

trait TestTrait
{
    public $setUp = false;

    public $tearDown = false;

    public function setUpTestTrait()
    {
        $this->setUp = true;
    }

    public function tearDownTestTrait()
    {
        $this->tearDown = true;
    }
}
