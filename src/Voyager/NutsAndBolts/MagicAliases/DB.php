<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/** @see \Voyager\Database\DatabaseManager */
class DB extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'db';
    }
}
