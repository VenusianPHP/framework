<?php

namespace Tests\Bus\Fixtures;

class StandAloneHandler
{
    public function handle(StandAloneCommand $command)
    {
        return $command;
    }
}
