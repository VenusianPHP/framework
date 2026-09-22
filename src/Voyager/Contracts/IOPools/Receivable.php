<?php

namespace Voyager\Contracts\IOPools;

use Voyager\NutsAndBolts\Collection;

interface Receivable
{
    public function handOff(MailCollection $mail);
    
}