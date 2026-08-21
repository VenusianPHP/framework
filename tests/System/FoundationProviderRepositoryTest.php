<?php

use Voyager\Contracts\System\Application as ApplicationContract;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\System\Application;
use Voyager\System\ProviderRepository;

/** A provider repository writing its manifest to the given path. */
function providerRepositoryFor(string $manifestPath, ?Filesystem $files = null): ProviderRepository
{
    return new ProviderRepository(
        Mockery::mock(ApplicationContract::class),
        $files ?? new Filesystem,
        $manifestPath
    );
}

test('services are registered from a manifest that needs no recompiling', function () {
    $app = Mockery::mock(Application::class);

    $repo = Mockery::mock(ProviderRepository::class.'[createProvider,loadManifest,shouldRecompile]', [$app, Mockery::mock(Filesystem::class), __DIR__.'/services.php']);
    $repo->shouldReceive('loadManifest')->once()->andReturn(['eager' => ['foo'], 'deferred' => ['deferred'], 'providers' => ['providers'], 'when' => []]);
    $repo->shouldReceive('shouldRecompile')->once()->andReturn(false);

    $app->shouldReceive('register')->once()->with('foo');
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $app->shouldReceive('addDeferredServices')->once()->with(['deferred']);

    $repo->load([]);
});

test('a recompiled manifest splits eager providers from deferred ones', function () {
    $app = Mockery::mock(Application::class);

    $repo = Mockery::mock(ProviderRepository::class.'[createProvider,loadManifest,writeManifest,shouldRecompile]', [$app, Mockery::mock(Filesystem::class), __DIR__.'/services.php']);

    $repo->shouldReceive('loadManifest')->once()->andReturn(['eager' => [], 'deferred' => ['deferred']]);
    $repo->shouldReceive('shouldRecompile')->once()->andReturn(true);

    // foo mock is just a deferred provider
    $repo->shouldReceive('createProvider')->once()->with('foo')->andReturn($fooMock = Mockery::mock(stdClass::class));
    $fooMock->shouldReceive('isDeferred')->once()->andReturn(true);
    $fooMock->shouldReceive('provides')->once()->andReturn(['foo.provides1', 'foo.provides2']);
    $fooMock->shouldReceive('when')->once()->andReturn([]);

    // bar mock is added to eagers since it's not reserved
    $repo->shouldReceive('createProvider')->once()->with('bar')->andReturn($barMock = Mockery::mock(ServiceProvider::class));
    $barMock->shouldReceive('isDeferred')->once()->andReturn(false);
    $repo->shouldReceive('writeManifest')->once()->andReturnUsing(function ($manifest) {
        return $manifest;
    });

    $app->shouldReceive('register')->once()->with('bar');
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $app->shouldReceive('addDeferredServices')->once()->with(['foo.provides1' => 'foo', 'foo.provides2' => 'foo']);

    $repo->load(['foo', 'bar']);
});

test('shouldRecompile is true for a missing or stale manifest', function () {
    $repo = providerRepositoryFor(__DIR__.'/services.php');

    expect($repo->shouldRecompile(null, []))->toBeTrue()
        ->and($repo->shouldRecompile(['providers' => ['foo']], ['foo', 'bar']))->toBeTrue()
        ->and($repo->shouldRecompile(['providers' => ['foo']], ['foo']))->toBeFalse();
});

test('loadManifest returns the parsed manifest', function () {
    $repo = providerRepositoryFor(__DIR__.'/services.php', $files = Mockery::mock(Filesystem::class));
    $files->shouldReceive('exists')->once()->with(__DIR__.'/services.php')->andReturn(true);
    $files->shouldReceive('getRequire')->once()->with(__DIR__.'/services.php')->andReturn($array = ['users' => ['dayle' => true], 'when' => []]);

    expect($repo->loadManifest())->toEqual($array);
});

test('writeManifest stores the manifest at the configured path', function () {
    $repo = providerRepositoryFor(__DIR__.'/services.php', $files = Mockery::mock(Filesystem::class));
    $files->shouldReceive('replace')->once()->with(__DIR__.'/services.php', '<?php return '.var_export(['foo'], true).';');

    $result = $repo->writeManifest(['foo']);

    expect($result)->toEqual(['foo', 'when' => []]);
});

test('writeManifest throws when the manifest directory is missing', function () {
    $this->expectException(Exception::class);
    $this->expectExceptionMessageMatches('/^The (.*) directory must be present and writable.$/');

    $repo = providerRepositoryFor(__DIR__.'/cache/services.php', $files = Mockery::mock(Filesystem::class));
    $files->shouldReceive('replace')->never();

    $repo->writeManifest(['foo']);
});
