<?php
declare(strict_types=1);

use Venusian\Tests\Sketches\Fixtures\Sketches\NamedSketch;
use Venusian\Tests\Sketches\Fixtures\Sketches\PingSketch;
use Voyager\Sketches\SketchRegistry;
use Voyager\Vessel\ControlPanel;

test('kebab name from class basename', function () {
    $r = new SketchRegistry(new ControlPanel);
    $r->register(PingSketch::class);
    expect($r->all())->toBe(['ping-sketch' => PingSketch::class])
        ->and($r->has('ping-sketch'))->toBeTrue();
});

test('attribute name wins', function () {
    $r = new SketchRegistry(new ControlPanel);
    $r->register(NamedSketch::class);
    expect($r->all())->toHaveKey('custom-name');
});

test('duplicate name throws', function () {
    $r = new SketchRegistry(new ControlPanel);
    $r->register(PingSketch::class);
    expect(fn () => $r->register(PingSketch::class))->toThrow(InvalidArgumentException::class);
});

test('non-sketch class throws', function () {
    expect(fn () => (new SketchRegistry(new ControlPanel))->register(\Venusian\Tests\Sketches\Fixtures\Sketches\NotASketch::class))
        ->toThrow(InvalidArgumentException::class);
});

test('resolve builds through the container', function () {
    $r = new SketchRegistry(new ControlPanel);
    $r->register(PingSketch::class);
    expect($r->resolve('ping-sketch'))->toBeInstanceOf(PingSketch::class);
    expect(fn () => $r->resolve('nope'))->toThrow(InvalidArgumentException::class);
});
