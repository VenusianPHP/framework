<?php

use Voyager\Core\PackageManifest;
use Voyager\Filesystem\Filesystem;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/vf-manifest-'.getmypid();
    mkdir($this->root.'/vendor/composer', 0777, true);
    mkdir($this->root.'/bootstrap/cache', 0777, true);
    file_put_contents($this->root.'/composer.json', '{}');
    file_put_contents($this->root.'/vendor/composer/installed.json', json_encode(['packages' => [
        ['name' => 'venusian/probe', 'extra' => ['venusian' => ['providers' => ['Venusian\\Probe\\ProbeServiceProvider']]]],
        ['name' => 'acme/quiet', 'extra' => []],
    ]]));
    $this->manifest = new PackageManifest(new Filesystem, $this->root, $this->root.'/bootstrap/cache/packages.php');
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->root));
});

it('reads the providers back from a manifest it just built', function () {
    $this->manifest->build();

    expect($this->manifest->providers())->toBe(['Venusian\\Probe\\ProbeServiceProvider']);
});

it('reads the providers from a manifest already on disk, on a fresh instance', function () {
    $this->manifest->build();
    $fresh = new PackageManifest(new Filesystem, $this->root, $this->root.'/bootstrap/cache/packages.php');

    expect($fresh->providers())->toBe(['Venusian\\Probe\\ProbeServiceProvider']);
});

it('builds the manifest itself when none is on disk', function () {
    expect(is_file($this->root.'/bootstrap/cache/packages.php'))->toBeFalse()
        ->and($this->manifest->providers())->toBe(['Venusian\\Probe\\ProbeServiceProvider'])
        ->and(is_file($this->root.'/bootstrap/cache/packages.php'))->toBeTrue();
});

it('leaves out packages that declare nothing', function () {
    $this->manifest->build();

    expect(array_keys($this->manifest->manifest))->toBe(['venusian/probe']);
});
