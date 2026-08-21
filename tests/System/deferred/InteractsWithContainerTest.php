<?php

use Orchestra\Testbench\TestCase;
use Tests\System\Stubs\InstanceStub;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\System\Mix;
use Voyager\System\Vite;

uses(TestCase::class);

describe('vite', function () {
    test('withoutVite binds an empty handler and returns the test case', function () {
        $instance = $this->withoutVite();

        expect(app(Vite::class)(['resources/js/app.js'])->toHtml())->toBe('')
            ->and($instance)->toBe($this);
    });

    test('withoutVite handles react refresh', function () {
        $instance = $this->withoutVite();

        expect(app(Vite::class)->reactRefresh())->toBe('')
            ->and($instance)->toBe($this);
    });

    test('withoutVite handles asset', function () {
        $instance = $this->withoutVite();

        expect(app(Vite::class)->asset('path/to/asset.png'))->toBe('')
            ->and($instance)->toBe($this);
    });

    test('withoutVite returns an empty array of preloaded assets', function () {
        $instance = $this->withoutVite();

        expect(app(Vite::class)->preloadedAssets())->toBe([])
            ->and($instance)->toBe($this);
    });

    test('withVite restores the original handler and returns the test case', function () {
        $handler = new stdClass;
        $this->app->instance(Vite::class, $handler);

        $this->withoutVite();
        $instance = $this->withVite();

        expect(resolve(Vite::class))->toBe($handler)
            ->and($instance)->toBe($this);
    });
});

describe('mix', function () {
    test('withoutMix binds an empty handler and returns the test case', function () {
        $instance = $this->withoutMix();

        expect((string) mix('path/to/asset.png'))->toBe('')
            ->and($instance)->toBe($this);
    });

    test('withMix restores the original handler and returns the test case', function () {
        $handler = new stdClass;
        $this->app->instance(Mix::class, $handler);

        $this->withoutMix();
        $instance = $this->withMix();

        expect(resolve(Mix::class))->toBe($handler)
            ->and($instance)->toBe($this);
    });
});

test('withoutDefer runs deferred callbacks immediately until withDefer', function () {
    $called = [];
    defer(function () use (&$called) {
        $called[] = 1;
    });
    expect($called)->toBe([]);

    $instance = $this->withoutDefer();
    defer(function () use (&$called) {
        $called[] = 2;
    });
    expect($called)->toBe([2])
        ->and($instance)->toBe($this);

    $this->withDefer();
    expect($called)->toBe([2]);

    $this->app[DeferredCallbackCollection::class]->invoke();
    expect($called)->toBe([2, 1]);
});

test('forgetMock puts the real instance back', function () {
    $this->mock(InstanceStub::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn('bar');

    expect($this->app->make(InstanceStub::class)->execute())->toBe('bar');

    $this->forgetMock(InstanceStub::class);

    expect($this->app->make(InstanceStub::class)->execute())->toBe('foo');
});
