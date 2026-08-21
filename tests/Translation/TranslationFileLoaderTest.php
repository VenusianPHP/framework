<?php

use Voyager\Filesystem\Filesystem;
use Voyager\Translation\FileLoader;

test('load method loads translations from added path', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/another');

    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->shouldReceive('exists')->once()->with(__DIR__.'/another/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/another/en/messages.php')->andReturn(['baz' => 'backagesplash']);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'bar', 'baz' => 'backagesplash']);
});

test('load method handles missing added path', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/missing');

    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->shouldReceive('exists')->once()->with(__DIR__.'/missing/en/messages.php')->andReturn(false);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'bar']);
});

test('load method overwrites existing keys from added path', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/another');

    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->shouldReceive('exists')->once()->with(__DIR__.'/another/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/another/en/messages.php')->andReturn(['foo' => 'baz']);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'baz']);
});

test('load method loads translations from multiple added paths', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/another');
    $loader->addPath(__DIR__.'/yet-another');

    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->shouldReceive('exists')->once()->with(__DIR__.'/another/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/another/en/messages.php')->andReturn(['baz' => 'backagesplash']);

    $files->shouldReceive('exists')->once()->with(__DIR__.'/yet-another/en/messages.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/yet-another/en/messages.php')->andReturn(['qux' => 'quux']);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'bar', 'baz' => 'backagesplash', 'qux' => 'quux']);
});

test('load method without namespaces properly calls loader', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/foo.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/en/foo.php')->andReturn(['messages']);

    expect($loader->load('en', 'foo', null))->toEqual(['messages']);
});

test('load method without namespaces properly calls loader with multiple paths', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), [__DIR__, __DIR__.'/second']);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/second/en/foo.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/en/foo.php')->andReturn(['messages' => 'first']);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/second/en/foo.php')->andReturn(['messages' => 'second']);

    expect($loader->load('en', 'foo', null))->toEqual(['messages' => 'second']);
});

test('load method with namespaces properly calls loader', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $files->shouldReceive('exists')->once()->with('bar/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(false);
    $files->shouldReceive('getRequire')->once()->with('bar/en/foo.php')->andReturn(['foo' => 'bar']);
    $loader->addNamespace('namespace', 'bar');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'bar']);
});

test('load method with namespaces properly calls loader with multiple paths', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), [__DIR__, __DIR__.'/second']);
    $files->shouldReceive('exists')->once()->with('test-namespace-dir/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(false);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(false);
    $files->shouldReceive('getRequire')->once()->with('test-namespace-dir/en/foo.php')->andReturn(['foo' => 'bar']);
    $loader->addNamespace('namespace', 'test-namespace-dir');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'bar']);
});

test('load method with namespaces properly calls loader and loads local overrides', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $files->shouldReceive('exists')->once()->with('bar/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with('bar/en/foo.php')->andReturn(['foo' => 'bar']);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override', 'baz' => 'boom']);
    $loader->addNamespace('namespace', 'bar');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'override', 'baz' => 'boom']);
});

test('load method with namespaces properly calls loader and loads local overrides with multiple paths', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), [__DIR__, __DIR__.'/second']);
    $files->shouldReceive('exists')->once()->with('test-namespace-dir/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with('test-namespace-dir/en/foo.php')->andReturn(['foo' => 'bar']);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override', 'baz' => 'boom']);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override-2', 'baz' => 'boom-2']);
    $loader->addNamespace('namespace', 'test-namespace-dir');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'override-2', 'baz' => 'boom-2']);
});

test('load method with namespaces properly calls loader and loads local overrides with multiple paths with missing key', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), [__DIR__, __DIR__.'/second']);
    $files->shouldReceive('exists')->once()->with('test-namespace-dir/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with('test-namespace-dir/en/foo.php')->andReturn(['foo' => 'bar']);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override', 'baz' => 'boom']);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(['baz' => 'boom-2']);
    $loader->addNamespace('namespace', 'test-namespace-dir');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'override', 'baz' => 'boom-2']);
});

test('empty arrays returned when files dont exist', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/en/foo.php')->andReturn(false);
    $files->shouldReceive('getRequire')->never();

    expect($loader->load('en', 'foo', null))->toEqual([]);
});

test('empty arrays returned when files dont exist for namespaced items', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $files->shouldReceive('getRequire')->never();

    expect($loader->load('en', 'foo', 'bar'))->toEqual([]);
});

test('load method for JSON properly calls loader', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/en.json')->andReturn(true);
    $files->shouldReceive('get')->once()->with(__DIR__.'/en.json')->andReturn('{"foo":"bar"}');

    expect($loader->load('en', '*', '*'))->toEqual(['foo' => 'bar']);
});

test('load method for JSON properly calls loader for multiple paths', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $loader->addJsonPath(__DIR__.'/another');

    $files->shouldReceive('exists')->once()->with(__DIR__.'/en.json')->andReturn(true);
    $files->shouldReceive('exists')->once()->with(__DIR__.'/another/en.json')->andReturn(true);
    $files->shouldReceive('get')->once()->with(__DIR__.'/en.json')->andReturn('{"foo":"bar"}');
    $files->shouldReceive('get')->once()->with(__DIR__.'/another/en.json')->andReturn('{"foo":"backagebar", "baz": "backagesplash"}');

    expect($loader->load('en', '*', '*'))->toEqual(['foo' => 'bar', 'baz' => 'backagesplash']);
});

test('load method throw exception when provide invalid JSON', function () {
    $loader = new FileLoader($files = Mockery::mock(Filesystem::class), __DIR__);
    $loader->addJsonPath(__DIR__.'/invalid');

    $invalidJsonString = '.{"foo":"cricket", "baz": "football"}';
    $files->shouldReceive('exists')->once()->with(__DIR__.'/invalid/en.json')->andReturn(true);
    $files->shouldReceive('get')->once()->with(__DIR__.'/invalid/en.json')->andReturn($invalidJsonString);

    $loader->load('en', '*', '*');
})->throws(\RuntimeException::class);

test('all registered namespace return properly', function () {
    $loader = new FileLoader(Mockery::mock(Filesystem::class), __DIR__);
    $loader->addNamespace('namespace', 'foo');
    $loader->addNamespace('namespace2', 'bar');

    expect($loader->namespaces())->toEqual(['namespace' => 'foo', 'namespace2' => 'bar']);
});

test('all added json paths return properly', function () {
    $loader = new FileLoader(Mockery::mock(Filesystem::class), __DIR__);
    $path1 = __DIR__.'/another';
    $path2 = __DIR__.'/another2';
    $loader->addJsonPath($path1);
    $loader->addJsonPath($path2);

    expect($loader->jsonPaths())->toEqual([$path1, $path2]);
});

test('all added paths return properly', function () {
    $loader = new FileLoader(Mockery::mock(Filesystem::class), __DIR__);
    $path1 = __DIR__.'/another';
    $path2 = __DIR__.'/another2';
    $loader->addPath($path1);
    $loader->addPath($path2);

    expect(array_slice($loader->paths(), 1))->toEqual([$path1, $path2]);
});
