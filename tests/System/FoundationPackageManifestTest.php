<?php

use Voyager\Filesystem\Filesystem;
use Voyager\System\PackageManifest;

test('the manifest is compiled from the installed packages', function () {
    $compiled = __DIR__.'/fixtures/packages.php';

    if (is_file($compiled)) {
        unlink($compiled);
    }

    $manifest = new PackageManifest(new Filesystem, __DIR__.'/fixtures', __DIR__.'/fixtures/packages.php');

    expect($manifest->providers())->toEqual(['foo', 'bar', 'baz'])
        ->and($manifest->aliases())->toEqual(['Foo' => 'Foo\\Facade']);

    unlink(__DIR__.'/fixtures/packages.php');
});
