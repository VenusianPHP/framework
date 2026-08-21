<?php

namespace Voyager\System\Exceptions;

use Closure;
use Exception;
use Voyager\Auth\Access\AuthorizationException;
use Voyager\Auth\AuthenticationException;
use Voyager\Cache\RateLimiter;
use Voyager\Cache\RateLimiting\Limit;
use Voyager\Cache\RateLimiting\Unlimited;
use Voyager\Console\View\Components\BulletList;
use Voyager\Console\View\Components\Error;
use Voyager\Contracts\Vessel\Vessel;
use Voyager\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Voyager\Contracts\Debug\ShouldntReport;
use Voyager\Contracts\System\ExceptionRenderer;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\MultipleRecordsFoundException;
use Voyager\Database\RecordNotFoundException;
use Voyager\Database\RecordsNotFoundException;
use Voyager\System\Exceptions\Renderer\Renderer;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Collection;
use Voyager\MagicAliases\Auth;
use Voyager\NutsAndBolts\Lottery;
use Voyager\Reflection\Reflector;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\Concerns\ReflectsClosures;
use Voyager\NutsAndBolts\ViewErrorBag;
use Voyager\Validation\ValidationException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Throwable;
use WeakMap;

class Handler implements ExceptionHandlerContract
{
    use ReflectsClosures;

    /**
     * The container implementation.
     *
     * @var \Voyager\Contracts\Vessel\Vessel
     */
    protected $container;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [];

    /**
     * The callbacks that inspect exceptions to determine if they should be reported.
     *
     * @var array
     */
    protected $dontReportCallbacks = [];

    /**
     * The callbacks that should be used during reporting.
     *
     * @var \Voyager\System\Exceptions\ReportableHandler[]
     */
    protected $reportCallbacks = [];

    /**
     * A map of exceptions with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [];

    /**
     * The callbacks that should be used to throttle reportable exceptions.
     *
     * @var array
     */
    protected $throttleCallbacks = [];

    /**
     * The callbacks that should be used to build exception context data.
     *
     * @var array
     */
    protected $contextCallbacks = [];


    /**
     * The callback that determines if the exception handler response should be JSON.
     *
     * @var callable|null
     */
    protected $shouldRenderJsonWhenCallback;

    /**
     * The callback that prepares responses to be returned to the browser.
     *
     * @var callable|null
     */
    protected $finalizeResponseCallback;

    /**
     * The registered exception mappings.
     *
     * @var array<string, \Closure>
     */
    protected $exceptionMap = [];

    /**
     * Indicates that throttled keys should be hashed.
     *
     * @var bool
     */
    protected $hashThrottleKeys = true;

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $internalDontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        BackedEnumCaseNotFoundException::class,
        ModelNotFoundException::class,
        MultipleRecordsFoundException::class,
        RecordNotFoundException::class,
        RecordsNotFoundException::class,
        TokenMismatchException::class,
        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Indicates that an exception instance should only be reported once.
     *
     * @var bool
     */
    protected $withoutDuplicates = false;

    /**
     * The already reported exception map.
     *
     * @var \WeakMap
     */
    protected $reportedExceptionMap;

    /**
     * Create a new exception handler instance.
     *
     * @param  \Voyager\Contracts\Vessel\Vessel  $container
     */
    public function __construct(Vessel $container)
    {
        $this->container = $container;

        $this->reportedExceptionMap = new WeakMap;

        $this->register();
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Register a reportable callback.
     *
     * @param  callable  $reportUsing
     * @return \Voyager\System\Exceptions\ReportableHandler
     */
    public function reportable(callable $reportUsing): \Voyager\System\Exceptions\ReportableHandler
    {
        if (! $reportUsing instanceof Closure) {
            $reportUsing = Closure::fromCallable($reportUsing);
        }

        return tap(new ReportableHandler($reportUsing), function ($callback) {
            $this->reportCallbacks[] = $callback;
        });
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
        if (is_string($to)) {
            $to = fn ($exception) => new $to('', 0, $exception);
        }

        if (is_callable($from) && is_null($to)) {
            $from = $this->firstClosureParameterType($to = $from);
        }

        if (! is_string($from) || ! $to instanceof Closure) {
            throw new InvalidArgumentException('Invalid exception mapping.');
        }

        $this->exceptionMap[$from] = $to;

        return $this;
    }

    /**
     * Indicate that the given exception type should not be reported.
     *
     * Alias of "ignore".
     *
     * @param  array|string  $exceptions
     * @return $this
     */
    public function dontReport(array|string $exceptions): static
    {
        return $this->ignore($exceptions);
    }

    /**
     * Register a callback to determine if an exception should not be reported.
     *
     * @param  (callable(\Throwable): bool)  $dontReportWhen
     * @return $this
     */
    public function dontReportWhen(callable $dontReportWhen): static
    {
        if (! $dontReportWhen instanceof Closure) {
            $dontReportWhen = Closure::fromCallable($dontReportWhen);
        }

        $this->dontReportCallbacks[] = $dontReportWhen;

        return $this;
    }

    /**
     * Indicate that the given exception type should not be reported.
     *
     * @param  array|string  $exceptions
     * @return $this
     */
    public function ignore(array|string $exceptions): static
    {
        $exceptions = Arr::wrap($exceptions);

        $this->dontReport = array_values(array_unique(array_merge($this->dontReport, $exceptions)));

        return $this;
    }

    /**
     * Set the log level for the given exception type.
     *
     * @param  class-string<\Throwable>  $type
     * @param  \Psr\Log\LogLevel::*  $level
     * @return $this
     */
    public function level($type, $level): static
    {
        $this->levels[$type] = $level;

        return $this;
    }

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e): void
    {
        $e = $this->mapException($e);

        if ($this->shouldntReport($e)) {
            return;
        }

        $this->reportThrowable($e);
    }

    /**
     * Reports error based on report method on exception or to logger.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    protected function reportThrowable(Throwable $e): void
    {
        $this->reportedExceptionMap[$e] = true;

        if (Reflector::isCallable($reportCallable = [$e, 'report']) &&
            $this->container->call($reportCallable) !== false) {
            return;
        }

        foreach ($this->reportCallbacks as $reportCallback) {
            if ($reportCallback->handles($e) && $reportCallback($e) === false) {
                return;
            }
        }

        try {
            $logger = $this->newLogger();
        } catch (Exception) {
            throw $e;
        }

        $level = $this->mapLogLevel($e);

        $context = $this->buildExceptionContext($e);

        method_exists($logger, $level)
            ? $logger->{$level}($e->getMessage(), $context)
            : $logger->log($level, $e->getMessage(), $context);
    }

    /**
     * Map the exception using a registered mapper if possible.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function mapException(Throwable $e): Throwable
    {
        if (method_exists($e, 'getInnerException') &&
            ($inner = $e->getInnerException()) instanceof Throwable) {
            return $inner;
        }

        foreach ($this->exceptionMap as $class => $mapper) {
            if (is_a($e, $class)) {
                return $mapper($e);
            }
        }

        return $e;
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        return ! $this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e): bool
    {
        if ($this->withoutDuplicates && ($this->reportedExceptionMap[$e] ?? false)) {
            return true;
        }

        if ($e instanceof ShouldntReport) {
            return true;
        }

        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        if (! is_null(Arr::first($dontReport, fn ($type) => $e instanceof $type))) {
            return true;
        }

        foreach ($this->dontReportCallbacks as $dontReportCallback) {
            if ($dontReportCallback($e) === true) {
                return true;
            }
        }

        return rescue(fn () => with($this->throttle($e), function ($throttle) use ($e) {
            if ($throttle instanceof Unlimited || $throttle === null) {
                return false;
            }

            if ($throttle instanceof Lottery) {
                return ! $throttle($e);
            }

            return ! $this->container->make(RateLimiter::class)->attempt(
                with($throttle->key ?: 'illuminate:foundation:exceptions:'.$e::class, fn ($key) => $this->hashThrottleKeys ? hash('xxh128', $key) : $key),
                $throttle->maxAttempts,
                fn () => true,
                $throttle->decaySeconds
            );
        }), rescue: false, report: false);
    }

    /**
     * Throttle the given exception.
     *
     * @param  \Throwable  $e
     * @return \Voyager\NutsAndBolts\Lottery|\Voyager\Cache\RateLimiting\Limit|null
     */
    protected function throttle(Throwable $e): \Voyager\NutsAndBolts\Lottery|\Voyager\Cache\RateLimiting\Limit|null
    {
        foreach ($this->throttleCallbacks as $throttleCallback) {
            foreach ($this->firstClosureParameterTypes($throttleCallback) as $type) {
                if (is_a($e, $type)) {
                    $response = $throttleCallback($e);

                    if (! is_null($response)) {
                        return $response;
                    }
                }
            }
        }

        return Limit::none();
    }

    /**
     * Specify the callback that should be used to throttle reportable exceptions.
     *
     * @param  callable  $throttleUsing
     * @return $this
     */
    public function throttleUsing(callable $throttleUsing): static
    {
        if (! $throttleUsing instanceof Closure) {
            $throttleUsing = Closure::fromCallable($throttleUsing);
        }

        $this->throttleCallbacks[] = $throttleUsing;

        return $this;
    }

    /**
     * Remove the given exception class from the list of exceptions that should be ignored.
     *
     * @param  array|string  $exceptions
     * @return $this
     */
    public function stopIgnoring(array|string $exceptions): static
    {
        $exceptions = Arr::wrap($exceptions);

        $this->dontReport = (new Collection($this->dontReport))
            ->reject(fn ($ignored) => in_array($ignored, $exceptions))
            ->values()
            ->all();

        $this->internalDontReport = (new Collection($this->internalDontReport))
            ->reject(fn ($ignored) => in_array($ignored, $exceptions))
            ->values()
            ->all();

        return $this;
    }

    /**
     * Create the context array for logging the given exception.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function buildExceptionContext(Throwable $e): array
    {
        return array_merge(
            $this->exceptionContext($e),
            $this->context(),
            ['exception' => $e]
        );
    }

    /**
     * Get the default exception context variables for logging.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function exceptionContext(Throwable $e): array
    {
        $context = [];

        if (method_exists($e, 'context')) {
            $context = $e->context();
        }

        foreach ($this->contextCallbacks as $callback) {
            $context = array_merge($context, $callback($e, $context));
        }

        return $context;
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context(): array
    {
        try {
            return array_filter([
                'userId' => Auth::id(),
            ]);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Register a closure that should be used to build exception context data.
     *
     * @param  \Closure  $contextCallback
     * @return $this
     */
    public function buildContextUsing(Closure $contextCallback): static
    {
        $this->contextCallbacks[] = $contextCallback;

        return $this;
    }

    /**
     * Render an exception to the console.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  \Throwable  $e
     * @return void
     *
     * @internal This method is not meant to be used or overwritten outside the framework.
     */
    public function renderForConsole(\Symfony\Component\Console\Output\OutputInterface $output, Throwable $e): void
    {
        if ($e instanceof CommandNotFoundException) {
            $message = Str::of($e->getMessage())->explode('.')->first();

            if (! empty($alternatives = $e->getAlternatives())) {
                $message .= '. Did you mean one of these?';

                (new Error($output))->render($message);
                (new BulletList($output))->render($alternatives);

                $output->writeln('');
            } else {
                (new Error($output))->render($message);
            }

            return;
        }

        (new ConsoleApplication)->renderThrowable($e, $output);
    }

    /**
     * Do not report duplicate exceptions.
     *
     * @return $this
     */
    public function dontReportDuplicates(): static
    {
        $this->withoutDuplicates = true;

        return $this;
    }

    /**
     * Map the exception to a log level.
     *
     * @param  \Throwable  $e
     * @return \Psr\Log\LogLevel::*
     */
    protected function mapLogLevel(Throwable $e)
    {
        return Arr::first(
            $this->levels, fn ($level, $type) => $e instanceof $type, LogLevel::ERROR
        );
    }

    /**
     * Create a new logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function newLogger(): \Psr\Log\LoggerInterface
    {
        return $this->container->make(LoggerInterface::class);
    }
}
