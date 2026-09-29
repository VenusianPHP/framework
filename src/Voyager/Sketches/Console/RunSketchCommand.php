<?php
declare(strict_types=1);
namespace Voyager\Sketches\Console;

use Voyager\Console\Command;
use Voyager\Contracts\Sketches\SketchRegistry;
use Voyager\Sketches\Sketch;
use Voyager\Sketches\SketchRunner;

class RunSketchCommand extends Command
{
    public function __construct(
        protected string $sketch_name,
        string $sketch_description,
    ) {
        $this->signature = $sketch_name;
        $this->description = $sketch_description;

        parent::__construct();
    }

    public function handle(SketchRegistry $registry, SketchRunner $runner): int
    {
        $sketch = $registry->resolve($this->sketch_name);

        if ($sketch instanceof Sketch) {
            $sketch->configureIO($this->input, $this->output);
        }

        return $runner->run($sketch);
    }
}
