<?php

namespace Voyager\Core\Signals;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\Reflector;

class DiscoverSignals
{
    /**
     * The callback to be used to guess class names.
     *
     * @var (callable(SplFileInfo, string): class-string)|null
     */
    public static $guess_class_names_using_callback;

    /**
     * Get every event and listener by searching the given listener directory.
     *
     * @param string|array<int, string> $listenerPath
     * @param  string  $basePath
     * @return array
     */
    public static function within(array|string $listenerPath, string $basePath): array
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
     * @param iterable<string, SplFileInfo> $listeners
     * @param  string  $basePath
     * @return array
     */
    protected static function getListenerEvents(array $listeners, string $basePath): array
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
     * @param SplFileInfo $file
     * @param string $basePath
     * @return class-string
     * @throws ReflectionException
     */
    protected static function classFromFile(SplFileInfo $file, string $basePath): string
    {
        if (static::$guess_class_names_using_callback) {
            return call_user_func(static::$guess_class_names_using_callback, $file, $basePath);
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
        static::$guess_class_names_using_callback = $callback;
    }
}