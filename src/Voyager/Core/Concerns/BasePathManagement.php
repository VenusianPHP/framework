<?php

namespace Voyager\Core\Concerns;

use ReflectionException;
use function Voyager\NutsAndBolts\join_paths;

trait BasePathManagement
{
    /**
     * The base path for the Venusian application.
     *
     * @var string|null
     */
    protected ?string $base_path = null;

    /**
     * The custom application path defined by the developer.
     *
     * @var string|null
 */
    protected ?string $app_path = null;

    /**
     * The custom configuration path defined by the developer.
     *
     * @var string|null
    */
    protected ?string $config_path = null;

    /**
     * The custom database path defined by the developer.
     *
     * @var string|null
     */
    protected ?string $database_path = null;

    /**
     * The custom storage path defined by the developer.
     *
     * @var string|null
     */
    protected ?string $storage_path = null;

    /**
     * The custom bootstrap path defined by the developer.
     *
     * @var string|null
     */
    protected ?string $bootstrap_path = null;

    /**
     * Set the base path for the application.
     *
     * @param  string  $base_path
     * @return $this
     */
    public function setBasePath(string $base_path): static
    {
        $this->base_path = rtrim($base_path, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind every application path into the container.
     *
     * @return void
     * @throws \ReflectionException
     */
    protected function bindPathsInContainer(): void
    {
        $this->registerInstance('path', $this->path());
        $this->registerInstance('path.base', $this->basePath());
        $this->registerInstance('path.config', $this->configPath());
        $this->registerInstance('path.database', $this->databasePath());
        $this->registerInstance('path.storage', $this->storagePath());

        $this->useBootstrapPath(value(function () {
            return is_dir($directory = $this->basePath('.venusian'))
                ? $directory
                : $this->basePath('bootstrap');
        }));

    }

    /**
     * Set the bootstrap file directory.
     *
     * @param string $path
     * @return $this
     * @throws ReflectionException
     */
    public function useBootstrapPath(string $path): static
    {
        $this->bootstrap_path = $path;

        $this->registerInstance('path.bootstrap', $path);

        return $this;
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @param  string  $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->joinPaths($this->app_path ?: $this->basePath('app'), $path);
    }

    /**
     * Set the application directory.
     */
    public function useAppPath(string $path): static
    {
        $this->app_path = $path;

        $this->registerInstance('path', $path);

        return $this;
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     * @return string
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->joinPaths($this->bootstrap_path, $path);
    }

    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->base_path, $path);
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     * @return string
     */
    public function configPath(string $path = ''): string
    {
        return $this->joinPaths($this->config_path ?: $this->basePath('config'), $path);
    }

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        if (isset($_ENV['VENUSIAN_STORAGE_PATH'])) {
            return $this->joinPaths($this->storage_path ?: $_ENV['VENUSIAN_STORAGE_PATH'], $path);
        }

        if (isset($_SERVER['VENUSIAN_STORAGE_PATH'])) {
            return $this->joinPaths($this->storage_path ?: $_SERVER['VENUSIAN_STORAGE_PATH'], $path);
        }

        return $this->joinPaths($this->storage_path ?: $this->basePath('storage'), $path);
    }

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    public function databasePath(string $path = ''): string
    {
        return $this->joinPaths($this->database_path ?: $this->basePath('database'), $path);
    }

    /**
     * Set the database directory.
     */
    public function useDatabasePath(string $path): static
    {
        $this->database_path = $path;

        $this->registerInstance('path.database', $path);

        return $this;
    }


    /**
     * Join the given paths together.
     *
     * @param  string  $basePath
     * @param  string  $path
     * @return string
     */
    public function joinPaths(string $basePath, string $path = ''): string
    {
        return join_paths($basePath, $path);
    }


}