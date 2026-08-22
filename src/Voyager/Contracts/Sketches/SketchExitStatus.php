<?php

namespace Voyager\Contracts\Sketches;

enum SketchExitStatus: int
{
    case SUCCESS = 0;
    case FAILURE = 1;
}
