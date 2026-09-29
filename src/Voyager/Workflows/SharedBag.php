<?php

namespace Voyager\Workflows;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class SharedBag
{
    public function put(string $key, mixed $value): void
    {
        $this->$key = $value;
    }
}
