<?php

namespace Voyager\Core;

use ReflectionException;
use Composer\Autoload\ClassLoader;
use Voyager\Core\Bootstrap\ConfigFactory;
use Voyager\Core\Bootstrap\Exceptions;

final readonly class VenusianVoyager
{
    public static function setup(?string $base_path = null): ConfigFactory
    {
        return self::build($base_path);
    }

    /**
     * @throws ReflectionException
     */
    public static function launch(?string $base_path = null): RenderedInstance
    {
        return self::build($base_path)
            ->withExceptions(function (Exceptions $exceptions): void {})
            ->create();
    }

    /**
     * @throws ReflectionException
     */
    private static function build(?string $base_path = null): ConfigFactory
    {
        $base_path ??= self::inferBasePath();

        $factory = new ConfigFactory(new RenderedInstance($base_path));
        return $factory
            ->withKernels()
            ->withSignals()
            ->withCommands()
            ->withSketches()
            ->withProviders();
    }

    /**
     * Infer the application's base directory from the environment.
     *
     * @return string
     */
    public static function inferBasePath(): string
    {
        return match (true) {
            isset($_ENV['APP_BASE_PATH']) => $_ENV['APP_BASE_PATH'],
            default => dirname(
                array_values(array_filter(array_keys(ClassLoader::getRegisteredLoaders()),
                    fn ($path) => ! str_starts_with($path, 'phar://'),
                ))[0]),
        };
    }
}