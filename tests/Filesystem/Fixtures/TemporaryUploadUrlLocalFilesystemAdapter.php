<?php

namespace Tests\Filesystem\Fixtures;

use League\Flysystem\Local\LocalFilesystemAdapter;

class TemporaryUploadUrlLocalFilesystemAdapter extends LocalFilesystemAdapter
{
    public function temporaryUploadUrl($path, $expiration, $options): array
    {
        return [
            'url' => $path,
            'headers' => [],
        ];
    }
}
