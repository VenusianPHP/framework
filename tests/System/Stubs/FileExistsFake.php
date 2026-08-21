<?php

namespace Tests\System\Stubs;

class FileExistsFake
{
    public string $pathRequested;

    public function exists(string $path): bool
    {
        $this->pathRequested = $path;

        return false;
    }
}
