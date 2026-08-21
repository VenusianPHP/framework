<?php

namespace Tests\Log\Fixtures;

use Voyager\Contracts\NutsAndBolts\Arrayable;

class SpyingArrayable implements Arrayable
{
    public bool $wasCalled = false;

    public function toArray(): array
    {
        $this->wasCalled = true;

        return ['serialized' => 'data'];
    }
}
