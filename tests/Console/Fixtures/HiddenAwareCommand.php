<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class HiddenAwareCommand extends Command
{
    public function parentIsHidden()
    {
        return parent::isHidden();
    }
}
