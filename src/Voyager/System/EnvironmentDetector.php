<?php

namespace Voyager\System;

use Closure;

class EnvironmentDetector
{
    /**
     * Detect the application's current environment.
     *
     * @param  \Closure  $callback
     * @param  array|null  $consoleArgs
     * @return string
     */
    public function detect(Closure $callback, ?array $consoleArgs = null): string
    {
        if ($consoleArgs) {
            return $this->detectConsoleEnvironment($callback, $consoleArgs);
        }

        return $this->detectDefaultEnvironment($callback);
    }

    /**
     * Resolve the environment from the given callback.
     *
     * @param  \Closure  $callback
     * @return string
     */
    protected function detectDefaultEnvironment(Closure $callback): string
    {
        return $callback();
    }

    /**
     * Set the application environment from command-line arguments.
     *
     * @param  \Closure  $callback
     * @param  array  $args
     * @return string
     */
    protected function detectConsoleEnvironment(Closure $callback, array $args): string
    {
        // First we will check if an environment argument was passed via console arguments
        // and if it was that automatically overrides as the environment. Otherwise, we
        // fall back to the callback that supplies the configured environment.
        if (! is_null($value = $this->getEnvironmentArgument($args))) {
            return $value;
        }

        return $this->detectDefaultEnvironment($callback);
    }

    /**
     * Get the environment argument from the console.
     *
     * @param  array  $args
     * @return string|null
     */
    protected function getEnvironmentArgument(array $args): ?string
    {
        foreach ($args as $i => $value) {
            if ($value === '--env') {
                return $args[$i + 1] ?? null;
            }

            if (str_starts_with($value, '--env=')) {
                return head(array_slice(explode('=', $value), 1));
            }
        }

        return null;
    }
}
