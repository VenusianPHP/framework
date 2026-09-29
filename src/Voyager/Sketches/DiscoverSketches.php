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
    public static function within(array $paths, string $root_namespace, string $root_path): array
    {
        $paths = array_filter(array_map('realpath', $paths));

        if ($paths === []) {
            return [];
        }

        $root_path = rtrim((string) realpath($root_path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $found = [];

        foreach (Finder::create()->in($paths)->files()->name('*.php') as $file) {
            $class = rtrim($root_namespace, '\\').'\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                Str::after($file->getRealPath(), $root_path),
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
