<?php

use Voyager\Config\Repository;
use Voyager\NutsAndBolts\Concerns\CapsuleManagerTrait;
use Voyager\NutsAndBolts\Fluent;
use Voyager\Vessel\Vessel;

uses(CapsuleManagerTrait::class);

test('setupContainer binds a Fluent config when none is bound', function () {
    $this->container = null;
    $app = new Vessel;

    $this->setupContainer($app);

    expect($this->getContainer())->toEqual($app)
        ->and($app['config'])->toBeInstanceOf(Fluent::class);
});

test('setupContainer leaves an already bound config alone', function () {
    $this->container = null;
    $app = new Vessel;
    $app['config'] = Mockery::mock(Repository::class);

    $this->setupContainer($app);

    expect($this->getContainer())->toEqual($app)
        ->and($app['config'])->toBeInstanceOf(Repository::class);
});
