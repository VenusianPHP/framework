<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\System\PackageManifest;
use Voyager\NutsAndBolts\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:discover')]
class PackageDiscoverCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected ?string $signature = 'package:discover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Rebuild the cached package manifest';

    /**
     * Execute the console command.
     *
     * @param  \Voyager\System\PackageManifest  $manifest
     * @return void
     */
    public function handle(PackageManifest $manifest): void
    {
        $this->components->info('Discovering packages');

        $manifest->build();

        (new Collection($manifest->manifest))
            ->keys()
            ->each(fn ($description) => $this->components->task($description))
            ->whenNotEmpty(function () {
                $this->newLine();
            });
    }
}
