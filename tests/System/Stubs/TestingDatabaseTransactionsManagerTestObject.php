<?php

namespace Tests\System\Stubs;

class TestingDatabaseTransactionsManagerTestObject
{
    public $ran = false;

    public $runs = 0;

    public function handle()
    {
        $this->ran = true;
        $this->runs++;
    }
}
