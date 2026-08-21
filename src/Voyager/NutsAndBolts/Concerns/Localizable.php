<?php

namespace Voyager\NutsAndBolts\Concerns;

use Voyager\Vessel\Vessel;

trait Localizable
{
    /**
     * Run the callback with the given locale.
     *
     * @param  string  $locale
     * @param  \Closure  $callback
     * @return mixed
     */
    public function withLocale($locale, $callback): mixed
    {
        if (! $locale) {
            return $callback();
        }

        $app = Vessel::getInstance();

        $original = $app->getLocale();

        try {
            $app->setLocale($locale);

            return $callback();
        } finally {
            $app->setLocale($original);
        }
    }
}
