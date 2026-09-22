<?php
declare(strict_types=1);
namespace Voyager\Contracts\Sketches;

enum SketchLoopResult: string
{
    case CONTINUE = 'continue';
    case STOP = 'stop';
}
