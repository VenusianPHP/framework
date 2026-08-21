<?php

namespace Voyager\System;

use Exception;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Env;

class PackageManifest
{
    /**
     * The filesystem instance.
     *
     * @var \Voyager\Filesystem\Filesystem
     */
    public $files;

    /**
     * The base path.
     *
     * @var string
     */
    public $basePath;

    /**
     * The vendor path.
     *
     * @var string
     */
    public $vendorPath;

    /**
     * The manifest path.
     *
     * @var string|null
     */
    public $manifestPath;

    /**
     * The loaded manifest array.
     *
     * @var array
     */
    public $manifest;

    /**
     * Create a new package manifest instance.
     *
     * @param  \Voyager\Filesystem\Filesystem  $files
     * @param  string  $basePath
     * @param  string  $manifestPath
     */
    public function __construct(Filesystem $files, string $basePath, string $manifestPath)
    {
        $this->files = $files;
        $this->basePath = $basePath;
        $this->manifestPath = $manifestPath;
        $this->vendorPath = Env::get('COMPOSER_VENDOR_DIR') ?: $basePath.'/vendor';
    }

    /**
     * Get every service provider class name for all packages.
     *
     * @return array
     */
    public function providers(): array
    {
        return $this->config('providers');
    }

    /**
     * Get every alias for all packages.
     *
     * @return array
     */
    public function aliases(): array
    {
        return $this->config('aliases');
    }

    /**
     * Get every value for all packages for the given configuration name.
     *
     * @param  string  $key
     * @return array
     */
    public function config(string $key): array
    {
        return (new Collection($this->getManifest()))
            ->flatMap(fn ($configuration) => (array) ($configuration[$key] ?? []))
            ->filter()
            ->all();
    }

    /**
     * Get the current package manifest.
     *
     * @return array
     */
    protected function getManifest(): array
    {
        if (! is_null($this->manifest)) {
            return $this->manifest;
        }

        if (! is_file($this->manifestPath)) {
            $this->build();
        }

        return $this->manifest = is_file($this->manifestPath) ?
            $this->files->getRequire($this->manifestPath) : [];
    }

    /**
     * Build the manifest and write it to disk.
     *
     * @return void
     */
    public function build(): void
    {
        $packages = [];

        if ($this->files->exists($path = $this->vendorPath.'/composer/installed.json')) {
            $installed = json_decode($this->files->get($path), true);

            $packages = $installed['packages'] ?? $installed;
        }

        $ignoreAll = in_array('*', $ignore = $this->packagesToIgnore());

        $this->write((new Collection($packages))->mapWithKeys(function ($package) {
            return [$this->format($package['name']) => $package['extra']['venusian'] ?? []];
        })->each(function ($configuration) use (&$ignore) {
            $ignore = array_merge($ignore, $configuration['dont-discover'] ?? []);
        })->reject(function ($configuration, $package) use ($ignore, $ignoreAll) {
            return $ignoreAll || in_array($package, $ignore);
        })->filter()->all());
    }

    /**
     * Format the given package name.
     *
     * @param  string  $package
     * @return string
     */
    protected function format(string $package): string
    {
        return str_replace($this->vendorPath.'/', '', $package);
    }

    /**
     * Get every package name that should be ignored.
     *
     * @return array
     */
    protected function packagesToIgnore(): array
    {
        if (! is_file($this->basePath.'/composer.json')) {
            return [];
        }

        return json_decode(file_get_contents(
            $this->basePath.'/composer.json'
        ), true)['extra']['venusian']['dont-discover'] ?? [];
    }

    /**
     * Write the given manifest array to disk.
     *
     * @param  array  $manifest
     * @return void
     *
     * @throws \Exception
     */
    protected function write(array $manifest): void
    {
        if (! is_writable($dirname = dirname($this->manifestPath))) {
            throw new Exception("The {$dirname} directory must be present and writable.");
        }

        $this->files->replace(
            $this->manifestPath, '<?php return '.var_export($manifest, true).';'
        );
    }
}
