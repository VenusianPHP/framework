<?php

namespace Voyager\Sketches;

use Voyager\Contracts\Sketches\SketchRegistry;
use Voyager\Contracts\Vessel\Vessel;
use Voyager\Pipeline\Pipeline;
use ReflectionClass;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    /**
     * @param  array<int, class-string|callable|object>  $globalMiddleware
     */
    public function __construct(
        protected Vessel $container,
        protected SketchRegistry $registry,
        protected SketchRunner $runner,
        string $version,
        protected array $globalMiddleware = [],
    ) {
        parent::__construct('Venusian Runner', $version);

        $this->setAutoExit(false);
        $this->setCatchExceptions(false);

        $this->registerSketches();
    }

    protected function registerSketches(): void
    {
        foreach ($this->registry->all() as $name => $class) {
            $this->add(new Runner\RunSketchCommand(
                sketchName: $name,
                sketchDescription: $this->descriptionFor($class),
                registry: $this->registry,
                runner: $this->runner,
                pipeline: new Pipeline($this->container),
                globalMiddleware: $this->globalMiddleware,
            ));
        }
    }

    /**
     * @param  class-string  $class
     */
    protected function descriptionFor(string $class): string
    {
        try {
            $defaults = (new ReflectionClass($class))->getDefaultProperties();

            return is_string($defaults['description'] ?? null) ? $defaults['description'] : '';
        } catch (\ReflectionException) {
            return '';
        }
    }
}
