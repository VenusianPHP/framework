<?php

namespace Voyager\Contracts\Sketches;

enum SketchLoopResult: string
{
    case CONTINUE = 'continue';
    case STOP = 'stop';
}
