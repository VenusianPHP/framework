<?php

use Tests\Vessel\Fixtures\ParentClass;
use Tests\Vessel\Fixtures\VariadicParentClass;
use Tests\Vessel\Fixtures\VariadicPrimitive;
use Voyager\Vessel\Vessel;

test('resolving a non-instantiable dependency with a default drops the contextual withs', function () {
    $object = (new Vessel)->make(ParentClass::class, ['i' => 42]);

    expect($object->i)->toBe(42);
});

test('resolving a non-instantiable variadic dependency drops the contextual withs', function () {
    $parent = (new Vessel)->make(VariadicParentClass::class, ['i' => 42]);

    expect($parent->child->objects)->toHaveCount(0)
        ->and($parent->i)->toBe(42);
});

test('a variadic primitive resolves to an empty array', function () {
    $parent = (new Vessel)->make(VariadicPrimitive::class);

    expect($parent->params)->toBe([]);
});
