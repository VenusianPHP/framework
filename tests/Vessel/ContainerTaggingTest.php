<?php

use Tests\Vessel\Fixtures\ContainerImplementationTaggedStub;
use Tests\Vessel\Fixtures\ContainerImplementationTaggedStubTwo;
use Voyager\Vessel\Vessel;

test('tags may be passed variadically or as an array', function () {
    $vessel = new Vessel;
    $vessel->tag(ContainerImplementationTaggedStub::class, 'foo', 'bar');
    $vessel->tag(ContainerImplementationTaggedStubTwo::class, ['foo']);

    expect($vessel->tagged('bar'))->toHaveCount(1)
        ->and($vessel->tagged('foo'))->toHaveCount(2);

    $fooResults = iterator_to_array($vessel->tagged('foo'), false);
    $barResults = iterator_to_array($vessel->tagged('bar'), false);

    expect($fooResults[0])->toBeInstanceOf(ContainerImplementationTaggedStub::class)
        ->and($barResults[0])->toBeInstanceOf(ContainerImplementationTaggedStub::class)
        ->and($fooResults[1])->toBeInstanceOf(ContainerImplementationTaggedStubTwo::class);
});

test('an array of abstracts may be tagged at once', function () {
    $vessel = new Vessel;
    $vessel->tag([ContainerImplementationTaggedStub::class, ContainerImplementationTaggedStubTwo::class], ['foo']);

    expect($vessel->tagged('foo'))->toHaveCount(2);

    $fooResults = iterator_to_array($vessel->tagged('foo'), false);

    expect($fooResults[0])->toBeInstanceOf(ContainerImplementationTaggedStub::class)
        ->and($fooResults[1])->toBeInstanceOf(ContainerImplementationTaggedStubTwo::class);
});

test('an unknown tag resolves to nothing', function () {
    expect((new Vessel)->tagged('this_tag_does_not_exist'))->toHaveCount(0);
});

test('tagged services are lazily loaded', function () {
    $vessel = Mockery::mock(Vessel::class)->makePartial();
    $vessel->shouldReceive('make')->once()->andReturn(new ContainerImplementationTaggedStub);

    $vessel->tag(ContainerImplementationTaggedStub::class, ['foo']);
    $vessel->tag(ContainerImplementationTaggedStubTwo::class, ['foo']);

    $fooResults = [];
    foreach ($vessel->tagged('foo') as $foo) {
        $fooResults[] = $foo;
        break;
    }

    expect($vessel->tagged('foo'))->toHaveCount(2)
        ->and($fooResults[0])->toBeInstanceOf(ContainerImplementationTaggedStub::class);
});

test('lazily loaded tagged services can be looped over more than once', function () {
    $vessel = new Vessel;
    $vessel->tag(ContainerImplementationTaggedStub::class, 'foo');
    $vessel->tag(ContainerImplementationTaggedStubTwo::class, ['foo']);

    $services = $vessel->tagged('foo');

    foreach ([1, 2] as $pass) {
        $fooResults = iterator_to_array($services, false);

        expect($fooResults[0])->toBeInstanceOf(ContainerImplementationTaggedStub::class)
            ->and($fooResults[1])->toBeInstanceOf(ContainerImplementationTaggedStubTwo::class);
    }
});
