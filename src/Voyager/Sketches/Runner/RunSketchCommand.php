<?php

namespace Voyager\Sketches\Runner;

use Voyager\Console\OutputStyle;
use Voyager\Contracts\Sketches\SketchRegistry;
use Voyager\Pipeline\Pipeline;
use Voyager\Sketches\Middleware\DispatchSketch;
use Voyager\Sketches\Sketch;
use Voyager\Sketches\SketchRunContext;
use Voyager\Sketches\SketchRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony command that runs one registered sketch through middleware + SketchRunner.
 */
class RunSketchCommand extends Command
{
    /**
     * @param  array<int, class-string|callable|object>  $globalMiddleware
     */
    public function __construct(
        protected string $sketchName,
        protected string $sketchDescription,
        protected SketchRegistry $registry,
        protected SketchRunner $runner,
        protected Pipeline $pipeline,
        protected array $globalMiddleware = [],
    ) {
        parent::__construct($sketchName);

        $this->setDescription($sketchDescription !== '' ? $sketchDescription : "Run the [{$sketchName}] sketch");
    }

    protected function configure(): void
    {
        parent::configure();

        $sketch = $this->registry->resolve($this->sketchName);

        if ($sketch instanceof Sketch) {
            $sketch->configureCommand($this);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sketch = $this->registry->resolve($this->sketchName);
        $style = new OutputStyle($input, $output);

        if ($sketch instanceof Sketch) {
            $sketch->configureIO($input, $style);
        }

        $middleware = array_values(array_merge(
            $this->globalMiddleware,
            $sketch instanceof Sketch ? $sketch->middleware() : [],
        ));

        $context = new SketchRunContext(
            name: $this->sketchName,
            sketch: $sketch,
            runner: $this->runner,
            input: $input,
            output: $style,
        );

        return (new DispatchSketch($this->pipeline, $this->runner, $middleware))->run($context);
    }
}
