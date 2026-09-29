<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\IOPools\MailHandlers\SketchMailHandler;
use Voyager\Contracts\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchExitStatus;
use Voyager\Contracts\Sketches\SketchLoopResult;

class SketchRunner
{
    public const string RATE_KEY = 'sketches.refresh_rate';
    public const float MIN_HZ = 1.0;

    /** @var \WeakMap<Sketch, true> the sketches shut down already: onStop hooks outlive the run that added them */
    protected \WeakMap $shut_down;

    /**
     * @param SketchMailHandler|null $mail the loop's mail, held for the sketch; null when the loop hands its mail elsewhere
     */
    public function __construct(
        protected Loop $loop,
        protected Repository $config,
        protected ?SketchMailHandler $mail = null,
    ) {
        $this->shut_down = new \WeakMap();
    }

    public function run(Sketch $sketch): int
    {
        unset($this->shut_down[$sketch]);

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
            if ($sketch->loop($this->mail?->take() ?? []) === SketchLoopResult::STOP) {
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
        if (isset($this->shut_down[$sketch])) {
            return;
        }

        $this->shut_down[$sketch] = true;
        $sketch->shutdown();
    }
}
