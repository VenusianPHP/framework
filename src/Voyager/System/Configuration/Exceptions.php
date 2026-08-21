<?php

namespace Voyager\System\Configuration;

use Closure;
use Voyager\System\Exceptions\Handler;
use Voyager\NutsAndBolts\DataObjects\Arr;

class Exceptions
{
    /**
     * Create a new exception handling configuration instance.
     *
     * @param  \Voyager\System\Exceptions\Handler  $handler
     */
    public function __construct(public Handler $handler)
    {
    }

    /**
     * Register a reportable callback.
     *
     * @param  callable  $using
     * @return \Voyager\System\Exceptions\ReportableHandler
     */
    public function report(callable $using): \Voyager\System\Exceptions\ReportableHandler
    {
        return $this->handler->reportable($using);
    }

    /**
     * Register a reportable callback.
     *
     * @param  callable  $reportUsing
     * @return \Voyager\System\Exceptions\ReportableHandler
     */
    public function reportable(callable $reportUsing): \Voyager\System\Exceptions\ReportableHandler
    {
        return $this->handler->reportable($reportUsing);
    }

    /**
     * Specify the callback that should be used to throttle reportable exceptions.
     *
     * @param  callable  $throttleUsing
     * @return $this
     */
    public function throttle(callable $throttleUsing): static
    {
        $this->handler->throttleUsing($throttleUsing);

        return $this;
    }

    /**
     * Register a new exception mapping.
     *
     * @param  \Closure|string  $from
     * @param  \Closure|string|null  $to
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function map(\Closure|string $from, \Closure|string|null $to = null): static
    {
        $this->handler->map($from, $to);

        return $this;
    }

    /**
     * Set the log level for the given exception type.
     *
     * @param  class-string<\Throwable>  $type
     * @param  \Psr\Log\LogLevel::*  $level
     * @return $this
     */
    public function level(string $type, string $level): static
    {
        $this->handler->level($type, $level);

        return $this;
    }

    /**
     * Register a closure that should be used to build exception context data.
     *
     * @param  \Closure  $contextCallback
     * @return $this
     */
    public function context(Closure $contextCallback): static
    {
        $this->handler->buildContextUsing($contextCallback);

        return $this;
    }

    /**
     * Indicate that the given exception type should not be reported.
     *
     * @param  array|string  $class
     * @return $this
     */
    public function dontReport(array|string $class): static
    {
        foreach (Arr::wrap($class) as $exceptionClass) {
            $this->handler->dontReport($exceptionClass);
        }

        return $this;
    }

    /**
     * Register a callback to determine if an exception should not be reported.
     *
     * @param  (\Closure(\Throwable): bool)  $dontReportWhen
     * @return $this
     */
    public function dontReportWhen(Closure $dontReportWhen): static
    {
        $this->handler->dontReportWhen($dontReportWhen);

        return $this;
    }

    /**
     * Do not report duplicate exceptions.
     *
     * @return $this
     */
    public function dontReportDuplicates(): static
    {
        $this->handler->dontReportDuplicates();

        return $this;
    }

    /**
     * Indicate that the given exception class should not be ignored.
     *
     * @param  array<int, class-string<\Throwable>>|class-string<\Throwable>  $class
     * @return $this
     */
    public function stopIgnoring(array|string $class): static
    {
        $this->handler->stopIgnoring($class);

        return $this;
    }

}
