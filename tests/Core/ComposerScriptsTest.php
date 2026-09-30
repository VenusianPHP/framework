<?php

use Voyager\Core\ComposerScripts;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/venusian-composer-scripts-'.bin2hex(random_bytes(4));
    mkdir($this->base.'/bootstrap/cache', 0777, true);
});

afterEach(function () {
    array_map('unlink', glob($this->base.'/bootstrap/cache/*'));
    rmdir($this->base.'/bootstrap/cache');
    rmdir($this->base.'/bootstrap');
    rmdir($this->base);
});

it('deletes the cached config, services, packages and signals and leaves everything else', function () {
    foreach (['config.php', 'services.php', 'packages.php', 'signals.php', 'keep.php'] as $file) {
        file_put_contents($this->base.'/bootstrap/cache/'.$file, '<?php return [];');
    }

    ComposerScripts::clearCompiled($this->base);

    expect(array_map('basename', glob($this->base.'/bootstrap/cache/*')))->toBe(['keep.php']);
});

it('clears nothing when no cache was written', function () {
    ComposerScripts::clearCompiled($this->base);

    expect(glob($this->base.'/bootstrap/cache/*'))->toBe([]);
});
