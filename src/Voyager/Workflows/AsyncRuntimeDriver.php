<?php

namespace Voyager\Workflows;

enum AsyncRuntimeDriver: string
{
    case SYNC = 'sync';
    case FIBER = 'fiber';
    case REACT = 'react';
}
