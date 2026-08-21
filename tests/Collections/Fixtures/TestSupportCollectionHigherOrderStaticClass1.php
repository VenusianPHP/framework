<?php

namespace Tests\Collections\Fixtures;

class TestSupportCollectionHigherOrderStaticClass1
{
    public static function transform($name)
    {
        return strtoupper($name);
    }

    public static function matches($name)
    {
        return str_starts_with($name, 'T');
    }
}
