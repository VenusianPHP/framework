<?php

namespace Voyager\Core\Signals;

use Voyager\Contracts\Signals\Signal;

/**
 * Fired once vendor:publish has written everything under one tag.
 */
class VendorTagPublished implements Signal
{
    /**
     * @param array<string, string> $paths source path => destination path
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $paths,
    ) {}
}
