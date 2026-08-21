<?php

use Voyager\Filesystem\Filesystem;
use Voyager\System\PackageManifest;

test('the manifest is compiled from the installed packages', function () {
    @unlink(__DIR__.'/fixtures/packages.php');

    $manifest = new PackageManifest(new Filesystem, __DIR__.'/fixtures', __DIR__.'/fixtures/packages.php');

    expect($manifest->providers())->toEqual(['foo', 'bar', 'baz'])
        ->and($manifest->aliases())->toEqual(['Foo' => 'Foo\\Facade']);

    unlink(__DIR__.'/fixtures/packages.php');
});
