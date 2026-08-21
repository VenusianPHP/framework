<?php

namespace Tests\Collections\Fixtures;

class TestSupportCollectionHigherOrderStaticClass2
{
    public static function transform($name)
    {
        return trim(chunk_split($name, 1, ' '));
    }

    public static function matches($name)
    {
        return str_starts_with($name, 'O');
    }
}
