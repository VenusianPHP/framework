<?php

use Orchestra\Testbench\TestCase;
use Tests\System\Stubs\PublicPropertyComponent;
use Tests\System\Stubs\RenderingComponent;
use Voyager\System\Testing\Concerns\InteractsWithViews;
use Voyager\Testing\TestComponent;

uses(TestCase::class, InteractsWithViews::class);

test('blade renders a string correctly', function () {
    $string = (string) $this->blade('@if(true)test @endif');

    expect($string)->toBe('test ');
});

test('a rendered component exposes its public properties and methods', function () {
    $component = $this->component(PublicPropertyComponent::class);

    expect($component->foo)->toBe('bar')
        ->and($component->speak())->toBe('hello');

    $component->assertSee('content');
});

test('the test component is macroable', function () {
    TestComponent::macro('foo', fn (): string => 'bar');

    $component = $this->component(RenderingComponent::class);

    expect($component->foo())->toBe('bar');
});
