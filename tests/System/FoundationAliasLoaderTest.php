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
