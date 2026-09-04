<?php

namespace Voyager\System\Events;

use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Reflector;
use Voyager\NutsAndBolts\DataObjects\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class DiscoverEvents
{
    /**
     * The callback to be used to guess class names.
     *
     * @var (callable(SplFileInfo, string): class-string)|null
     */
    public static $guessClassNamesUsingCallback;

    /**
     * Get every event and listener by searching the given listener directory.
     *
     * @param  array<int, string>|string  $listenerPath
     * @param  string  $basePath
     * @return array
     */
    public static function within($listenerPath, string $basePath): array
    {
        if (Arr::wrap($listenerPath) === []) {
            return [];
        }

        $listeners = new Collection(static::getListenerEvents(
            Finder::create()->files()->in($listenerPath), $basePath
        ));

        $discoveredEvents = [];

        foreach ($listeners as $listener => $events) {
            foreach ($events as $event) {
                if (! isset($discoveredEvents[$event])) {
                    $discoveredEvents[$event] = [];
                }

                $discoveredEvents[$event][] = $listener;
            }
        }

        return $discoveredEvents;
    }

    /**
     * Get every listener and their corresponding event.
     *
     * @param  iterable<string, SplFileInfo>  $listeners
     * @param  string  $basePath
     * @return array
     */
    protected static function getListenerEvents($listeners, string $basePath): array
    {
        $listenerEvents = [];

        foreach ($listeners as $listener) {
            try {
                $listener = new ReflectionClass(
                    static::classFromFile($listener, $basePath)
                );
            } catch (ReflectionException) {
                continue;
            }

            if (! $listener->isInstantiable()) {
                continue;
            }

            foreach ($listener->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ((! Str::is('handle*', $method->name) && ! Str::is('__invoke', $method->name)) ||
                    ! isset($method->getParameters()[0])) {
                    continue;
                }

                $listenerEvents[$listener->name.'@'.$method->name] =
                                Reflector::getParameterClassNames($method->getParameters()[0]);
            }
        }

        return array_filter($listenerEvents);
    }

    /**
     * Extract the class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $basePath
     * @return class-string
     */
    protected static function classFromFile(SplFileInfo $file, string $basePath)
    {
        if (static::$guessClassNamesUsingCallback) {
            return call_user_func(static::$guessClassNamesUsingCallback, $file, $basePath);
        }

        $class = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);

        return ucfirst(Str::camel(str_replace(
            [DIRECTORY_SEPARATOR, ucfirst(basename(app()->path())).'\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        )));
    }

    /**
     * Specify a callback to be used to guess class names.
     *
     * @param  callable(SplFileInfo, string): class-string  $callback
     * @return void
     */
    public static function guessClassNamesUsing(callable $callback): void
    {
        static::$guessClassNamesUsingCallback = $callback;
    }
}
