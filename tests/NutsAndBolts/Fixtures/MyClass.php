<?php

namespace Tests\NutsAndBolts\Fixtures;

class MyClass
{
    public function rand()
    {
        return once(fn () => rand(1, PHP_INT_MAX));
    }

    public static function staticRand()
    {
        return once(fn () => rand(1, PHP_INT_MAX));
    }

    public function callRand()
    {
        return once(fn () => $this->rand());
    }
}
