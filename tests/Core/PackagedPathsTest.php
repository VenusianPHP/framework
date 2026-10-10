<?php

use Voyager\Core\PackagedPaths;
use Voyager\Filesystem\Filesystem;

/*
 * Where an app running from a phar keeps what it writes, and the one-time
 * seed of storage/ and database/ out of the phar.
 */
beforeEach(function () {
    $this->root = sys_get_temp_dir().'/venusian-packaged-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/phar/storage/logs', 0777, true);
    mkdir($this->root.'/phar/database', 0777, true);
    file_put_contents($this->root.'/phar/database/database.sqlite', 'shipped');
    file_put_contents($this->root.'/phar/storage/logs/.gitignore', '*');
    mkdir($this->root.'/home');
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('puts data under Application Support on macOS', function () {
    $paths = new PackagedPaths($this->root.'/phar', 'Stargazer', $this->root.'/home', 'Darwin');

    expect($paths->dataPath())->toBe($this->root.'/home/Library/Application Support/Stargazer')
        ->and($paths->dataPath('storage/logs'))->toBe($this->root.'/home/Library/Application Support/Stargazer/storage/logs');
});

it('puts data under XDG_DATA_HOME, then ~/.local/share, on Linux', function () {
    $paths = new PackagedPaths($this->root.'/phar', 'Stargazer', $this->root.'/home', 'Linux');
    expect($paths->dataPath())->toBe($this->root.'/home/.local/share/Stargazer');

    putenv('XDG_DATA_HOME='.$this->root.'/xdg');
    $paths = new PackagedPaths($this->root.'/phar', 'Stargazer', $this->root.'/home', 'Linux');
    expect($paths->dataPath())->toBe($this->root.'/xdg/Stargazer');
    putenv('XDG_DATA_HOME');
});

it('seeds storage and database once', function () {
    $paths = new PackagedPaths($this->root.'/phar', 'Stargazer', $this->root.'/home', 'Darwin');

    $paths->seed();
    expect(file_get_contents($paths->dataPath('database/database.sqlite')))->toBe('shipped')
        ->and(is_file($paths->dataPath('storage/logs/.gitignore')))->toBeTrue()
        ->and(is_dir($paths->dataPath('bootstrap/cache')))->toBeTrue();

    file_put_contents($paths->dataPath('database/database.sqlite'), 'edited by the user');
    $paths->seed();
    expect(file_get_contents($paths->dataPath('database/database.sqlite')))->toBe('edited by the user');
});

it('is absent outside a phar', function () {
    expect(PackagedPaths::fromRunningPhar())->toBeNull()
        ->and(PackagedPaths::fromRunningPhar($this->root.'/phar'))->toBeNull();
});

it('finds the phar file above a phar:// base path even without a .phar suffix, and ignores a path with no file', function () {
    // A plain file stands in for a phpmicro binary: its name has no suffix and it is not a readable phar,
    // so the name falls back to the file name.
    file_put_contents($this->root.'/stargazer-bin', 'not a phar');

    $paths = PackagedPaths::fromRunningPhar('phar://'.$this->root.'/stargazer-bin/bootstrap');

    expect($paths)->not->toBeNull()
        ->and($paths->phar_root)->toBe('phar://'.$this->root.'/stargazer-bin')
        ->and($paths->name)->toBe('stargazer')
        ->and(PackagedPaths::fromRunningPhar('phar:///nowhere/at/all/app'))->toBeNull();
});

it('drops the bootstrap caches a different phar wrote, and keeps them for the same one', function () {
    // An upgrade replaces the phar: its packages and providers may differ from what the caches list.
    $phar = $this->root.'/stargazer.phar';
    file_put_contents($phar, 'version one');
    touch($phar, 1_700_000_000);
    $paths = new PackagedPaths('phar://'.$phar, 'Stargazer', $this->root.'/home', 'Linux');

    $paths->seed();
    file_put_contents($paths->dataPath('bootstrap/cache/packages.php'), '<?php return ["jovian/venusian-gtk" => []];');
    file_put_contents($paths->dataPath('database/database.sqlite'), 'the user\'s data');
    $paths->seed();
    expect(is_file($paths->dataPath('bootstrap/cache/packages.php')))->toBeTrue();

    file_put_contents($phar, 'version two, rebuilt');
    touch($phar, 1_700_000_500);
    $paths->seed();
    expect(is_file($paths->dataPath('bootstrap/cache/packages.php')))->toBeFalse()
        ->and(file_get_contents($paths->dataPath('database/database.sqlite')))->toBe('the user\'s data');

    file_put_contents($paths->dataPath('bootstrap/cache/packages.php'), '<?php return ["jovian/venusian-qt" => []];');
    $paths->seed();
    expect(is_file($paths->dataPath('bootstrap/cache/packages.php')))->toBeTrue();
});
