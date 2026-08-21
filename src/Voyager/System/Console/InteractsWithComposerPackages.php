<?php

namespace Voyager\System\Console;

use Symfony\Component\Process\Process;

use function Voyager\NutsAndBolts\php_binary;

trait InteractsWithComposerPackages
{
    /**
     * Installs the given Composer Packages into the application.
     *
     * @param  string  $composer
     * @param  array  $packages
     * @return bool
     */
    protected function requireComposerPackages(string $composer, array $packages): bool
    {
        if ($composer !== 'global') {
            $command = [$this->phpBinary(), $composer, 'require'];
        }

        $command = array_merge(
            $command ?? ['composer', 'require'],
            $packages,
        );

        return ! (new Process($command, $this->venusian->basePath(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            });
    }

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary(): string
    {
        return php_binary();
    }
}
