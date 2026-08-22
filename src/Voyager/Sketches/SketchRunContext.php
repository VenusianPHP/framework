<?php

namespace Voyager\Sketches;

use Voyager\Console\OutputStyle;
use Voyager\Contracts\Sketches\Sketch;
use Symfony\Component\Console\Input\InputInterface;

class SketchRunContext
{
    /**
     * @param  array<string, mixed>  $shared
     */
    public function __construct(
        public string $name,
        public Sketch $sketch,
        public SketchRunner $runner,
        public ?InputInterface $input = null,
        public ?OutputStyle $output = null,
        public array $shared = [],
        public int $exitStatus = 0,
    ) {}
}
