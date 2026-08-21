<?php

use Orchestra\Testbench\TestCase;
use Voyager\MagicAliases\Vite as ViteFacade;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\Js;
use Voyager\System\Vite;
use Voyager\System\ViteException;
use Voyager\System\ViteManifestNotFoundException;

uses(TestCase::class);

/** Write a Vite manifest into the public build directory, defaulting to a small one. */
function makeViteManifest($contents = null, $path = 'build')
{
    app()->usePublicPath(__DIR__);

    if (! file_exists(public_path($path))) {
        mkdir(public_path($path));
    }

    $manifest = json_encode($contents ?? [
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
        ],
        'resources/js/app-with-css-import.js' => [
            'src' => 'resources/js/app-with-css-import.js',
            'file' => 'assets/app-with-css-import.versioned.js',
            'css' => [
                'assets/imported-css.versioned.css',
            ],
        ],
        'resources/css/imported-css.css' => [
            // 'src' => 'resources/css/imported-css.css',
            'file' => 'assets/imported-css.versioned.css',
        ],
        'resources/js/app-with-shared-css.js' => [
            'src' => 'resources/js/app-with-shared-css.js',
            'file' => 'assets/app-with-shared-css.versioned.js',
            'imports' => [
                '_someFile.js',
            ],
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
        ],
        '_someFile.js' => [
            'file' => 'assets/someFile.versioned.js',
            'css' => [
                'assets/shared-css.versioned.css',
            ],
        ],
        'resources/css/shared-css' => [
            'src' => 'resources/css/shared-css',
            'file' => 'assets/shared-css.versioned.css',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents(public_path("{$path}/manifest.json"), $manifest);
}

/** Remove a manifest written by makeViteManifest(), and its directory. */
function cleanViteManifest($path = 'build')
{
    if (file_exists(public_path("{$path}/manifest.json"))) {
        unlink(public_path("{$path}/manifest.json"));
    }

    if (file_exists(public_path($path))) {
        rmdir(public_path($path));
    }
}

/** Write a built asset into the public build directory. */
function makeViteAsset($asset, $content)
{
    $path = public_path('build/assets');

    if (! file_exists($path)) {
        mkdir($path, recursive: true);
    }

    file_put_contents($path.'/'.$asset, $content);
}

/** Remove an asset written by makeViteAsset(), and its directory. */
function cleanViteAsset($asset)
{
    $path = public_path('build/assets');

    unlink($path.$asset);

    rmdir($path);
}

/** Write the hot file, putting Vite into dev server mode. */
function makeViteHotFile($path = null)
{
    app()->usePublicPath(__DIR__);

    $path ??= public_path('hot');

    file_put_contents($path, 'http://localhost:3000');
}

/** Remove the hot file, putting Vite back into build mode. */
function cleanViteHotFile($path = null)
{
    $path ??= public_path('hot');

    if (file_exists($path)) {
        unlink($path);
    }
}

beforeEach(function () {
    app('config')->set('app.asset_url', 'https://example.com');
});

afterEach(function () {
    cleanViteManifest();
    cleanViteHotFile();
});

test('a js entry point renders one module script', function () {
    makeViteManifest();

    $result = app(Vite::class)('resources/js/app.js');

    expect($result->toHtml())->toEndWith('<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>');
});

test('a css and a js entry point render a stylesheet and a script', function () {
    makeViteManifest();

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" />'
        .'<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>');
});

test('css imported by an entry point is rendered as a stylesheet', function () {
    makeViteManifest();

    $result = app(Vite::class)('resources/js/app-with-css-import.js');

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/imported-css.versioned.css" />'
        .'<script type="module" src="https://example.com/build/assets/app-with-css-import.versioned.js"></script>');
});

test('css shared through an import is rendered as a stylesheet', function () {
    makeViteManifest();

    $result = app(Vite::class)(['resources/js/app-with-shared-css.js']);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/shared-css.versioned.css" />'
        .'<script type="module" src="https://example.com/build/assets/app-with-shared-css.versioned.js"></script>');
});

test('hot module replacement serves the js entry point from the dev server', function () {
    makeViteHotFile();

    $result = app(Vite::class)('resources/js/app.js');

    expect($result->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client"></script>'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>');
});

test('hot module replacement serves both entry points from the dev server', function () {
    makeViteHotFile();

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client"></script>'
        .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" />'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>');
});

test('a csp nonce is generated and applied in hot mode', function () {
    Str::createRandomStringsUsing(fn ($length) => "random-string-with-length:{$length}");
    makeViteHotFile();

    $nonce = ViteFacade::useCspNonce();
    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($nonce)->toBe('random-string-with-length:40');
    expect(ViteFacade::cspNonce())->toBe('random-string-with-length:40');
    expect($result->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client" nonce="random-string-with-length:40"></script>'
        .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" nonce="random-string-with-length:40" />'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js" nonce="random-string-with-length:40"></script>');

    Str::createRandomStringsNormally();
});

test('a csp nonce is generated and applied in build mode', function () {
    Str::createRandomStringsUsing(fn ($length) => "random-string-with-length:{$length}");
    makeViteManifest();

    $nonce = ViteFacade::useCspNonce();
    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($nonce)->toBe('random-string-with-length:40');
    expect(ViteFacade::cspNonce())->toBe('random-string-with-length:40');
    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" nonce="random-string-with-length:40" />'
        .'<script type="module" src="https://example.com/build/assets/app.versioned.js" nonce="random-string-with-length:40"></script>');

    Str::createRandomStringsNormally();
});

test('a csp nonce can be given in hot mode', function () {
    makeViteHotFile();

    $nonce = ViteFacade::useCspNonce('expected-nonce');
    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($nonce)->toBe('expected-nonce');
    expect(ViteFacade::cspNonce())->toBe('expected-nonce');
    expect($result->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client" nonce="expected-nonce"></script>'
        .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" nonce="expected-nonce" />'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js" nonce="expected-nonce"></script>');
});

test('a csp nonce can be given in build mode', function () {
    makeViteManifest();

    $nonce = ViteFacade::useCspNonce('expected-nonce');
    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($nonce)->toBe('expected-nonce');
    expect(ViteFacade::cspNonce())->toBe('expected-nonce');
    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" nonce="expected-nonce" />'
        .'<script type="module" src="https://example.com/build/assets/app.versioned.js" nonce="expected-nonce"></script>');
});

test('the react refresh runtime carries no nonce by default', function () {
    makeViteHotFile();

    $result = app(Vite::class)->reactRefresh();

    expect($result)->not->toContain('nonce');
});

test('the react refresh runtime carries the csp nonce', function () {
    makeViteHotFile();

    $nonce = ViteFacade::useCspNonce('expected-nonce');
    $result = app(Vite::class)->reactRefresh();

    expect($result)->toContain(sprintf('nonce="%s"', $nonce));
});

test('an integrity hash in the manifest is rendered', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'integrity' => 'expected-app.js-integrity',
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
            'integrity' => 'expected-app.css-integrity',
        ],
    ], $buildDir);

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js'], $buildDir);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" integrity="expected-app.css-integrity" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>');

    cleanViteManifest($buildDir);
});

test('an integrity hash is rendered for directly imported css', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'css' => [
                'assets/direct-css-dependency.aabbcc.css',
            ],
            'integrity' => 'expected-app.js-integrity',
        ],
        '_import.versioned.js' => [
            'file' => 'assets/import.versioned.js',
            'css' => [
                'assets/imported-css.versioned.css',
            ],
            'integrity' => 'expected-import.js-integrity',
        ],
        'imported-css.css' => [
            'file' => 'assets/direct-css-dependency.aabbcc.css',
            'integrity' => 'expected-imported-css.css-integrity',
        ],
    ], $buildDir);

    $result = app(Vite::class)('resources/js/app.js', $buildDir);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/direct-css-dependency.aabbcc.css" integrity="expected-imported-css.css-integrity" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>');

    cleanViteManifest($buildDir);
});

test('an integrity hash is rendered for transitively imported css', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'imports' => [
                '_import.versioned.js',
            ],
            'integrity' => 'expected-app.js-integrity',
        ],
        '_import.versioned.js' => [
            'file' => 'assets/import.versioned.js',
            'css' => [
                'assets/imported-css.versioned.css',
            ],
            'integrity' => 'expected-import.js-integrity',
        ],
        'imported-css.css' => [
            'file' => 'assets/imported-css.versioned.css',
            'integrity' => 'expected-imported-css.css-integrity',
        ],
    ], $buildDir);

    $result = app(Vite::class)('resources/js/app.js', $buildDir);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/imported-css.versioned.css" integrity="expected-imported-css.css-integrity" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>');

    cleanViteManifest($buildDir);
});

test('the manifest key holding the integrity hash can be changed', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'different-integrity-key' => 'expected-app.js-integrity',
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
            'different-integrity-key' => 'expected-app.css-integrity',
        ],
    ], $buildDir);
    ViteFacade::useIntegrityKey('different-integrity-key');

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js'], $buildDir);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" integrity="expected-app.css-integrity" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>');

    cleanViteManifest($buildDir);
});

test('arbitrary attributes can be added to script tags in build mode', function () {
    makeViteManifest();
    ViteFacade::useScriptTagAttributes([
        'general' => 'attribute',
    ]);
    ViteFacade::useScriptTagAttributes(function ($src, $url, $chunk, $manifest) {
        expect($src)->toBe('resources/js/app.js');
        expect($url)->toBe('https://example.com/build/assets/app.versioned.js');
        expect($chunk)->toBe([
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
        ]);
        expect($manifest)->toBe([
            'resources/js/app.js' => [
                'src' => 'resources/js/app.js',
                'file' => 'assets/app.versioned.js',
            ],
            'resources/js/app-with-css-import.js' => [
                'src' => 'resources/js/app-with-css-import.js',
                'file' => 'assets/app-with-css-import.versioned.js',
                'css' => [
                    'assets/imported-css.versioned.css',
                ],
            ],
            'resources/css/imported-css.css' => [
                'file' => 'assets/imported-css.versioned.css',
            ],
            'resources/js/app-with-shared-css.js' => [
                'src' => 'resources/js/app-with-shared-css.js',
                'file' => 'assets/app-with-shared-css.versioned.js',
                'imports' => [
                    '_someFile.js',
                ],
            ],
            'resources/css/app.css' => [
                'src' => 'resources/css/app.css',
                'file' => 'assets/app.versioned.css',
            ],
            '_someFile.js' => [
                'file' => 'assets/someFile.versioned.js',
                'css' => [
                    'assets/shared-css.versioned.css',
                ],
            ],
            'resources/css/shared-css' => [
                'src' => 'resources/css/shared-css',
                'file' => 'assets/shared-css.versioned.css',
            ],
        ]);

        return [
            'crossorigin',
            'data-persistent-across-pages' => 'YES',
            'remove-me' => false,
            'keep-me' => true,
            'null' => null,
            'empty-string' => '',
            'zero' => 0,
        ];
    });

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" />'
        .'<script type="module" src="https://example.com/build/assets/app.versioned.js" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me empty-string="" zero="0"></script>');
});

test('arbitrary attributes can be added to stylesheet tags in build mode', function () {
    makeViteManifest();
    ViteFacade::useStyleTagAttributes([
        'general' => 'attribute',
    ]);
    ViteFacade::useStyleTagAttributes(function ($src, $url, $chunk, $manifest) {
        expect($src)->toBe('resources/css/app.css');
        expect($url)->toBe('https://example.com/build/assets/app.versioned.css');
        expect($chunk)->toBe([
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
        ]);
        expect($manifest)->toBe([
            'resources/js/app.js' => [
                'src' => 'resources/js/app.js',
                'file' => 'assets/app.versioned.js',
            ],
            'resources/js/app-with-css-import.js' => [
                'src' => 'resources/js/app-with-css-import.js',
                'file' => 'assets/app-with-css-import.versioned.js',
                'css' => [
                    'assets/imported-css.versioned.css',
                ],
            ],
            'resources/css/imported-css.css' => [
                'file' => 'assets/imported-css.versioned.css',
            ],
            'resources/js/app-with-shared-css.js' => [
                'src' => 'resources/js/app-with-shared-css.js',
                'file' => 'assets/app-with-shared-css.versioned.js',
                'imports' => [
                    '_someFile.js',
                ],
            ],
            'resources/css/app.css' => [
                'src' => 'resources/css/app.css',
                'file' => 'assets/app.versioned.css',
            ],
            '_someFile.js' => [
                'file' => 'assets/someFile.versioned.js',
                'css' => [
                    'assets/shared-css.versioned.css',
                ],
            ],
            'resources/css/shared-css' => [
                'src' => 'resources/css/shared-css',
                'file' => 'assets/shared-css.versioned.css',
            ],
        ]);

        return [
            'crossorigin',
            'data-persistent-across-pages' => 'YES',
            'remove-me' => false,
            'keep-me' => true,
        ];
    });

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toEndWith('<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me />'
        .'<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>');
});

test('arbitrary attributes can be added to script tags in hot mode', function () {
    makeViteHotFile();
    ViteFacade::useScriptTagAttributes([
        'general' => 'attribute',
    ]);
    $expectedArguments = [
        ['src' => '@vite/client', 'url' => 'http://localhost:3000/@vite/client'],
        ['src' => 'resources/js/app.js', 'url' => 'http://localhost:3000/resources/js/app.js'],
    ];
    ViteFacade::useScriptTagAttributes(function ($src, $url, $chunk, $manifest) use (&$expectedArguments) {
        $args = array_shift($expectedArguments);

        expect($src)->toBe($args['src']);
        expect($url)->toBe($args['url']);
        expect($chunk)->toBeNull();
        expect($manifest)->toBeNull();

        return [
            'crossorigin',
            'data-persistent-across-pages' => 'YES',
            'remove-me' => false,
            'keep-me' => true,
        ];
    });

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me></script>'
        .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" />'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me></script>');
});

test('arbitrary attributes can be added to stylesheet tags in hot mode', function () {
    makeViteHotFile();
    ViteFacade::useStyleTagAttributes([
        'general' => 'attribute',
    ]);
    ViteFacade::useStyleTagAttributes(function ($src, $url, $chunk, $manifest) {
        expect($src)->toBe('resources/css/app.css');
        expect($url)->toBe('http://localhost:3000/resources/css/app.css');
        expect($chunk)->toBeNull();
        expect($manifest)->toBeNull();

        return [
            'crossorigin',
            'data-persistent-across-pages' => 'YES',
            'remove-me' => false,
            'keep-me' => true,
        ];
    });

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client"></script>'
        .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me />'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>');
});

test('every rendered attribute can be overridden', function () {
    makeViteManifest();
    ViteFacade::useStyleTagAttributes([
        'rel' => 'expected-rel',
        'href' => 'expected-href',
    ]);
    ViteFacade::useScriptTagAttributes([
        'type' => 'expected-type',
        'src' => 'expected-src',
    ]);

    $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

    expect($result->toHtml())->toEndWith('<link rel="expected-rel" href="expected-href" />'
        .'<script type="expected-type" src="expected-src"></script>');
});

test('an individual asset url is generated in build mode', function () {
    makeViteManifest();

    $url = ViteFacade::asset('resources/js/app.js');

    expect($url)->toBe('https://example.com/build/assets/app.versioned.js');
});

test('an individual asset url is generated in hot mode', function () {
    makeViteHotFile();

    $url = ViteFacade::asset('resources/js/app.js');

    expect($url)->toBe('http://localhost:3000/resources/js/app.js');
});

test('a missing manifest throws in build mode', function () {
    $this->expectException(ViteException::class);
    $this->expectExceptionMessage('Vite manifest not found at: '.public_path('build/manifest.json'));

    ViteFacade::asset('resources/js/app.js');
});

test('a missing manifest still throws the deprecated exception type', function () {
    $this->expectException(ViteManifestNotFoundException::class);
    $this->expectExceptionMessage('Vite manifest not found at: '.public_path('build/manifest.json'));

    ViteFacade::asset('resources/js/app.js');
});

test('an asset missing from the manifest throws', function () {
    makeViteManifest();

    $this->expectException(ViteException::class);
    $this->expectExceptionMessage('Unable to locate file in Vite manifest: resources/js/missing.js');

    ViteFacade::asset('resources/js/missing.js');
});

test('the manifest hash is null in hot mode', function () {
    makeViteHotFile();

    expect(ViteFacade::manifestHash())->toBeNull();

    cleanViteHotFile();
});

test('the manifest hash is returned in build mode', function () {
    makeViteManifest(['a.js' => ['src' => 'a.js']]);

    expect(ViteFacade::manifestHash())->toBe('98ca5a789544599b562c9978f3147a0f');

    cleanViteManifest();
});

test('each manifest has its own hash', function () {
    makeViteManifest(['a.js' => ['src' => 'a.js']]);
    makeViteManifest(['b.js' => ['src' => 'b.js']], 'admin');

    expect(ViteFacade::manifestHash())->toBe('98ca5a789544599b562c9978f3147a0f');
    expect(ViteFacade::manifestHash('admin'))->toBe('928a60835978bae84e5381fbb08a38b2');

    cleanViteManifest();
    cleanViteManifest('admin');
});

test('entry points can be set through the fluent builder', function () {
    makeViteManifest();

    $vite = app(Vite::class);

    expect($vite->toHtml())->toBe('');

    $vite->withEntryPoints(['resources/js/app.js']);

    expect($vite->toHtml())->toEndWith('<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>');
});

test('the build directory can be overridden', function () {
    makeViteManifest(null, 'custom-build');

    $vite = app(Vite::class);

    $vite->withEntryPoints(['resources/js/app.js'])->useBuildDirectory('custom-build');

    expect($vite->toHtml())->toEndWith('<script type="module" src="https://example.com/custom-build/assets/app.versioned.js"></script>');

    cleanViteManifest('custom-build');
});

test('the hot file path can be overridden', function () {
    makeViteHotFile('cold');

    $vite = app(Vite::class);

    $vite->withEntryPoints(['resources/js/app.js'])->useHotFile('cold');

    expect($vite->toHtml())->toBe('<script type="module" src="http://localhost:3000/@vite/client"></script>'
        .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>');

    cleanViteHotFile('cold');
});

test('asset paths can be generated by a custom callback', function () {
    makeViteManifest([
        'resources/images/profile.png' => [
            'src' => 'resources/images/profile.png',
            'file' => 'assets/profile.versioned.png',
        ],
    ], $buildDir = Str::random());
    $vite = app(Vite::class)->useBuildDirectory($buildDir);
    $this->app['config']->set('app.asset_url', 'https://cdn.app.com');

    // default behaviour...
    expect($vite->asset('resources/images/profile.png'))->toBe("https://cdn.app.com/{$buildDir}/assets/profile.versioned.png");

    // custom behaviour
    $vite->createAssetPathsUsing(function ($path) {
        return 'https://tenant-cdn.app.com/'.$path;
    });
    expect($vite->asset('resources/images/profile.png'))->toBe("https://tenant-cdn.app.com/{$buildDir}/assets/profile.versioned.png");

    // restore default behaviour...
    $vite->createAssetPathsUsing(null);
    expect($vite->asset('resources/images/profile.png'))->toBe("https://cdn.app.com/{$buildDir}/assets/profile.versioned.png");

    cleanViteManifest($buildDir);
});

test('vite is macroable', function () {
    makeViteManifest([
        'resources/images/profile.png' => [
            'src' => 'resources/images/profile.png',
            'file' => 'assets/profile.versioned.png',
        ],
    ], $buildDir = Str::random());
    Vite::macro('image', function ($asset, $buildDir = null) {
        return $this->asset("resources/images/{$asset}", $buildDir);
    });

    $path = ViteFacade::image('profile.png', $buildDir);

    expect($path)->toBe("https://example.com/{$buildDir}/assets/profile.versioned.png");

    cleanViteManifest($buildDir);
});

test('preload directives are generated for js and css imports', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/jetstream-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);

    $result = app(Vite::class)(['resources/js/Pages/Auth/Login.vue'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="preload" as="style" href="https://example.com/'.$buildDir.'/assets/app.9842b564.css" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/Login.8c52c4a3.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app.a26d8e4d.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/AuthenticationCard.47ef70cc.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/AuthenticationCardLogo.9999a373.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/Checkbox.33ba23f3.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/TextInput.e2f0248c.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/InputLabel.d245ec4e.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/PrimaryButton.931d2859.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/_plugin-vue_export-helper.cdc0426e.js" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.9842b564.css" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/Login.8c52c4a3.js"></script>');
    expect(ViteFacade::preloadedAssets())->toBe([
        'https://example.com/'.$buildDir.'/assets/app.9842b564.css' => [
            'rel="preload"',
            'as="style"',
        ],
        'https://example.com/'.$buildDir.'/assets/Login.8c52c4a3.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/app.a26d8e4d.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/AuthenticationCard.47ef70cc.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/AuthenticationCardLogo.9999a373.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/Checkbox.33ba23f3.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/TextInput.e2f0248c.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/InputLabel.d245ec4e.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/PrimaryButton.931d2859.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
        'https://example.com/'.$buildDir.'/assets/_plugin-vue_export-helper.cdc0426e.js' => [
            'rel="modulepreload"',
            'as="script"',
        ],
    ]);

    cleanViteManifest($buildDir);
});

test('arbitrary attributes can be added to preload tags', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'imports' => [
                'import.js',
            ],
            'css' => [
                'assets/app.versioned.css',
            ],
        ],
        'import.js' => [
            'file' => 'assets/import.versioned.js',
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
        ],
    ], $buildDir);
    ViteFacade::usePreloadTagAttributes([
        'general' => 'attribute',
    ]);
    ViteFacade::usePreloadTagAttributes(function ($src, $url, $chunk, $manifest) use ($buildDir) {
        expect($manifest)->toBe([
            'resources/js/app.js' => [
                'src' => 'resources/js/app.js',
                'file' => 'assets/app.versioned.js',
                'imports' => [
                    'import.js',
                ],
                'css' => [
                    'assets/app.versioned.css',
                ],
            ],
            'import.js' => [
                'file' => 'assets/import.versioned.js',
            ],
            'resources/css/app.css' => [
                'src' => 'resources/css/app.css',
                'file' => 'assets/app.versioned.css',
            ],
        ]);

        (match ($src) {
            'resources/js/app.js' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/app.versioned.js");
                expect($chunk)->toBe([
                    'src' => 'resources/js/app.js',
                    'file' => 'assets/app.versioned.js',
                    'imports' => [
                        'import.js',
                    ],
                    'css' => [
                        'assets/app.versioned.css',
                    ],
                ]);
            },
            'import.js' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/import.versioned.js");
                expect($chunk)->toBe([
                    'file' => 'assets/import.versioned.js',
                ]);
            },
            'resources/css/app.css' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/app.versioned.css");
                expect($chunk)->toBe([
                    'src' => 'resources/css/app.css',
                    'file' => 'assets/app.versioned.css',
                ]);
            },
        })();

        return [
            'crossorigin',
            'data-persistent-across-pages' => 'YES',
            'remove-me' => false,
            'keep-me' => true,
            'null' => null,
            'empty-string' => '',
            'zero' => 0,
        ];
    });

    $result = app(Vite::class)(['resources/js/app.js'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="preload" as="style" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me empty-string="" zero="0" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app.versioned.js" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me empty-string="" zero="0" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/import.versioned.js" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me empty-string="" zero="0" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js"></script>');

    expect(ViteFacade::preloadedAssets())->toBe([
        "https://example.com/$buildDir/assets/app.versioned.css" => [
            'rel="preload"',
            'as="style"',
            'general="attribute"',
            'crossorigin',
            'data-persistent-across-pages="YES"',
            'keep-me',
            'empty-string=""',
            'zero="0"',
        ],
        "https://example.com/$buildDir/assets/app.versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
            'general="attribute"',
            'crossorigin',
            'data-persistent-across-pages="YES"',
            'keep-me',
            'empty-string=""',
            'zero="0"',
        ],
        "https://example.com/$buildDir/assets/import.versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
            'general="attribute"',
            'crossorigin',
            'data-persistent-across-pages="YES"',
            'keep-me',
            'empty-string=""',
            'zero="0"',
        ],
    ]);

    cleanViteManifest($buildDir);
});

test('preload tag generation can be suppressed per asset', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'imports' => [
                'import.js',
                'import-nopreload.js',
            ],
            'css' => [
                'assets/app.versioned.css',
                'assets/app-nopreload.versioned.css',
            ],
        ],
        'resources/js/app-nopreload.js' => [
            'src' => 'resources/js/app-nopreload.js',
            'file' => 'assets/app-nopreload.versioned.js',
        ],
        'import.js' => [
            'file' => 'assets/import.versioned.js',
        ],
        'import-nopreload.js' => [
            'file' => 'assets/import-nopreload.versioned.js',
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
        ],
        'resources/css/app-nopreload.css' => [
            'src' => 'resources/css/app-nopreload.css',
            'file' => 'assets/app-nopreload.versioned.css',
        ],
    ], $buildDir);
    ViteFacade::usePreloadTagAttributes(function ($src, $url, $chunk, $manifest) use ($buildDir) {
        expect($manifest)->toBe([
            'resources/js/app.js' => [
                'src' => 'resources/js/app.js',
                'file' => 'assets/app.versioned.js',
                'imports' => [
                    'import.js',
                    'import-nopreload.js',
                ],
                'css' => [
                    'assets/app.versioned.css',
                    'assets/app-nopreload.versioned.css',
                ],
            ],
            'resources/js/app-nopreload.js' => [
                'src' => 'resources/js/app-nopreload.js',
                'file' => 'assets/app-nopreload.versioned.js',
            ],
            'import.js' => [
                'file' => 'assets/import.versioned.js',
            ],
            'import-nopreload.js' => [
                'file' => 'assets/import-nopreload.versioned.js',
            ],
            'resources/css/app.css' => [
                'src' => 'resources/css/app.css',
                'file' => 'assets/app.versioned.css',
            ],
            'resources/css/app-nopreload.css' => [
                'src' => 'resources/css/app-nopreload.css',
                'file' => 'assets/app-nopreload.versioned.css',
            ],
        ]);

        (match ($src) {
            'resources/js/app.js' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/app.versioned.js");
                expect($chunk)->toBe([
                    'src' => 'resources/js/app.js',
                    'file' => 'assets/app.versioned.js',
                    'imports' => [
                        'import.js',
                        'import-nopreload.js',
                    ],
                    'css' => [
                        'assets/app.versioned.css',
                        'assets/app-nopreload.versioned.css',
                    ],
                ]);
            },
            'resources/js/app-nopreload.js' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/app-nopreload.versioned.js");
                expect($chunk)->toBe([
                    'src' => 'resources/js/app-nopreload.js',
                    'file' => 'assets/app-nopreload.versioned.js',
                ]);
            },
            'import.js' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/import.versioned.js");
                expect($chunk)->toBe([
                    'file' => 'assets/import.versioned.js',
                ]);
            },
            'import-nopreload.js' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/import-nopreload.versioned.js");
                expect($chunk)->toBe([
                    'file' => 'assets/import-nopreload.versioned.js',
                ]);
            },
            'resources/css/app.css' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/app.versioned.css");
                expect($chunk)->toBe([
                    'src' => 'resources/css/app.css',
                    'file' => 'assets/app.versioned.css',
                ]);
            },
            'resources/css/app-nopreload.css' => function () use ($url, $chunk, $buildDir) {
                expect($url)->toBe("https://example.com/{$buildDir}/assets/app-nopreload.versioned.css");
                expect($chunk)->toBe([
                    'src' => 'resources/css/app-nopreload.css',
                    'file' => 'assets/app-nopreload.versioned.css',
                ]);
            },
        })();

        return Str::contains($src, '-nopreload') ? false : [];
    });

    $result = app(Vite::class)(['resources/js/app.js', 'resources/js/app-nopreload.js'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="preload" as="style" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app.versioned.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/import.versioned.js" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app-nopreload.versioned.css" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js"></script>'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app-nopreload.versioned.js"></script>');

    expect(ViteFacade::preloadedAssets())->toBe([
        "https://example.com/$buildDir/assets/app.versioned.css" => [
            'rel="preload"',
            'as="style"',
        ],
        "https://example.com/$buildDir/assets/app.versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
        ],
        "https://example.com/$buildDir/assets/import.versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
        ],
    ]);

    cleanViteManifest($buildDir);
});

test('preload tags inherit the csp nonce', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'css' => [
                'assets/app.versioned.css',
            ],
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
        ],
    ], $buildDir);
    ViteFacade::useCspNonce('expected-nonce');

    $result = app(Vite::class)(['resources/js/app.js'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="preload" as="style" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" nonce="expected-nonce" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app.versioned.js" nonce="expected-nonce" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" nonce="expected-nonce" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" nonce="expected-nonce"></script>');

    expect(ViteFacade::preloadedAssets())->toBe([
        "https://example.com/$buildDir/assets/app.versioned.css" => [
            'rel="preload"',
            'as="style"',
            'nonce="expected-nonce"',
        ],
        "https://example.com/$buildDir/assets/app.versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
            'nonce="expected-nonce"',
        ],
    ]);

    cleanViteManifest($buildDir);
});

test('preload tags inherit the crossorigin attribute', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.js' => [
            'src' => 'resources/js/app.js',
            'file' => 'assets/app.versioned.js',
            'css' => [
                'assets/app.versioned.css',
            ],
        ],
        'resources/css/app.css' => [
            'src' => 'resources/css/app.css',
            'file' => 'assets/app.versioned.css',
        ],
    ], $buildDir);
    ViteFacade::useScriptTagAttributes([
        'crossorigin' => 'script-crossorigin',
    ]);
    ViteFacade::useStyleTagAttributes([
        'crossorigin' => 'style-crossorigin',
    ]);

    $result = app(Vite::class)(['resources/js/app.js'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="preload" as="style" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" crossorigin="style-crossorigin" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app.versioned.js" crossorigin="script-crossorigin" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" crossorigin="style-crossorigin" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" crossorigin="script-crossorigin"></script>');

    expect(ViteFacade::preloadedAssets())->toBe([
        "https://example.com/$buildDir/assets/app.versioned.css" => [
            'rel="preload"',
            'as="style"',
            'crossorigin="style-crossorigin"',
        ],
        "https://example.com/$buildDir/assets/app.versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
            'crossorigin="script-crossorigin"',
        ],
    ]);

    cleanViteManifest($buildDir);
});

test('the manifest filename can be configured', function () {
    $buildDir = Str::random();
    app()->usePublicPath(__DIR__);
    if (! file_exists(public_path($buildDir))) {
        mkdir(public_path($buildDir));
    }
    $contents = json_encode([
        'resources/js/app.js' => [
            'src' => 'resources/js/app-from-custom-manifest.js',
            'file' => 'assets/app-from-custom-manifest.versioned.js',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents(public_path("{$buildDir}/custom-manifest.json"), $contents);

    ViteFacade::useManifestFilename('custom-manifest.json');

    $result = app(Vite::class)(['resources/js/app.js'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app-from-custom-manifest.versioned.js" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app-from-custom-manifest.versioned.js"></script>');

    unlink(public_path("{$buildDir}/custom-manifest.json"));
    rmdir(public_path($buildDir));
});

test('a preload tag is only output once per asset', function () {
    $buildDir = Str::random();
    makeViteManifest([
        'resources/js/app.css' => [
            'file' => 'assets/app-versioned.css',
            'src' => 'resources/js/app.css',
        ],
        'resources/js/Pages/Welcome.vue' => [
            'file' => 'assets/Welcome-versioned.js',
            'src' => 'resources/js/Pages/Welcome.vue',
            'imports' => [
                'resources/js/app.js',
            ],
        ],
        'resources/js/app.js' => [
            'file' => 'assets/app-versioned.js',
            'src' => 'resources/js/app.js',
            'css' => [
                'assets/app-versioned.css',
            ],
        ],
    ], $buildDir);

    $result = app(Vite::class)(['resources/js/app.js', 'resources/js/Pages/Welcome.vue'], $buildDir);

    expect($result->toHtml())->toBe('<link rel="preload" as="style" href="https://example.com/'.$buildDir.'/assets/app-versioned.css" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/app-versioned.js" />'
        .'<link rel="modulepreload" as="script" href="https://example.com/'.$buildDir.'/assets/Welcome-versioned.js" />'
        .'<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app-versioned.css" />'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app-versioned.js"></script>'
        .'<script type="module" src="https://example.com/'.$buildDir.'/assets/Welcome-versioned.js"></script>');

    expect(ViteFacade::preloadedAssets())->toBe([
        "https://example.com/$buildDir/assets/app-versioned.css" => [
            'rel="preload"',
            'as="style"',
        ],
        "https://example.com/$buildDir/assets/app-versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
        ],
        "https://example.com/$buildDir/assets/Welcome-versioned.js" => [
            'rel="modulepreload"',
            'as="script"',
        ],
    ]);

    cleanViteManifest($buildDir);
});

test('the content of a built asset can be read', function () {
    makeViteManifest();

    makeViteAsset('/app.versioned.js', 'some content');

    $content = ViteFacade::content('resources/js/app.js');

    expect($content)->toBe('some content');

    cleanViteAsset('/app.versioned.js');

    cleanViteManifest();
});

test('reading a missing built asset throws', function () {
    makeViteManifest();

    $this->expectException(ViteException::class);
    $this->expectExceptionMessage('Unable to locate file from Vite manifest: '.public_path('build/assets/app.versioned.js'));

    ViteFacade::content('resources/js/app.js');
});

test('an entry point can be prefetched with a concurrency limit', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) ViteFacade::withEntryPoints(['resources/js/app.js'])->useBuildDirectory($buildDir)->prefetch(concurrency: 3)->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/GuestLayout-BY3LC-73.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/TextInput-C8CCB_U_.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/PrimaryButton-DuXwr-9M.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ApplicationLogo-BhIZH06z.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/_plugin-vue_export-helper-DlAUqK2U.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Login-DAFSdGSW.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'fetchpriority' => 'low'],
    ]);
    expect($html)->toBe(<<<HTML
    <link rel="preload" as="style" href="https://example.com/{$buildDir}/assets/index-B3s1tYeC.css" /><link rel="modulepreload" as="script" href="https://example.com/{$buildDir}/assets/app-lliD09ip.js" /><link rel="modulepreload" as="script" href="https://example.com/{$buildDir}/assets/index-BSdK3M0e.js" /><link rel="stylesheet" href="https://example.com/{$buildDir}/assets/index-B3s1tYeC.css" /><script type="module" src="https://example.com/{$buildDir}/assets/app-lliD09ip.js"></script>
    <script>
         window.addEventListener('load', () => window.setTimeout(() => {
            const makeLink = (asset) => {
                const link = document.createElement('link')

                Object.keys(asset).forEach((attribute) => {
                    link.setAttribute(attribute, asset[attribute])
                })

                return link
            }

            const loadNext = (assets, count) => window.setTimeout(() => {
                if (count > assets.length) {
                    count = assets.length

                    if (count === 0) {
                        return
                    }
                }

                const fragment = new DocumentFragment

                while (count > 0) {
                    const link = makeLink(assets.shift())
                    fragment.append(link)
                    count--

                    if (assets.length) {
                        link.onload = () => loadNext(assets, 1)
                        link.onerror = () => loadNext(assets, 1)
                    }
                }

                document.head.append(fragment)
            })

            loadNext({$expectedAssets}, 3)
        }))
    </script>
    HTML);

    cleanViteManifest($buildDir);
});

test('naming a page alongside app.js narrows what is prefetched', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) ViteFacade::withEntryPoints(['resources/js/app.js', 'resources/js/Pages/Auth/Login.vue'])->useBuildDirectory($buildDir)->prefetch(concurrency: 3)->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'fetchpriority' => 'low'],
    ]);
    expect($html)->toContain(<<<JAVASCRIPT
            loadNext({$expectedAssets}, 3)
        JAVASCRIPT);

    cleanViteManifest($buildDir);
});

test('the prefetch concurrency can be raised', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) ViteFacade::withEntryPoints(['resources/js/app.js'])->useBuildDirectory($buildDir)->prefetch(concurrency: 10)->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/GuestLayout-BY3LC-73.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/TextInput-C8CCB_U_.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/PrimaryButton-DuXwr-9M.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ApplicationLogo-BhIZH06z.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/_plugin-vue_export-helper-DlAUqK2U.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Login-DAFSdGSW.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'fetchpriority' => 'low'],
    ]);
    expect($html)->toContain(<<<JAVASCRIPT
            loadNext({$expectedAssets}, 10)
        JAVASCRIPT);

    cleanViteManifest($buildDir);
});

test('prefetching can be aggressive rather than waterfalled', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) ViteFacade::withEntryPoints(['resources/js/app.js'])->useBuildDirectory($buildDir)->prefetch()->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/GuestLayout-BY3LC-73.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/TextInput-C8CCB_U_.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/PrimaryButton-DuXwr-9M.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ApplicationLogo-BhIZH06z.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/_plugin-vue_export-helper-DlAUqK2U.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Login-DAFSdGSW.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'fetchpriority' => 'low'],
    ]);

    expect($html)->toBe(<<<HTML
    <link rel="preload" as="style" href="https://example.com/{$buildDir}/assets/index-B3s1tYeC.css" /><link rel="modulepreload" as="script" href="https://example.com/{$buildDir}/assets/app-lliD09ip.js" /><link rel="modulepreload" as="script" href="https://example.com/{$buildDir}/assets/index-BSdK3M0e.js" /><link rel="stylesheet" href="https://example.com/{$buildDir}/assets/index-B3s1tYeC.css" /><script type="module" src="https://example.com/{$buildDir}/assets/app-lliD09ip.js"></script>
    <script>
         window.addEventListener('load', () => window.setTimeout(() => {
            const makeLink = (asset) => {
                const link = document.createElement('link')

                Object.keys(asset).forEach((attribute) => {
                    link.setAttribute(attribute, asset[attribute])
                })

                return link
            }

            const fragment = new DocumentFragment;
            {$expectedAssets}.forEach((asset) => fragment.append(makeLink(asset)))
            document.head.append(fragment)
         }))
    </script>
    HTML);

    cleanViteManifest($buildDir);
});

test('attributes are added to prefetch tags', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) tap(ViteFacade::withEntryPoints(['resources/js/app.js'])->useBuildDirectory($buildDir)->prefetch(concurrency: 3))->useCspNonce('abc123')->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/GuestLayout-BY3LC-73.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/TextInput-C8CCB_U_.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/PrimaryButton-DuXwr-9M.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ApplicationLogo-BhIZH06z.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/_plugin-vue_export-helper-DlAUqK2U.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Login-DAFSdGSW.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'nonce' => 'abc123', 'fetchpriority' => 'low'],
    ]);
    expect($html)->toContain(<<<JAVASCRIPT
            loadNext({$expectedAssets}, 3)
    JAVASCRIPT);

    cleanViteManifest($buildDir);
});

test('prefetch tag attributes are normalised', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) tap(ViteFacade::withEntryPoints(['resources/js/app.js']))->useBuildDirectory($buildDir)->prefetch(concurrency: 3)->usePreloadTagAttributes([
        'key' => 'value',
        'key-only',
        'true-value' => true,
        'false-value' => false,
        'null-value' => null,
    ])->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/GuestLayout-BY3LC-73.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/TextInput-C8CCB_U_.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/PrimaryButton-DuXwr-9M.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ApplicationLogo-BhIZH06z.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/_plugin-vue_export-helper-DlAUqK2U.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Login-DAFSdGSW.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'key' => 'value', 'key-only' => 'key-only', 'true-value' => 'true-value', 'fetchpriority' => 'low'],
    ]);

    expect($html)->toContain(<<<JAVASCRIPT
            loadNext({$expectedAssets}, 3)
    JAVASCRIPT);

    cleanViteManifest($buildDir);
});

test('css is prefetched alongside js', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) ViteFacade::withEntryPoints(['resources/js/admin.js'])->useBuildDirectory($buildDir)->prefetch(concurrency: 3)->toHtml();

    $expectedAssets = Js::from([
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ConfirmPassword-CDwcgU8E.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/GuestLayout-BY3LC-73.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/TextInput-C8CCB_U_.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/PrimaryButton-DuXwr-9M.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ApplicationLogo-BhIZH06z.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/_plugin-vue_export-helper-DlAUqK2U.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ForgotPassword-B0WWE0BO.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Login-DAFSdGSW.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Register-CfYQbTlA.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/ResetPassword-BNl7a4X1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/VerifyEmail-CyukB_SZ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Dashboard-DM_LxQy2.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/AuthenticatedLayout-DfWF52N1.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Edit-CYV2sXpe.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/DeleteUserForm-B1oHFaVP.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdatePasswordForm-CaeWqGla.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/UpdateProfileInformationForm-CJwkYwQQ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/Welcome-D_7l79PQ.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/admin-runtime-import-CRvLQy6v.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'script', 'href' => "https://example.com/{$buildDir}/assets/admin-runtime-import-import-DKMIaPXC.js", 'fetchpriority' => 'low'],
        ['rel' => 'prefetch', 'as' => 'style', 'href' => "https://example.com/{$buildDir}/assets/admin-runtime-import-BlmN0T4U.css", 'fetchpriority' => 'low'],
    ]);
    expect($html)->toBe(<<<HTML
    <link rel="preload" as="style" href="https://example.com/{$buildDir}/assets/index-B3s1tYeC.css" /><link rel="preload" as="style" href="https://example.com/{$buildDir}/assets/admin-BctAalm_.css" /><link rel="modulepreload" as="script" href="https://example.com/{$buildDir}/assets/admin-Sefg0Q45.js" /><link rel="modulepreload" as="script" href="https://example.com/{$buildDir}/assets/index-BSdK3M0e.js" /><link rel="stylesheet" href="https://example.com/{$buildDir}/assets/index-B3s1tYeC.css" /><link rel="stylesheet" href="https://example.com/{$buildDir}/assets/admin-BctAalm_.css" /><script type="module" src="https://example.com/{$buildDir}/assets/admin-Sefg0Q45.js"></script>
    <script>
         window.addEventListener('load', () => window.setTimeout(() => {
            const makeLink = (asset) => {
                const link = document.createElement('link')

                Object.keys(asset).forEach((attribute) => {
                    link.setAttribute(attribute, asset[attribute])
                })

                return link
            }

            const loadNext = (assets, count) => window.setTimeout(() => {
                if (count > assets.length) {
                    count = assets.length

                    if (count === 0) {
                        return
                    }
                }

                const fragment = new DocumentFragment

                while (count > 0) {
                    const link = makeLink(assets.shift())
                    fragment.append(link)
                    count--

                    if (assets.length) {
                        link.onload = () => loadNext(assets, 1)
                        link.onerror = () => loadNext(assets, 1)
                    }
                }

                document.head.append(fragment)
            })

            loadNext({$expectedAssets}, 3)
        }))
    </script>
    HTML);

    cleanViteManifest($buildDir);
});

test('the prefetch script itself carries the csp nonce', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) tap(ViteFacade::withEntryPoints(['resources/js/app.js']))
        ->useCspNonce('abc123')
        ->useBuildDirectory($buildDir)
        ->prefetch()
        ->toHtml();
    expect($html)->toContain('<script nonce="abc123">');

    $html = (string) tap(ViteFacade::withEntryPoints(['resources/js/app.js']))
        ->useCspNonce('abc123')
        ->useBuildDirectory($buildDir)
        ->prefetch(concurrency: 3)
        ->toHtml();
    expect($html)->toContain('<script nonce="abc123">');

    cleanViteManifest($buildDir);
});

test('the prefetch trigger event can be configured', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/prefetching-manifest.json'));
    $buildDir = Str::random();
    makeViteManifest($manifest, $buildDir);
    app()->usePublicPath(__DIR__);

    $html = (string) tap(ViteFacade::withEntryPoints(['resources/js/app.js']))
        ->useBuildDirectory($buildDir)
        ->prefetch(event: 'vite:prefetch')
        ->toHtml();
    expect($html)->not->toContain("window.addEventListener('load', ");
    expect($html)->toContain("window.addEventListener('vite:prefetch', ");

    cleanViteManifest($buildDir);
});

test('flush clears the preloaded assets', function () {
    makeViteManifest();

    app(Vite::class)('resources/js/app.js');
    app()->forgetScopedInstances();
    expect(app(Vite::class)->preloadedAssets())->toHaveCount(1);

    app(Vite::class)->flush();
    expect(app(Vite::class)->preloadedAssets())->toHaveCount(0);
});
