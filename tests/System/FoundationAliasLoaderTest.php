<?php

use Tests\System\Stubs\FoundationAliasLoaderStub;
use Voyager\System\AliasLoader;

beforeEach(function () {
    AliasLoader::setInstance(null);
    AliasLoader::setMagicAliasNamespace('MagicAliases\\');
});

test('the loader carries its aliases and registers once', function () {
    $loader = AliasLoader::getInstance(['foo' => 'bar']);

    expect($loader->getAliases())->toEqual(['foo' => 'bar'])
        ->and($loader->isRegistered())->toBeFalse();

    $loader->register();

    expect($loader->isRegistered())->toBeTrue();
});

test('getInstance returns the one instance', function () {
    $loader = AliasLoader::getInstance(['foo' => 'bar']);

    expect(AliasLoader::getInstance())->toBe($loader);
});

test('getInstance merges further aliases in, later keys winning', function () {
    $loader = AliasLoader::getInstance(['foo' => 'bar']);
    expect($loader->getAliases())->toEqual(['foo' => 'bar']);

    $loader = AliasLoader::getInstance(['foo2' => 'bar2']);
    expect($loader->getAliases())->toEqual(['foo2' => 'bar2', 'foo' => 'bar']);

    // override keys
    $loader = AliasLoader::getInstance(['foo' => 'baz']);
    expect($loader->getAliases())->toEqual(['foo2' => 'bar2', 'foo' => 'baz']);
});

test('load aliases a known class and ignores an unknown one', function () {
    $loader = AliasLoader::getInstance(['some_alias_foo_bar' => FoundationAliasLoaderStub::class]);

    $result = $loader->load('some_alias_foo_bar');

    expect(new \some_alias_foo_bar)->toBeInstanceOf(FoundationAliasLoaderStub::class)
        ->and($result)->toBeTrue()
        ->and($loader->load('bar'))->toBeNull();
});

test('setAliases replaces the alias map', function () {
    $loader = AliasLoader::getInstance();
    $loader->setAliases(['some_alias_foo' => FoundationAliasLoaderStub::class]);

    $result = $loader->load('some_alias_foo');

    expect(new \some_alias_foo)->toBeInstanceOf(FoundationAliasLoaderStub::class)
        ->and($result)->toBeTrue();
});

test('default aliases ship every framework magic alias outside config', function () {
    $aliases = AliasLoader::defaultAliases()->all();

    expect($aliases)->toHaveCount(26)
        ->and($aliases)->toBe([
            'App' => \Voyager\NutsAndBolts\MagicAliases\App::class,
            'Broadcast' => \Voyager\NutsAndBolts\MagicAliases\Broadcast::class,
            'Bus' => \Voyager\NutsAndBolts\MagicAliases\Bus::class,
            'Cache' => \Voyager\NutsAndBolts\MagicAliases\Cache::class,
            'Computer' => \Voyager\NutsAndBolts\MagicAliases\Computer::class,
            'Concurrency' => \Voyager\NutsAndBolts\MagicAliases\Concurrency::class,
            'Config' => \Voyager\NutsAndBolts\MagicAliases\Config::class,
            'Context' => \Voyager\NutsAndBolts\MagicAliases\Context::class,
            'Crypt' => \Voyager\NutsAndBolts\MagicAliases\Crypt::class,
            'Date' => \Voyager\NutsAndBolts\MagicAliases\Date::class,
            'DB' => \Voyager\NutsAndBolts\MagicAliases\DB::class,
            'Event' => \Voyager\NutsAndBolts\MagicAliases\Event::class,
            'File' => \Voyager\NutsAndBolts\MagicAliases\File::class,
            'Hash' => \Voyager\NutsAndBolts\MagicAliases\Hash::class,
            'Http' => \Voyager\NutsAndBolts\MagicAliases\Http::class,
            'Lang' => \Voyager\NutsAndBolts\MagicAliases\Lang::class,
            'Log' => \Voyager\NutsAndBolts\MagicAliases\Log::class,
            'Notification' => \Voyager\NutsAndBolts\MagicAliases\Notification::class,
            'ParallelTesting' => \Voyager\NutsAndBolts\MagicAliases\ParallelTesting::class,
            'Pipeline' => \Voyager\NutsAndBolts\MagicAliases\Pipeline::class,
            'Process' => \Voyager\NutsAndBolts\MagicAliases\Process::class,
            'Queue' => \Voyager\NutsAndBolts\MagicAliases\Queue::class,
            'Redis' => \Voyager\NutsAndBolts\MagicAliases\Redis::class,
            'Schema' => \Voyager\NutsAndBolts\MagicAliases\Schema::class,
            'Storage' => \Voyager\NutsAndBolts\MagicAliases\Storage::class,
            'Validator' => \Voyager\NutsAndBolts\MagicAliases\Validator::class,
        ]);
});
