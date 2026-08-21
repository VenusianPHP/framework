<?php

namespace Tests\NutsAndBolts\Fixtures;

function my_rand()
{
    return once(fn () => rand(1, PHP_INT_MAX));
}
