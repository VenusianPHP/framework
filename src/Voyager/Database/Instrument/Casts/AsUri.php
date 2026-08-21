<?php

namespace Voyager\Database\Instrument\Casts;

use Voyager\Contracts\Database\Instrument\Castable;
use Voyager\Contracts\Database\Instrument\CastsAttributes;
use Voyager\NutsAndBolts\Uri;

class AsUri implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return \Voyager\Contracts\Database\Instrument\CastsAttributes<\Voyager\NutsAndBolts\Uri, string|Uri>
     */
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes
        {
            public function get($model, $key, $value, $attributes)
            {
                return isset($value) ? new Uri($value) : null;
            }

            public function set($model, $key, $value, $attributes)
            {
                return isset($value) ? (string) $value : null;
            }
        };
    }
}
