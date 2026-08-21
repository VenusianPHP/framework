<?php

namespace Tests\Console\Fixtures;

use SplFileInfo;
use Voyager\System\Console\Kernel;

class TestKernel extends Kernel
{
    public $loadedCommands = [];

    public function loadFrom($paths)
    {
        $this->load($paths);
    }

    #[\Override]
    protected function commandClassFromFile(SplFileInfo $file, string $namespace): string
    {
        return tap(parent::commandClassFromFile($file, $namespace), fn ($command) => $this->loadedCommands[] = $command);
    }

    public function getRegisteredCommands(): array
    {
        return collect($this->getComputer()->all())->values()->transform(fn ($command) => $command::class)->all();
    }
}
