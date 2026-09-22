<?php
declare(strict_types=1);
namespace Voyager\Contracts\Sketches;

enum SketchExitStatus: int
{
    case SUCCESS = 0;
    case FAILURE = 1;
}
