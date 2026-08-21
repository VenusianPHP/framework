<?php

namespace Tests\System\Stubs;

use Voyager\View\Component;

class PublicPropertyComponent extends Component
{
    public $foo = 'bar';

    public function speak()
    {
        return 'hello';
    }

    public function render()
    {
        return 'rendered content';
    }
}
