<?php

namespace Voyager\System\Bootstrap;

use Voyager\Contracts\System\Application;
use Voyager\System\AliasLoader;
use Voyager\System\PackageManifest;
use Voyager\MagicAliases\MagicAlias;

class RegisterMagicAliases
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        MagicAlias::clearResolvedInstances();

        MagicAlias::setMagicAliasApplication($app);

        AliasLoader::getInstance(array_merge(
            AliasLoader::defaultAliases()->all(),
            $app->make('config')->get('app.aliases', []),
            $app->make(PackageManifest::class)->aliases()
        ))->register();
    }
}
