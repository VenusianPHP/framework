<?php

use Voyager\Core\Bus\PendingClosureDispatch;
use Voyager\Core\Bus\PendingDispatch;
use Voyager\Queue\CallQueuedClosure;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Vessel\ControlPanel;

if (! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @template TClass of object
     *
     * @param string|class-string<TClass>|null $abstract
     * @return ($abstract is class-string<TClass> ? TClass : ($abstract is null ? \Voyager\Core\RenderedInstance : mixed))
     * @throws ReflectionException
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        if (is_null($abstract)) {
            return ControlPanel::getInstance();
        }

        return ControlPanel::getInstance()->make($abstract, $parameters);
    }
}

if (! function_exists('app_path')) {
    /**
     * Get the path to the application folder.
     *
     * @param string $path
     * @return string
     * @throws ReflectionException
     */
    function app_path(string $path = ''): string
    {
        return app()->path($path);
    }
}

if (! function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param string $path
     * @throws ReflectionException
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('database_path')) {
    /**
     * Get the path to the database directory.
     *
     * @param string $path
     * @throws ReflectionException
     */
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}


if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param string|array<string, mixed>|null $key
     * @param mixed $default
     * @return ($key is null ? \Voyager\Config\Repository : ($key is string ? mixed : null))
     * @throws ReflectionException
     */
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('config');
        }

        if (is_array($key)) {
            return app('config')->set($key);
        }

        return app('config')->get($key, $default);
    }
}

if (! function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param string $path
     * @throws ReflectionException
     */
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (! function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param  mixed  $job
     * @return ($job is Closure ? \Voyager\Core\Bus\PendingClosureDispatch : \Voyager\Core\Bus\PendingDispatch)
     */
    function dispatch($job): PendingDispatch|PendingClosureDispatch
    {
        return $job instanceof Closure
            ? new PendingClosureDispatch(CallQueuedClosure::create($job))
            : new PendingDispatch($job);
    }
}

if (! function_exists('rescue')) {
    /**
     * Catch a potential exception and return a default value.
     *
     * @template TValue
     * @template TFallback
     *
     * @param callable(): TValue $callback
     * @param (callable(Throwable): TFallback)|null $rescue
     * @param callable(Throwable): bool|bool $report
     * @return TValue|TFallback
     * @throws ReflectionException
     * @throws Throwable
     */
    function rescue(callable $callback, callable|bool|null $rescue = null, callable|bool $report = true): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (value($report, $e)) {
                report($e);
            }

            return value($rescue, $e);
        }
    }
}

if (! function_exists('report')) {
    /**
     * Report an exception.
     *
     * @param Throwable|string $exception
     * @throws ReflectionException
     * @throws Throwable
     */
    function report(\Throwable|string $exception): void
    {
        if (is_string($exception)) {
            $exception = new Exception($exception);
        }

        app(ExceptionHandler::class)->report($exception);
    }
}
