<?php

namespace Tests\System\Stubs;

use Voyager\View\Component;

class RenderingComponent extends Component
{
    public function render()
    {
        return 'rendered content';
    }
}
