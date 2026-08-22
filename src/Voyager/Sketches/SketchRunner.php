<?php

namespace Voyager\Sketches;

use Voyager\Contracts\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchExitStatus;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Throwable;

class SketchRunner
{
    /**
     * Indicates whether a cooperative stop has been requested.
     */
    protected bool $shouldStop = false;

    /**
     * Indicates whether shutdown() has already been invoked for the active run.
     */
    protected bool $shutdownInvoked = false;

    /**
     * Boot once, tick loop() until STOP or stop(), then shutdown exactly once.
     *
     * @throws Throwable
     */
    public function run(Sketch $sketch): int
    {
        $this->shouldStop = false;
        $this->shutdownInvoked = false;

        $this->listenForSignals();

        try {
            $sketch->boot();

            while (! $this->shouldStop) {
                if ($sketch->loop() === SketchLoopResult::STOP) {
                    break;
                }
            }

            return SketchExitStatus::SUCCESS->value;
        } finally {
            $this->shutdownOnce($sketch);
        }
    }

    /**
     * Request a cooperative stop after the current loop tick.
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * Determine whether a cooperative stop has been requested.
     */
    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }

    /**
     * Invoke shutdown exactly once for the active run.
     */
    protected function shutdownOnce(Sketch $sketch): void
    {
        if ($this->shutdownInvoked) {
            return;
        }

        $this->shutdownInvoked = true;
        $sketch->shutdown();
    }

    /**
     * Listen for process termination signals when available.
     */
    protected function listenForSignals(): void
    {
        if (! $this->supportsSignals()) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->stop();
            });
        }
    }

    /**
     * Determine whether async signal handling is available.
     */
    protected function supportsSignals(): bool
    {
        return extension_loaded('pcntl');
    }
}
