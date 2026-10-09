<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Voyager\Contracts\Sketches\Sketch;
use Voyager\NutsAndBolts\DataObjects\Str;

class DiscoverSketches
{
    /**
     * Class-from-path, the Laravel Kernel::load() formula: a file under $root_path
     * maps onto $root_namespace by replacing the directory separators.
     *
     * @param  array<string> $paths
     * @return array<class-string<Sketch>>
     */
    /**
     * realpath() for paths on disk; inside a phar (a packaged app) it is false
     * and the phar stream wrapper leaves `..` and `.` segments alone, so those
     * are collapsed here instead.
     */
    public static function normalize(string $path): string
    {
        if (($real = realpath($path)) !== false) {
            return $real;
        }

        $prefix = str_starts_with($path, 'phar://') ? 'phar://' : '';
        $segments = [];

        foreach (explode('/', substr($path, strlen($prefix))) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.' && ($segment !== '' || $segments === [])) {
                $segments[] = $segment;
            }
        }

        return $prefix.implode('/', $segments);
    }

    public static function within(array $paths, string $root_namespace, string $root_path): array
    {
        $paths = array_values(array_filter(array_map(self::normalize(...), $paths), 'is_dir'));

        if ($paths === []) {
            return [];
        }

        $root_path = rtrim(self::normalize($root_path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $found = [];

        foreach (Finder::create()->in($paths)->files()->name('*.php') as $file) {
            $class = rtrim($root_namespace, '\\').'\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                Str::after($file->getRealPath() ?: $file->getPathname(), $root_path),
            );

            if (! class_exists($class) || ! is_subclass_of($class, Sketch::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $found[] = $class;
        }

        return $found;
    }
}
