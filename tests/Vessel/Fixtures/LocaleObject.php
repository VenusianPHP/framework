<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Config;

final class LocaleObject
{
    public function __construct(
        #[Config('app.locale')] public readonly ?string $locale
    ) {
        //
    }
}
