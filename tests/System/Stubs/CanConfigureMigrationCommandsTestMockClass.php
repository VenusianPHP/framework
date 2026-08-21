<?php

namespace Tests\System\Stubs;

use Voyager\System\Testing\Traits\CanConfigureMigrationCommands;

class CanConfigureMigrationCommandsTestMockClass
{
    use CanConfigureMigrationCommands;

    public $dropViews = false;

    public $dropTypes = false;
}
