<?php

namespace Voyager\Workflows;

enum AsyncRuntimeDriver: string
{
    case LOOP = 'loop';            // the application's event loop: shares turns with everything else
}
