<?php

namespace Tests\Vessel\Fixtures;

class CircularAStub
{
    public function __construct(CircularBStub $b)
    {
        //
    }
}
