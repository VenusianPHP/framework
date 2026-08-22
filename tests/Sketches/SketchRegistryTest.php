<?php

use Voyager\Contracts\Sketches\SketchException;
use Voyager\Sketches\SketchRegistry;
use Voyager\Vessel\Vessel;
use Tests\Sketches\Fixtures\AttributedFixtureSketch;
use Tests\Sketches\Fixtures\ReplaceFixtureSketch;

test('registry registers attributed sketches and resolves them', function () {
    $registry = new SketchRegistry(new Vessel);

    $registry->register(AttributedFixtureSketch::class);

    expect($registry->has('attributed-fixture'))->toBeTrue()
        ->and($registry->resolve('attributed-fixture'))->toBeInstanceOf(AttributedFixtureSketch::class);
});

test('registry rejects duplicate names', function () {
    $registry = new SketchRegistry(new Vessel);

    $registry->registerConvention('hello', AttributedFixtureSketch::class);

    expect(fn () => $registry->registerConvention('hello', AttributedFixtureSketch::class))
        ->toThrow(SketchException::class);
});

test('registry replace overwrites an existing sketch name', function () {
    $registry = new SketchRegistry(new Vessel);

    $registry->registerConvention('replace-fixture', AttributedFixtureSketch::class);
    $registry->replace(ReplaceFixtureSketch::class);

    expect($registry->all()['replace-fixture'])->toBe(ReplaceFixtureSketch::class)
        ->and($registry->resolve('replace-fixture'))->toBeInstanceOf(ReplaceFixtureSketch::class);
});

test('registry replaceAs binds under an explicit name', function () {
    $registry = new SketchRegistry(new Vessel);

    $registry->registerConvention('canvas-window-demo', AttributedFixtureSketch::class);
    $registry->replaceAs('canvas-window-demo', ReplaceFixtureSketch::class);

    expect($registry->all()['canvas-window-demo'])->toBe(ReplaceFixtureSketch::class);
});
