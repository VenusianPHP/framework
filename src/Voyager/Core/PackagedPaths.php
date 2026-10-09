<?php

namespace Voyager\Core;

use Phar;
use Voyager\Filesystem\Filesystem;

use function Voyager\NutsAndBolts\join_paths;

/**
 * Where a packaged app (one running from a phar) keeps what it writes.
 *
 * The phar is read-only, so storage, database and the bootstrap caches live
 * in the user's data directory, seeded from the phar on first run only.
 */
final class PackagedPaths
{
    private readonly string $home;

    private readonly string $os_family;

    public function __construct(
        public readonly string $phar_root,
        public readonly string $name,
        ?string $home = null,
        ?string $os_family = null,
    ) {
        $this->home = $home ?? (getenv('HOME') ?: '');
        $this->os_family = $os_family ?? PHP_OS_FAMILY;
    }

    /**
     * The paths of the phar the app lives in, or null outside one.
     *
     * The base path is the signal: a build passes phar://.../<binary> as the
     * app root. The phar file is the nearest ancestor of that path that is a
     * file; a phpmicro binary carries no .phar suffix, and Phar::running() is
     * empty inside one, so neither is relied on. Phar::running() is the second
     * check for a plain `php app.phar` run that passed no base path.
     */
    public static function fromRunningPhar(?string $base_path = null): ?self
    {
        $root = is_string($base_path) && str_starts_with($base_path, 'phar://')
            ? $base_path
            : Phar::running();

        if ($root === '') {
            return null;
        }

        $file = substr($root, strlen('phar://'));

        while (! is_file($file)) {
            $parent = dirname($file);

            if ($parent === $file || $parent === '.' || $parent === '/') {
                return null;
            }

            $file = $parent;
        }

        try {
            $metadata = (new Phar($file))->getMetadata();
        } catch (\Throwable) {
            $metadata = null;
        }

        $name = is_array($metadata) && is_string($metadata['name'] ?? null) && $metadata['name'] !== ''
            ? $metadata['name']
            : preg_replace('/(\.phar|-bin)$/', '', basename($file));

        return new self('phar://'.$file, $name);
    }

    public function dataPath(string $path = ''): string
    {
        $base = match ($this->os_family) {
            'Darwin' => join_paths($this->home, 'Library', 'Application Support', $this->name),
            'Windows' => join_paths(getenv('APPDATA') ?: join_paths($this->home, 'AppData', 'Roaming'), $this->name),
            default => join_paths(getenv('XDG_DATA_HOME') ?: join_paths($this->home, '.local', 'share'), $this->name),
        };

        return join_paths($base, $path);
    }

    /** Copies storage/ and database/ out of the phar the first time only. */
    public function seed(): void
    {
        if (is_dir($this->dataPath())) {
            return;
        }

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->dataPath('bootstrap/cache'));

        foreach (['storage', 'database'] as $directory) {
            $source = join_paths($this->phar_root, $directory);

            if (is_dir($source)) {
                $files->copyDirectory($source, $this->dataPath($directory));
            } else {
                $files->ensureDirectoryExists($this->dataPath($directory));
            }
        }
    }
}
