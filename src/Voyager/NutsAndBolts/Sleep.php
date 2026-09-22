<?php

namespace Voyager\NutsAndBolts;

use Voyager\NutsAndBolts\DataObjects\Carbon;

use Carbon\CarbonInterval;
use Closure;
use DateInterval;
use Voyager\NutsAndBolts\Concerns\Macroable;
use PHPUnit\Framework\Assert as PHPUnit;
use RuntimeException;

class Sleep
{
    use Macroable;

    /**
     * The fake sleep callbacks.
     *
     * @var array
     */
    public static $fakeSleepCallbacks = [];

    /**
     * Keep Carbon's "now" in sync when sleeping.
     *
     * @var bool
     */
    protected static $syncWithCarbon = false;

    /**
     * The total duration to sleep.
     *
     * @var \Carbon\CarbonInterval
     */
    public $duration;

    /**
     * The callback that determines if sleeping should continue.
     *
     * @var \Closure
     */
    public $while;

    /**
     * The pending duration to sleep.
     *
     * @var int|float|null
     */
    protected $pending = null;

    /**
     * Indicates that all sleeping should be faked.
     *
     * @var bool
     */
    protected static $fake = false;

    /**
     * The sequence of sleep durations encountered while faking.
     *
     * @var array<int, \Carbon\CarbonInterval>
     */
    protected static $sequence = [];

    /**
     * Indicates if the instance should sleep.
     *
     * @var bool
     */
    protected $shouldSleep = true;

    /**
     * Indicates if the instance already slept via `then()`.
     *
     * @var bool
     */
    protected $alreadySlept = false;

    /**
     * Create a new class instance.
     *
     * @param \DateInterval|float|int $duration
     */
    public function __construct(DateInterval|float|int $duration)
    {
        $this->duration($duration);
    }

    /**
     * Sleep for the given duration.
     *
     * @param \DateInterval|float|int $duration
     * @return static
     */
    public static function for(DateInterval|float|int $duration): static
    {
        return new static($duration);
    }

    /**
     * Sleep until the given timestamp.
     *
     * @param float|\DateTimeInterface|int|numeric-string $timestamp
     * @return static
     */
    public static function until(float|\DateTimeInterface|int|string $timestamp): static
    {
        if (is_numeric($timestamp)) {
            $timestamp = Carbon::createFromTimestamp($timestamp, date_default_timezone_get());
        }

        return new static(Carbon::now()->diff($timestamp));
    }

    /**
     * Sleep for the given number of microseconds.
     *
     * @param int $duration
     * @return static
     */
    public static function usleep(int $duration): static
    {
        return new static($duration)->microseconds();
    }

    /**
     * Sleep for the given number of seconds.
     *
     * @param float|int $duration
     * @return static
     */
    public static function sleep(float|int $duration): static
    {
        return new static($duration)->seconds();
    }

    /**
     * Sleep for the given duration. Replaces any previously defined duration.
     *
     * @param \DateInterval|float|int $duration
     * @return $this
     */
    protected function duration(DateInterval|float|int $duration): static
    {
        if (! $duration instanceof DateInterval) {
            $this->duration = CarbonInterval::microsecond(0);

            $this->pending = $duration;
        } else {
            $duration = CarbonInterval::instance($duration);

            if ($duration->totalMicroseconds < 0) {
                $duration = CarbonInterval::seconds(0);
            }

            $this->duration = $duration;
            $this->pending = null;
        }

        return $this;
    }

    /**
     * Sleep for the given number of minutes.
     *
     * @return $this
     */
    public function minutes(): static
    {
        $this->duration->add('minutes', $this->pullPending());

        return $this;
    }

    /**
     * Sleep for one minute.
     *
     * @return $this
     */
    public function minute(): static
    {
        return $this->minutes();
    }

    /**
     * Sleep for the given number of seconds.
     *
     * @return $this
     */
    public function seconds(): static
    {
        $this->duration->add('seconds', $this->pullPending());

        return $this;
    }

    /**
     * Sleep for one second.
     *
     * @return $this
     */
    public function second(): static
    {
        return $this->seconds();
    }

    /**
     * Sleep for the given number of milliseconds.
     *
     * @return $this
     */
    public function milliseconds(): static
    {
        $this->duration->add('milliseconds', $this->pullPending());

        return $this;
    }

    /**
     * Sleep for one millisecond.
     *
     * @return $this
     */
    public function millisecond(): static
    {
        return $this->milliseconds();
    }

    /**
     * Sleep for the given number of microseconds.
     *
     * @return $this
     */
    public function microseconds(): static
    {
        $this->duration->add('microseconds', $this->pullPending());

        return $this;
    }

    /**
     * Sleep for on microsecond.
     *
     * @return $this
     */
    public function microsecond(): static
    {
        return $this->microseconds();
    }

    /**
     * Add additional time to sleep for.
     *
     * @param float|int $duration
     * @return $this
     */
    public function and(float|int $duration): static
    {
        $this->pending = $duration;

        return $this;
    }

    /**
     * Sleep while a given callback returns "true".
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function while(Closure $callback): static
    {
        $this->while = $callback;

        return $this;
    }

    /**
     * Specify a callback that should be executed after sleeping.
     *
     * @param  callable  $then
     * @return mixed
     */
    public function then(callable $then): mixed
    {
        $this->goodnight();

        $this->alreadySlept = true;

        return $then();
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->goodnight();
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    protected function goodnight(): void
    {
        if ($this->alreadySlept || ! $this->shouldSleep) {
            return;
        }

        if ($this->pending !== null) {
            throw new RuntimeException('Unknown duration unit.');
        }

        if (static::$fake) {
            static::$sequence[] = $this->duration;

            if (static::$syncWithCarbon) {
                Carbon::setTestNow(Carbon::now()->add($this->duration));
            }

            foreach (static::$fakeSleepCallbacks as $callback) {
                $callback($this->duration);
            }

            return;
        }

        $remaining = $this->duration->copy();

        $seconds = (int) $remaining->totalSeconds;

        $while = $this->while ?: function () {
            static $return = [true, false];

            return array_shift($return);
        };

        while ($while()) {
            if ($seconds > 0) {
                sleep($seconds);

                $remaining = $remaining->subSeconds($seconds);
            }

            $microseconds = (int) $remaining->totalMicroseconds;

            if ($microseconds > 0) {
                usleep($microseconds);
            }
        }
    }

    /**
     * Resolve the pending duration.
     *
     * @return int|float
     */
    protected function pullPending(): float|int
    {
        if ($this->pending === null) {
            $this->shouldNotSleep();

            throw new RuntimeException('No duration specified.');
        }

        if ($this->pending < 0) {
            $this->pending = 0;
        }

        return tap($this->pending, function () {
            $this->pending = null;
        });
    }

    /**
     * Stay awake and capture any attempts to sleep.
     *
     * @param bool $value
     * @param bool $syncWithCarbon
     * @return void
     */
    public static function fake(bool $value = true, bool $syncWithCarbon = false): void
    {
        static::$fake = $value;

        static::$sequence = [];
        static::$fakeSleepCallbacks = [];
        static::$syncWithCarbon = $syncWithCarbon;
    }

    /**
     * Assert a given amount of sleeping occurred a specific number of times.
     *
     * @param \Closure $expected
     * @param int $times
     * @return void
     */
    public static function assertSlept(Closure $expected, int $times = 1): void
    {
        $count = new Collection(static::$sequence)->filter($expected)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected sleep was found [{$count}] times instead of [{$times}]."
        );
    }

    /**
     * Assert sleeping occurred a given number of times.
     *
     * @param int $expected
     * @return void
     */
    public static function assertSleptTimes(int $expected): void
    {
        PHPUnit::assertSame($expected, $count = count(static::$sequence), "Expected [{$expected}] sleeps but found [{$count}].");
    }

    /**
     * Assert the given sleep sequence was encountered.
     *
     * @param array $sequence
     * @return void
     */
    public static function assertSequence(array $sequence): void
    {
        try {
            static::assertSleptTimes(count($sequence));

            new Collection($sequence)
                ->zip(static::$sequence)
                ->eachSpread(function (?Sleep $expected, CarbonInterval $actual) {
                    if ($expected === null) {
                        return;
                    }

                    PHPUnit::assertTrue(
                        $expected->shouldNotSleep()->duration->equalTo($actual),
                        vsprintf('Expected sleep duration of [%s] but actually slept for [%s].', [
                            $expected->duration->cascade()->forHumans([
                                'options' => 0,
                                'minimumUnit' => 'microsecond',
                            ]),
                            $actual->cascade()->forHumans([
                                'options' => 0,
                                'minimumUnit' => 'microsecond',
                            ]),
                        ])
                    );
                });
        } finally {
            foreach ($sequence as $expected) {
                if ($expected instanceof self) {
                    $expected->shouldNotSleep();
                }
            }
        }
    }

    /**
     * Assert that no sleeping occurred.
     *
     * @return void
     */
    public static function assertNeverSlept(): void
    {
        static::assertSleptTimes(0);
    }

    /**
     * Assert that no sleeping occurred.
     *
     * @return void
     */
    public static function assertInsomniac(): void
    {
        if (static::$sequence === []) {
            PHPUnit::assertTrue(true);
        }

        foreach (static::$sequence as $duration) {
            PHPUnit::assertSame(0, (int) $duration->totalMicroseconds, vsprintf('Unexpected sleep duration of [%s] found.', [
                $duration->cascade()->forHumans([
                    'options' => 0,
                    'minimumUnit' => 'microsecond',
                ]),
            ]));
        }
    }

    /**
     * Indicate that the instance should not sleep.
     *
     * @return $this
     */
    protected function shouldNotSleep(): static
    {
        $this->shouldSleep = false;

        return $this;
    }

    /**
     * Only sleep when the given condition is true.
     *
     * @param bool|(\Closure($this): bool) $condition
     * @return $this
     */
    public function when(bool|Closure $condition): static
    {
        $this->shouldSleep = (bool) value($condition, $this);

        return $this;
    }

    /**
     * Don't sleep when the given condition is true.
     *
     * @param bool|(\Closure($this): bool) $condition
     * @return $this
     */
    public function unless(bool|Closure $condition): static
    {
        return $this->when(! value($condition, $this));
    }

    /**
     * Specify a callback that should be invoked when faking sleep within a test.
     *
     * @param callable $callback
     * @return void
     */
    public static function whenFakingSleep(callable $callback): void
    {
        static::$fakeSleepCallbacks[] = $callback;
    }

    /**
     * Indicate that Carbon's "now" should be kept in sync when sleeping.
     *
     * @return void
     */
    public static function syncWithCarbon($value = true): void
    {
        static::$syncWithCarbon = $value;
    }
}
