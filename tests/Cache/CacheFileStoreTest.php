<?php

use Voyager\Cache\FileStore;
use Voyager\Contracts\Filesystem\FileNotFoundException;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;

/**
 * A PHPUnit mock of the Filesystem, the way the test class used to build one via $this->createMock().
 *
 * createMock() is protected on PHPUnit's TestCase, so a plain function can't call
 * it on a passed-in $this directly (visibility is enforced by lexical scope, not
 * by the object). Going through test() re-invokes it via Pest's own reflection,
 * which is exempt from that check.
 */
function mockFileStoreFilesystem()
{
    return test()->createMock(Filesystem::class);
}

/** The on-disk cache path FileStore computes for the given key under __DIR__. */
function fileStoreCachePath($key)
{
    $hash = sha1($key);
    $cache_dir = substr($hash, 0, 2).'/'.substr($hash, 2, 2);

    return __DIR__.'/'.$cache_dir.'/'.$hash;
}

afterEach(function () {
    Carbon::setTestNow(null);
});

test('null is returned if the file does not exist', function () {
    $files = mockFileStoreFilesystem();
    $files->expects($this->once())->method('get')->will($this->throwException(new FileNotFoundException));
    $store = new FileStore($files, __DIR__);
    $value = $store->get('foo');

    expect($value)->toBeNull();
});

test('put creates missing directories', function () {
    $files = mockFileStoreFilesystem();
    $hash = sha1('foo');
    $contents = '0000000000';
    $full_dir = __DIR__.'/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
    $files->expects($this->once())->method('makeDirectory')->with($this->equalTo($full_dir), $this->equalTo(0777), $this->equalTo(true));
    $files->expects($this->once())->method('put')->with($this->equalTo($full_dir.'/'.$hash))->willReturn(strlen($contents));
    $store = new FileStore($files, __DIR__);
    $result = $store->put('foo', $contents, 0);

    expect($result)->toBeTrue();
});

test('put considers zero as eternal time', function () {
    $files = mockFileStoreFilesystem();

    $hash = sha1('O--L / key');
    $filePath = __DIR__.'/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
    $ten9s = '9999999999'; // The "forever" time value.
    $fileContents = $ten9s.serialize('gold');
    $exclusiveLock = true;

    $files->expects($this->once())->method('put')->with(
        $this->equalTo($filePath),
        $this->equalTo($fileContents),
        $this->equalTo($exclusiveLock) // Ensure we do lock the file while putting.
    )->willReturn(strlen($fileContents));

    (new FileStore($files, __DIR__))->put('O--L / key', 'gold', 0);
});

test('put considers big values as eternal time', function () {
    $files = mockFileStoreFilesystem();

    $hash = sha1('O--L / key');
    $filePath = __DIR__.'/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
    $ten9s = '9999999999'; // The "forever" time value.
    $fileContents = $ten9s.serialize('gold');

    $files->expects($this->once())->method('put')->with(
        $this->equalTo($filePath),
        $this->equalTo($fileContents),
    );

    (new FileStore($files, __DIR__))->put('O--L / key', 'gold', (int) $ten9s + 1);
});

test('expired items return null and get deleted', function () {
    $files = mockFileStoreFilesystem();
    $contents = '0000000000';
    $files->expects($this->once())->method('get')->willReturn($contents);
    $store = $this->getMockBuilder(FileStore::class)->onlyMethods(['forget'])->setConstructorArgs([$files, __DIR__])->getMock();
    $store->expects($this->once())->method('forget');
    $value = $store->get('foo');

    expect($value)->toBeNull();
});

test('a valid item returns its contents', function () {
    $files = mockFileStoreFilesystem();
    $contents = '9999999999'.serialize('Hello World');
    $files->expects($this->once())->method('get')->willReturn($contents);
    $store = new FileStore($files, __DIR__);

    expect($store->get('foo'))->toBe('Hello World');
});

test('storing an item properly stores its value', function () {
    $files = mockFileStoreFilesystem();
    $store = $this->getMockBuilder(FileStore::class)->onlyMethods(['expiration'])->setConstructorArgs([$files, __DIR__])->getMock();
    $store->expects($this->once())->method('expiration')->with($this->equalTo(10))->willReturn(1111111111);
    $contents = '1111111111'.serialize('Hello World');
    $hash = sha1('foo');
    $cache_dir = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
    $files->expects($this->once())->method('put')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$hash), $this->equalTo($contents))->willReturn(strlen($contents));
    $result = $store->put('foo', 'Hello World', 10);

    expect($result)->toBeTrue();
});

test('storing an item properly sets permissions', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldIgnoreMissing();
    $store = $this->getMockBuilder(FileStore::class)->onlyMethods(['expiration'])->setConstructorArgs([$files, __DIR__, 0644])->getMock();
    $hash = sha1('foo');
    $cache_dir = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
    $files->shouldReceive('put')->withArgs([__DIR__.'/'.$cache_dir.'/'.$hash, Mockery::any(), Mockery::any()])->andReturnUsing(function ($name, $value) {
        return strlen($value);
    });
    $files->shouldReceive('chmod')->withArgs([__DIR__.'/'.$cache_dir.'/'.$hash])->andReturnValues(['0600', '0644'])->times(3);
    $files->shouldReceive('chmod')->withArgs([__DIR__.'/'.$cache_dir.'/'.$hash, 0644])->andReturn(true)->once();
    $result = $store->put('foo', 'foo', 10);
    expect($result)->toBeTrue();

    $result = $store->put('foo', 'bar', 10);
    expect($result)->toBeTrue();

    $result = $store->put('foo', 'baz', 10);
    expect($result)->toBeTrue();
});

test('storing an item directory properly sets permissions', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldIgnoreMissing();
    $store = $this->getMockBuilder(FileStore::class)->onlyMethods(['expiration'])->setConstructorArgs([$files, __DIR__, 0606])->getMock();
    $hash = sha1('foo');
    $cache_parent_dir = substr($hash, 0, 2);
    $cache_dir = $cache_parent_dir.'/'.substr($hash, 2, 2);

    $files->shouldReceive('put')->withArgs([__DIR__.'/'.$cache_dir.'/'.$hash, Mockery::any(), Mockery::any()])->andReturnUsing(function ($name, $value) {
        return strlen($value);
    });

    $files->shouldReceive('exists')->withArgs([__DIR__.'/'.$cache_dir])->andReturn(false)->once();
    $files->shouldReceive('makeDirectory')->withArgs([__DIR__.'/'.$cache_dir, 0777, true, true])->once();
    $files->shouldReceive('chmod')->withArgs([__DIR__.'/'.$cache_parent_dir])->andReturn('0600')->once();
    $files->shouldReceive('chmod')->withArgs([__DIR__.'/'.$cache_parent_dir, 0606])->andReturn(true)->once();
    $files->shouldReceive('chmod')->withArgs([__DIR__.'/'.$cache_dir])->andReturn('0600')->once();
    $files->shouldReceive('chmod')->withArgs([__DIR__.'/'.$cache_dir, 0606])->andReturn(true)->once();

    $result = $store->put('foo', 'foo', 10);

    expect($result)->toBeTrue();
});

test('forever items are stored with a high timestamp', function () {
    $files = mockFileStoreFilesystem();
    $contents = '9999999999'.serialize('Hello World');
    $hash = sha1('foo');
    $cache_dir = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
    $files->expects($this->once())->method('put')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$hash), $this->equalTo($contents))->willReturn(strlen($contents));
    $store = new FileStore($files, __DIR__);
    $result = $store->forever('foo', 'Hello World', 10);

    expect($result)->toBeTrue();
});

test('forever items are not removed on increment', function () {
    $files = mockFileStoreFilesystem();
    $contents = '9999999999'.serialize('Hello World');
    $store = new FileStore($files, __DIR__);
    $store->forever('foo', 'Hello World');
    $store->increment('foo');
    $files->expects($this->once())->method('get')->willReturn($contents);

    expect($store->get('foo'))->toBe('Hello World');
});

test('increment on expired keys', function () {
    Carbon::setTestNow(Carbon::now());

    $filePath = fileStoreCachePath('foo');
    $files = mockFileStoreFilesystem();
    $now = Carbon::now()->getTimestamp();
    $initialValue = ($now - 10).serialize(77);
    $valueAfterIncrement = '9999999999'.serialize(3);
    $store = new FileStore($files, __DIR__);

    $files->expects($this->once())->method('get')->with($this->equalTo($filePath), $this->equalTo(true))->willReturn($initialValue);
    $files->expects($this->once())->method('put')->with($this->equalTo($filePath), $this->equalTo($valueAfterIncrement));

    $store->increment('foo', 3);
});

test('increment can atomically jump', function () {
    $filePath = fileStoreCachePath('foo');
    $files = mockFileStoreFilesystem();
    $initialValue = '9999999999'.serialize(1);
    $valueAfterIncrement = '9999999999'.serialize(4);
    $store = new FileStore($files, __DIR__);

    $files->expects($this->once())->method('get')->with($this->equalTo($filePath), $this->equalTo(true))->willReturn($initialValue);
    $files->expects($this->once())->method('put')->with($this->equalTo($filePath), $this->equalTo($valueAfterIncrement));

    $result = $store->increment('foo', 3);

    expect($result)->toEqual(4);
});

test('decrement can atomically jump', function () {
    $filePath = fileStoreCachePath('foo');

    $files = mockFileStoreFilesystem();
    $initialValue = '9999999999'.serialize(2);
    $valueAfterIncrement = '9999999999'.serialize(0);
    $store = new FileStore($files, __DIR__);

    $files->expects($this->once())->method('get')->with($this->equalTo($filePath), $this->equalTo(true))->willReturn($initialValue);
    $files->expects($this->once())->method('put')->with($this->equalTo($filePath), $this->equalTo($valueAfterIncrement));

    $result = $store->decrement('foo', 2);

    expect($result)->toEqual(0);
});

test('incrementing non-numeric values', function () {
    $filePath = fileStoreCachePath('foo');

    $files = mockFileStoreFilesystem();
    $initialValue = '1999999909'.serialize('foo');
    $valueAfterIncrement = '1999999909'.serialize(1);
    $store = new FileStore($files, __DIR__);
    $files->expects($this->once())->method('get')->with($this->equalTo($filePath), $this->equalTo(true))->willReturn($initialValue);
    $files->expects($this->once())->method('put')->with($this->equalTo($filePath), $this->equalTo($valueAfterIncrement));
    $result = $store->increment('foo');

    expect($result)->toEqual(1);
});

test('incrementing non-existent keys', function () {
    $filePath = fileStoreCachePath('foo');

    $files = mockFileStoreFilesystem();
    $valueAfterIncrement = '9999999999'.serialize(1);
    $store = new FileStore($files, __DIR__);
    // simulates a missing item in file store by the exception
    $files->expects($this->once())->method('get')->with($this->equalTo($filePath), $this->equalTo(true))->willThrowException(new Exception);
    $files->expects($this->once())->method('put')->with($this->equalTo($filePath), $this->equalTo($valueAfterIncrement));
    $result = $store->increment('foo');

    expect($result)->toBeInt()
        ->toEqual(1);
});

test('increment does not extend the cache life', function () {
    Carbon::setTestNow(Carbon::now());

    $files = mockFileStoreFilesystem();
    $expiration = Carbon::now()->addSeconds(50)->getTimestamp();
    $initialValue = $expiration.serialize(1);
    $valueAfterIncrement = $expiration.serialize(2);
    $store = new FileStore($files, __DIR__);
    $files->expects($this->once())->method('get')->willReturn($initialValue);
    $hash = sha1('foo');
    $cache_dir = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
    $files->expects($this->once())->method('put')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$hash), $this->equalTo($valueAfterIncrement));

    $store->increment('foo');
});

test('remove ignores a file that does not exist', function () {
    $files = mockFileStoreFilesystem();
    $hash = sha1('foobull');
    $cache_dir = substr($hash, 0, 2).'/'.substr($hash, 2, 2);
    $files->expects($this->once())->method('exists')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$hash))->willReturn(false);
    $store = new FileStore($files, __DIR__);

    $store->forget('foobull');
});

test('remove deletes the file', function () {
    $files = new Filesystem;
    $store = new FileStore($files, __DIR__);
    $store->put('foobar', 'Hello Baby', 10);

    expect($store->path('foobar'))->toBeFile();

    $store->forget('foobar');

    expect($store->path('foobar'))->not->toBeFile();
});

test('flush cleans the directory', function () {
    $files = mockFileStoreFilesystem();
    $files->expects($this->once())->method('isDirectory')->with($this->equalTo(__DIR__))->willReturn(true);
    $files->expects($this->once())->method('directories')->with($this->equalTo(__DIR__))->willReturn(['foo']);
    $files->expects($this->once())->method('deleteDirectory')->with($this->equalTo('foo'))->willReturn(true);

    $store = new FileStore($files, __DIR__);
    $result = $store->flush();

    expect($result)->toBeTrue();
});

test('flush fails to clean the directory', function () {
    $files = mockFileStoreFilesystem();
    $files->expects($this->once())->method('isDirectory')->with($this->equalTo(__DIR__))->willReturn(true);
    $files->expects($this->once())->method('directories')->with($this->equalTo(__DIR__))->willReturn(['foo']);
    $files->expects($this->once())->method('deleteDirectory')->with($this->equalTo('foo'))->willReturn(false);

    $store = new FileStore($files, __DIR__);
    $result = $store->flush();

    expect($result)->toBeFalse();
});

test('flush ignores a non-existing directory', function () {
    $files = mockFileStoreFilesystem();
    $files->expects($this->once())->method('isDirectory')->with($this->equalTo(__DIR__.'--wrong'))->willReturn(false);

    $store = new FileStore($files, __DIR__.'--wrong');
    $result = $store->flush();

    expect($result)->toBeFalse();
});

test('it handles forgetting non-flexible keys', function () {
    $store = new FileStore(new Filesystem, __DIR__);

    $key = Str::random();
    $path = $store->path($key);
    $flexiblePath = "illuminate:cache:flexible:created:{$key}";

    $store->put($key, 'value', 5);

    expect($path)->toBeFile()
        ->and($flexiblePath)->not->toBeFile();

    $store->forget($key);

    expect($path)->not->toBeFile()
        ->and($flexiblePath)->not->toBeFile();
});

// The upstream method below (`itOnlyForgetsFlexibleKeysIfParentIsForgotten`) was
// never actually run by PHPUnit: its name doesn't start with `test` and it
// carries no `@test`/#[Test] marker, so PHPUnit's own discovery skipped it
// silently. Preserved verbatim, still unwired, so this conversion stays
// behavior-identical to the baseline rather than newly running a case that
// may or may not still pass.
//
// function itOnlyForgetsFlexibleKeysIfParentIsForgotten()
// {
//     $store = new FileStore(new Filesystem, __DIR__);
//
//     $key = Str::random();
//     $path = $store->path($key);
//     $flexiblePath = "illuminate:cache:flexible:created:{$key}";
//
//     touch($flexiblePath);
//
//     expect($path)->not->toBeFile()
//         ->and($flexiblePath)->toBeFile();
//
//     $store->forget($key);
//
//     expect($path)->not->toBeFile()
//         ->and($flexiblePath)->toBeFile();
//
//     $store->put($key, 'value', 5);
//
//     expect($path)->not->toBeFile()
//         ->and($flexiblePath)->not->toBeFile();
// }
