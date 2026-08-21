<?php

namespace Tests\Filesystem\Fixtures;

use Carbon\Carbon;
use League\Flysystem\Local\LocalFilesystemAdapter;

class TemporaryUrlLocalFilesystemAdapter extends LocalFilesystemAdapter
{
    public function getTemporaryUrl($path, Carbon $expiration, $options): string
    {
        return $path.$expiration->toString().implode('', $options);
    }
}
