<?php

namespace Voyager\Core;

use Composer\Script\Event;

class ComposerScripts
{
    /**
     * Handle the post-autoload-dump Composer event.
     *
     * @param  \Composer\Script\Event  $event
     * @return void
     */
    public static function postAutoloadDump(Event $event): void
    {
        require_once $event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';

        static::clearCompiled(getcwd());
    }

    /**
     * Delete the cached config, services, packages and signals, so the next boot rebuilds
     * them from the packages now installed.
     *
     * @param  string  $base_path
     * @return void
     */
    public static function clearCompiled(string $base_path): void
    {
        $instance = new RenderedInstance($base_path);

        foreach ([
            $instance->getCachedConfigPath(),
            $instance->getCachedServicesPath(),
            $instance->getCachedPackagesPath(),
            $instance->getCachedSignalsPath(),
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
