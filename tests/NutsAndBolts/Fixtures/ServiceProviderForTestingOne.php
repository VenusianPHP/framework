<?php

namespace Tests\NutsAndBolts\Fixtures;

use Voyager\NutsAndBolts\ServiceProvider;

class ServiceProviderForTestingOne extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->publishes(['source/unmarked/one' => 'destination/unmarked/one']);
        $this->publishes(['source/tagged/one' => 'destination/tagged/one'], 'some_tag');
        $this->publishes(['source/tagged/multiple' => 'destination/tagged/multiple'], ['tag_one', 'tag_two']);

        $this->publishesMigrations(['source/unmarked/two' => 'destination/unmarked/two']);
        $this->publishesMigrations(['source/tagged/three' => 'destination/tagged/three'], 'tag_three');
        $this->publishesMigrations(['source/tagged/multiple_two' => 'destination/tagged/multiple_two'], ['tag_four', 'tag_five']);
    }

    public function loadTranslationsFrom($path, $namespace = null)
    {
        $this->callAfterResolving('translator', fn ($translator) => is_null($namespace)
            ? $translator->addPath($path)
            : $translator->addNamespace($namespace, $path));
    }
}
