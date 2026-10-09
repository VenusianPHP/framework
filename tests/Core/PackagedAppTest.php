<?php

use Symfony\Component\Process\Process;
use Voyager\Filesystem\Filesystem;

/*
 * A one-file app inside a phar reports where storage, database and the
 * config cache land, and the seeded database file is there. The phar is
 * built and run in child processes: this process has phar.readonly on.
 */
beforeEach(function () {
    $this->root = sys_get_temp_dir().'/venusian-packaged-app-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/app/database', 0777, true);
    mkdir($this->root.'/app/storage/logs', 0777, true);
    mkdir($this->root.'/home');
    file_put_contents($this->root.'/app/database/database.sqlite', 'shipped');
    file_put_contents($this->root.'/app/main.php', <<<'PHP'
    <?php
    require getenv('VENUSIAN_FRAMEWORK_AUTOLOAD');
    $app = new Voyager\Core\RenderedInstance(__DIR__);
    echo json_encode([
        'packaged' => $app->runningPackaged(),
        'storage' => $app->storagePath(),
        'database' => $app->databasePath('database.sqlite'),
        'config_cache' => $app->getCachedConfigPath(),
        'providers' => $app->getBootstrapProvidersPath(),
        'seeded' => @file_get_contents($app->databasePath('database.sqlite')),
    ]);
    PHP);
    file_put_contents($this->root.'/build.php', <<<'PHP'
    <?php
    $phar = new Phar($argv[1]);
    $phar->buildFromDirectory($argv[2]);
    $phar->setMetadata(['name' => 'PackagedProbe']);
    $phar->setStub("<?php Phar::interceptFileFuncs(); require 'phar://'.__FILE__.'/main.php'; __HALT_COMPILER();");
    PHP);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('moves storage, database and caches to the data directory and seeds it', function () {
    $build = new Process([PHP_BINARY, '-d', 'phar.readonly=0', $this->root.'/build.php', $this->root.'/probe.phar', $this->root.'/app']);
    $build->mustRun();

    $run = new Process([PHP_BINARY, $this->root.'/probe.phar'], null, [
        'HOME' => $this->root.'/home',
        'VENUSIAN_FRAMEWORK_AUTOLOAD' => dirname(__DIR__, 2).'/vendor/autoload.php',
    ]);
    $run->mustRun();
    $report = json_decode($run->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    $data = PHP_OS_FAMILY === 'Darwin'
        ? $this->root.'/home/Library/Application Support/PackagedProbe'
        : $this->root.'/home/.local/share/PackagedProbe';

    expect($report['packaged'])->toBeTrue()
        ->and($report['storage'])->toBe($data.'/storage')
        ->and($report['database'])->toBe($data.'/database/database.sqlite')
        ->and($report['config_cache'])->toBe($data.'/bootstrap/cache/config.php')
        ->and($report['providers'])->toBe('phar://'.realpath($this->root).'/probe.phar/bootstrap/providers.php')
        ->and($report['seeded'])->toBe('shipped');
});
