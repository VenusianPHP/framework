<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use Symfony\Component\Console\Input\InputInterface;
use Voyager\Console\Concerns\InteractsWithIO;
use Voyager\Console\OutputStyle;
use Voyager\Contracts\Sketches\Sketch as SketchContract;
use Voyager\Contracts\Sketches\SketchLoopResult;

abstract class Sketch implements SketchContract
{
    use InteractsWithIO;

    protected string $description = '';

    // Hz. Null defers to config('sketches.refresh_rate').
    protected ?float $refresh_rate = null;

    public function configureIO(InputInterface $input, OutputStyle $output): void
    {
        $this->input = $input;
        $this->output = $output;
    }

    public function getDescription(): string { return $this->description; }

    public function refreshRate(): ?float { return $this->refresh_rate; }

    public function boot(): void {}

    abstract public function loop(): SketchLoopResult;

    public function shutdown(): void {}
}
