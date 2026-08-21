<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class HiddenPropertyCommand extends Command
{
    protected bool $hidden = true;

    public function parentIsHidden()
    {
        return parent::isHidden();
    }
}
