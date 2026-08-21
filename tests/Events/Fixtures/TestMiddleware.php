<?php

namespace Tests\Events\Fixtures;

class TestMiddleware
{
    public $a;
    public $b;

    public function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    public function handle($job, $next)
    {
        $next($job);
    }
}
