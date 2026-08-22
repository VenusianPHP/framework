<?php

namespace Voyager\Sketches;

use Voyager\NutsAndBolts\DataObjects\Str;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class DiscoverSketches
{
    /**
     * Discover concrete Sketch subclasses under the given path.
     *
     * @return array<string, class-string>  registration key => FQCN
     */
    public static function within(
        string $path,
        string $basePath,
        string $baseClass,
        string $appNamespace,
        string $appPath,
    ): array {
        if (! is_dir($path) || ! class_exists($baseClass)) {
            return [];
        }

        $discovered = [];

        foreach (Finder::create()->files()->name('*.php')->in($path) as $file) {
            try {
                $class = new ReflectionClass(static::classFromFile(
                    $file,
                    $basePath,
                    $appNamespace,
                    $appPath,
                ));
            } catch (ReflectionException) {
                continue;
            }

            if (! $class->isInstantiable()) {
                continue;
            }

            if (! $class->isSubclassOf($baseClass)) {
                continue;
            }

            $name = Str::kebab($class->getShortName());

            if ($name === '') {
                continue;
            }

            $discovered[Str::lower($name)] = $class->getName();
        }

        return $discovered;
    }

    /**
     * @return class-string
     */
    protected static function classFromFile(
        SplFileInfo $file,
        string $basePath,
        string $appNamespace,
        string $appPath,
    ): string {
        $appPath = realpath($appPath) ?: $appPath;
        $basePath = realpath($basePath) ?: $basePath;

        $filePath = $file->getRealPath() ?: $file->getPathname();

        $relativeToApp = Str::after($filePath, rtrim($appPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

        if ($relativeToApp === $filePath) {
            $relativeToApp = ltrim(Str::replaceFirst($basePath, '', $filePath), DIRECTORY_SEPARATOR);
            $appDir = basename($appPath);
            $relativeToApp = Str::after($relativeToApp, $appDir.DIRECTORY_SEPARATOR);
        }

        return rtrim($appNamespace, '\\').'\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            $relativeToApp,
        );
    }
}
