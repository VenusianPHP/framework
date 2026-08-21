<?php

namespace Tests\Http\Stubs;

use Voyager\Http\Client\Response;

class BodyTrackingResponse extends Response
{
    public int $bodyCallCount = 0;

    public function body()
    {
        $this->bodyCallCount++;

        return parent::body();
    }
}
