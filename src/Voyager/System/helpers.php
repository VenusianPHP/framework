<?php

use Carbon\CarbonInterface;
use Voyager\Broadcasting\FakePendingBroadcast;
use Voyager\Broadcasting\PendingBroadcast;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\Auth\Factory as AuthFactory;
use Voyager\Contracts\Broadcasting\Factory as BroadcastFactory;
use Voyager\Contracts\Bus\Dispatcher;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Translation\Translator;
use Voyager\Contracts\Validation\Factory as ValidationFactory;
use Voyager\Contracts\Validation\Validator as ValidatorContract;
use Voyager\Contracts\View\Factory as ViewFactory;
use Voyager\Contracts\View\View as ViewContract;
use Voyager\System\Bus\PendingClosureDispatch;
use Voyager\System\Bus\PendingDispatch;
use Voyager\Log\Context\Repository as ContextRepository;
use Voyager\Log\LogManager;
use Voyager\Queue\CallQueuedClosure;
use Voyager\NutsAndBolts\Defer\DeferredCallback;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\NutsAndBolts\MagicAliases\Date;
use Psr\Log\LoggerInterface;

use function Voyager\NutsAndBolts\Helpers\enum_value;

if (! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>|null  $abstract
     * @return ($abstract is class-string<TClass> ? TClass : ($abstract is null ? \Voyager\System\Application : mixed))
     */
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Vessel::getInstance();
        }

        return Vessel::getInstance()->make($abstract, $parameters);
    }
}

if (! function_exists('app_path')) {
    /**
     * Get the path to the application folder.
     *
     * @param  string  $path
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
     * @param  string  $path
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('bcrypt')) {
    /**
     * Hash the given value against the bcrypt algorithm.
     *
     * @param  string  $value
     * @param  array  $options
     */
    function bcrypt(string $value, array $options = []): string
    {
        return app('hash')->driver('bcrypt')->make($value, $options);
    }
}

if (! function_exists('broadcast')) {
    /**
     * Begin broadcasting an event.
     *
     * @param  mixed  $event
     */
    function broadcast(mixed $event = null): PendingBroadcast
    {
        return app(BroadcastFactory::class)->event($event);
    }
}

if (! function_exists('broadcast_if')) {
    /**
     * Begin broadcasting an event if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  mixed  $event
     */
    function broadcast_if(bool $boolean, mixed $event = null): PendingBroadcast
    {
        if ($boolean) {
            return app(BroadcastFactory::class)->event(value($event));
        } else {
            return new FakePendingBroadcast;
        }
    }
}

if (! function_exists('broadcast_unless')) {
    /**
     * Begin broadcasting an event unless the given condition is true.
     *
     * @param  bool  $boolean
     * @param  mixed  $event
     */
    function broadcast_unless(bool $boolean, mixed $event = null): PendingBroadcast
    {
        if (! $boolean) {
            return app(BroadcastFactory::class)->event(value($event));
        } else {
            return new FakePendingBroadcast;
        }
    }
}

if (! function_exists('cache')) {
    /**
     * Get / set the specified cache value.
     *
     * If an array is passed, we'll assume you want to put to the cache.
     *
     * @param  string|array<string, mixed>|null  $key  key|data
     * @param  mixed  $default  default|expiration|null
     * @return ($key is null ? \Voyager\Cache\CacheManager : ($key is string ? mixed : bool))
     *
     * @throws \InvalidArgumentException
     */
    function cache($key = null, mixed $default = null)
    {
        if (is_null($key)) {
            return app('cache');
        }

        if (is_string($key)) {
            return app('cache')->get($key, $default);
        }

        if (! is_array($key)) {
            throw new InvalidArgumentException(
                'When setting a value in the cache, you must pass an array of key / value pairs.'
            );
        }

        return app('cache')->put(key($key), array_first($key), ttl: $default);
    }
}

if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array<string, mixed>|string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? \Voyager\Config\Repository : ($key is string ? mixed : null))
     */
    function config($key = null, mixed $default = null)
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
     * @param  string  $path
     */
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (! function_exists('context')) {
    /**
     * Get / set the specified context value.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return ($key is string ? mixed : \Voyager\Log\Context\Repository)
     */
    function context(array|string|null $key = null, mixed $default = null)
    {
        $context = app(ContextRepository::class);

        return match (true) {
            is_null($key) => $context,
            is_array($key) => $context->add($key),
            default => $context->get($key, $default),
        };
    }
}

if (! function_exists('database_path')) {
    /**
     * Get the database path.
     *
     * @param  string  $path
     */
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('decrypt')) {
    /**
     * Decrypt the given value.
     *
     * @param  string  $value
     * @param  bool  $unserialize
     * @return mixed
     */
    function decrypt(string $value, bool $unserialize = true): mixed
    {
        return app('encrypter')->decrypt($value, $unserialize);
    }
}

if (! function_exists('defer')) {
    /**
     * Defer execution of the given callback.
     *
     * @return ($callback is null ? \Voyager\NutsAndBolts\Defer\DeferredCallbackCollection : \Voyager\NutsAndBolts\Defer\DeferredCallback)
     */
    function defer(?callable $callback = null, ?string $name = null, bool $always = false): DeferredCallback|DeferredCallbackCollection
    {
        return \Voyager\NutsAndBolts\defer($callback, $name, $always);
    }
}

if (! function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param  mixed  $job
     * @return ($job is \Closure ? \Voyager\System\Bus\PendingClosureDispatch : \Voyager\System\Bus\PendingDispatch)
     */
    function dispatch(mixed $job): PendingDispatch|PendingClosureDispatch
    {
        return $job instanceof Closure
            ? new PendingClosureDispatch(CallQueuedClosure::create($job))
            : new PendingDispatch($job);
    }
}

if (! function_exists('dispatch_sync')) {
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $job
     * @param  mixed  $handler
     * @return mixed
     */
    function dispatch_sync(mixed $job, mixed $handler = null): mixed
    {
        return app(Dispatcher::class)->dispatchSync($job, $handler);
    }
}

if (! function_exists('encrypt')) {
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     */
    function encrypt(mixed $value, bool $serialize = true): string
    {
        return app('encrypter')->encrypt($value, $serialize);
    }
}

if (! function_exists('event')) {
    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    function event(...$args): ?array
    {
        return app('events')->dispatch(...$args);
    }
}

if (! function_exists('fake') && class_exists(\Faker\Factory::class)) {
    /**
     * Get a faker instance.
     *
     * @param  string|null  $locale
     */
    function fake(?string $locale = null): \Faker\Generator
    {
        if (app()->bound('config')) {
            $locale ??= app('config')->get('app.faker_locale');
        }

        $locale ??= 'en_US';

        $abstract = \Faker\Generator::class.':'.$locale;

        if (! app()->bound($abstract)) {
            app()->singleton($abstract, fn () => \Faker\Factory::create($locale));
        }

        return app()->make($abstract);
    }
}

if (! function_exists('info')) {
    /**
     * Write some information to the log.
     *
     * @param  string  $message
     * @param  array  $context
     */
    function info(string $message, array $context = []): void
    {
        app('log')->info($message, $context);
    }
}

if (! function_exists('lang_path')) {
    /**
     * Get the path to the language folder.
     *
     * @param  string  $path
     */
    function lang_path(string $path = ''): string
    {
        return app()->langPath($path);
    }
}

if (! function_exists('logger')) {
    /**
     * Log a debug message to the logs.
     *
     * @param  string|null  $message
     * @return ($message is null ? \Psr\Log\LoggerInterface : null)
     */
    function logger(?string $message = null, array $context = []): ?LoggerInterface
    {
        if (is_null($message)) {
            return app('log');
        }

        return app('log')->debug($message, $context);
    }
}

if (! function_exists('logs')) {
    /**
     * Get a log driver instance.
     *
     * @param  string|null  $driver
     * @return ($driver is null ? \Voyager\Log\LogManager : \Psr\Log\LoggerInterface)
     */
    function logs(?string $driver = null): LoggerInterface|LogManager
    {
        return $driver ? app('log')->driver($driver) : app('log');
    }
}

if (! function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param  \DateTimeZone|\UnitEnum|string|null  $tz
     */
    function now(\DateTimeZone|\UnitEnum|string|null $tz = null): CarbonInterface
    {
        return Date::now(enum_value($tz));
    }
}

if (! function_exists('report')) {
    /**
     * Report an exception.
     *
     * @param  \Throwable|string  $exception
     */
    function report(\Throwable|string $exception): void
    {
        if (is_string($exception)) {
            $exception = new Exception($exception);
        }

        app(ExceptionHandler::class)->report($exception);
    }
}

if (! function_exists('report_if')) {
    /**
     * Report an exception if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  \Throwable|string  $exception
     */
    function report_if(bool $boolean, \Throwable|string $exception): void
    {
        if ($boolean) {
            report($exception);
        }
    }
}

if (! function_exists('report_unless')) {
    /**
     * Report an exception unless the given condition is true.
     *
     * @param  bool  $boolean
     * @param  \Throwable|string  $exception
     */
    function report_unless(bool $boolean, \Throwable|string $exception): void
    {
        if (! $boolean) {
            report($exception);
        }
    }
}

if (! function_exists('rescue')) {
    /**
     * Catch a potential exception and return a default value.
     *
     * @template TValue
     * @template TFallback
     *
     * @param  callable(): TValue  $callback
     * @param  (callable(\Throwable): TFallback)|TFallback  $rescue
     * @param  bool|callable(\Throwable): bool  $report
     * @return TValue|TFallback
     */
    function rescue(callable $callback, $rescue = null, $report = true): mixed
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

if (! function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $name
     * @return ($name is class-string<TClass> ? TClass : mixed)
     */
    function resolve($name, array $parameters = [])
    {
        return app($name, $parameters);
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     */
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (! function_exists('today')) {
    /**
     * Create a new Carbon instance for the current date.
     *
     * @param  \DateTimeZone|\UnitEnum|string|null  $tz
     * @return \Voyager\NutsAndBolts\DataObjects\Carbon
     */
    function today(\DateTimeZone|\UnitEnum|string|null $tz = null): CarbonInterface
    {
        return Date::today(enum_value($tz));
    }
}

if (! function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param  string|null  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @return ($key is null ? \Voyager\Contracts\Translation\Translator : array|string)
     */
    function trans(?string $key = null, array $replace = [], ?string $locale = null): Translator|array|string
    {
        if (is_null($key)) {
            return app('translator');
        }

        return app('translator')->get($key, $replace, $locale);
    }
}

if (! function_exists('trans_choice')) {
    /**
     * Translates the given message based on a count.
     *
     * @param  string  $key
     * @param  \Countable|int|float|array  $number
     * @param  string|null  $locale
     */
    function trans_choice(string $key, \Countable|int|float|array $number, array $replace = [], ?string $locale = null): string
    {
        return app('translator')->choice($key, $number, $replace, $locale);
    }
}

if (! function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param  string|null  $key
     * @param  array  $replace
     * @param  string|null  $locale
     */
    function __(?string $key = null, array $replace = [], ?string $locale = null): string|array|null
    {
        if (is_null($key)) {
            return $key;
        }

        return trans($key, $replace, $locale);
    }
}

if (! function_exists('validator')) {
    /**
     * Create a new Validator instance.
     *
     * @return ($data is null ? \Voyager\Contracts\Validation\Factory : \Voyager\Contracts\Validation\Validator)
     */
    function validator(?array $data = null, array $rules = [], array $messages = [], array $attributes = []): ValidatorContract|ValidationFactory
    {
        $factory = app(ValidationFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($data ?? [], $rules, $messages, $attributes);
    }
}
