<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchExitStatus;
use Voyager\Contracts\Sketches\SketchLoopResult;

class SketchRunner
{
    public const string RATE_KEY = 'sketches.refresh_rate';
    public const float MIN_HZ = 1.0;

    protected bool $shutdown_invoked = false;

    public function __construct(
        protected Loop $loop,
        protected Repository $config,
    ) {}

    public function run(Sketch $sketch): int
    {
        $this->shutdown_invoked = false;

        if (! is_null($hz = $sketch->refreshRate())) {
            $this->config->set(self::RATE_KEY, $hz);
        }

        // the loop's finally covers stop()/signals/tick throws; the outer finally covers boot()
        $this->loop->onStop(fn () => $this->shutdownOnce($sketch));

        try {
            $sketch->boot();
            $this->arm($sketch);

            return $this->loop->run();
        } finally {
            $this->shutdownOnce($sketch);
        }
    }

    public function stop(int $status = 0): void
    {
        $this->loop->stop($status);
    }

    protected function arm(Sketch $sketch): void
    {
        $this->loop->at($this->period(), function () use ($sketch): void {
            if ($sketch->loop() === SketchLoopResult::STOP) {
                $this->loop->stop(SketchExitStatus::SUCCESS->value);
                return;
            }
            $this->arm($sketch);
        });
    }

    protected function period(): float
    {
        $hz = (float) $this->config->get(self::RATE_KEY, 60);

        return 1 / max($hz, self::MIN_HZ);
    }

    protected function shutdownOnce(Sketch $sketch): void
    {
        if ($this->shutdown_invoked) {
            return;
        }
        $this->shutdown_invoked = true;
        $sketch->shutdown();
    }
}
