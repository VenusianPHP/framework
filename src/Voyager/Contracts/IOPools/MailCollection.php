<?php

namespace Voyager\Contracts\IOPools;

use Voyager\NutsAndBolts\Collection;

interface MailCollection
{
    public function mail(): Collection;
    
}