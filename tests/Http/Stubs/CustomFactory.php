<?php

namespace Tests\Http\Stubs;

use Voyager\Http\Client\Factory;
use Voyager\Http\Client\PendingRequest;

class CustomFactory extends Factory
{
    protected function newPendingRequest()
    {
        return new class extends PendingRequest
        {
            protected function newResponse($response)
            {
                return new TestResponse($response);
            }
        };
    }
}
