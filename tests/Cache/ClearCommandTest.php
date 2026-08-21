<?php

use Tests\Cache\Fixtures\ClearCommandTestStub;
use Voyager\Cache\CacheManager;
use Voyager\Contracts\Cache\Repository;
use Voyager\Filesystem\Filesystem;
use Voyager\System\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/** Run the given command with the given input, the way the test class used to. */
function runClearCommand($command, $input = [])
{
    return $command->run(new ArrayInput($input), new NullOutput);
}

beforeEach(function () {
    $this->cacheManager = Mockery::mock(CacheManager::class);
    $this->files = Mockery::mock(Filesystem::class);
    $this->cacheRepository = Mockery::mock(Repository::class);
    $this->command = new ClearCommandTestStub($this->cacheManager, $this->files);

    $app = new Application;
    $app['path.storage'] = __DIR__;
    $this->command->setVenusian($app);
});

test('clear with no store argument', function () {
    $this->files->shouldReceive('exists')->andReturn(true);
    $this->files->shouldReceive('files')->andReturn([]);

    $this->cacheManager->shouldReceive('store')->once()->with(null)->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('flush')->once();

    runClearCommand($this->command);
});

test('clear with store argument', function () {
    $this->files->shouldReceive('exists')->andReturn(true);
    $this->files->shouldReceive('files')->andReturn([]);

    $this->cacheManager->shouldReceive('store')->once()->with('foo')->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('flush')->once();

    runClearCommand($this->command, ['store' => 'foo']);
});

test('clear with invalid store argument', function () {
    $this->expectException(InvalidArgumentException::class);

    $this->files->shouldReceive('files')->andReturn([]);

    $this->cacheManager->shouldReceive('store')->once()->with('bar')->andThrow(InvalidArgumentException::class);
    $this->cacheRepository->shouldReceive('flush')->never();

    runClearCommand($this->command, ['store' => 'bar']);
});

test('clear with tags option', function () {
    $this->files->shouldReceive('exists')->andReturn(true);
    $this->files->shouldReceive('files')->andReturn([]);

    $this->cacheManager->shouldReceive('store')->once()->with(null)->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('tags')->once()->with(['foo', 'bar'])->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('flush')->once();

    runClearCommand($this->command, ['--tags' => 'foo,bar']);
});

test('clear with store argument and tags option', function () {
    $this->files->shouldReceive('exists')->andReturn(true);
    $this->files->shouldReceive('files')->andReturn([]);

    $this->cacheManager->shouldReceive('store')->once()->with('redis')->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('tags')->once()->with(['foo'])->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('flush')->once();

    runClearCommand($this->command, ['store' => 'redis', '--tags' => 'foo']);
});

test('clear will clear real time facades', function () {
    $this->cacheManager->shouldReceive('store')->once()->with(null)->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('flush')->once();

    $this->files->shouldReceive('exists')->andReturn(true);
    $this->files->shouldReceive('files')->andReturn(['/magic-alias-XXXX.php']);
    $this->files->shouldReceive('delete')->with('/magic-alias-XXXX.php')->once();

    runClearCommand($this->command);
});

test('clear will not clear real time facades if the cache directory does not exist', function () {
    $this->cacheManager->shouldReceive('store')->once()->with(null)->andReturn($this->cacheRepository);
    $this->cacheRepository->shouldReceive('flush')->once();

    // No files should be looped over and nothing should be deleted if the cache directory doesn't exist
    $this->files->shouldReceive('exists')->andReturn(false);
    $this->files->shouldNotReceive('files');
    $this->files->shouldNotReceive('delete');

    runClearCommand($this->command);
});
