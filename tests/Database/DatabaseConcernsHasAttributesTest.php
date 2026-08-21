<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Casts\Attribute;
use Voyager\Database\Instrument\Concerns\HasAttributes;
use Voyager\Database\Instrument\Model;
use Voyager\NutsAndBolts\Collection;
use Mockery as m;

test('without constructor', function () {
    $instance = new HasAttributesWithoutConstructor();
    $attributes = $instance->getMutatedAttributes();
    expect($attributes)->toEqual(['some_attribute']);
});

test('with constructor arguments', function () {
    $instance = new HasAttributesWithConstructorArguments(null);
    $attributes = $instance->getMutatedAttributes();
    expect($attributes)->toEqual(['some_attribute']);
});

test('relations to array', function () {
    $mock = m::mock(HasAttributesWithoutConstructor::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getArrayableRelations')->andReturn([
            'arrayable_relation' => Collection::make(['foo' => 'bar']),
            'invalid_relation' => 'invalid',
            'null_relation' => null,
        ])
        ->getMock();

    expect($mock->relationsToArray())->toEqual([
        'arrayable_relation' => ['foo' => 'bar'],
        'null_relation' => null,
    ]);
});

test('casting empty string to array does not error', function () {
    $instance = new HasAttributesWithArrayCast();
    expect($instance->attributesToArray())->toEqual(['foo' => null])
        ->and(json_last_error() === JSON_ERROR_NONE)->toBeTrue();
});

test('unsetting cached attribute', function () {
    $instance = new HasCacheableAttributeWithAccessor();
    expect($instance->getAttribute('cacheableProperty'))->toEqual('foo')
        ->and($instance->cachedAttributeIsset('cacheableProperty'))->toBeTrue();

    unset($instance->cacheableProperty);

    expect($instance->cachedAttributeIsset('cacheableProperty'))->toBeFalse();
});

class HasAttributesWithoutConstructor
{
    use HasAttributes;

    public function someAttribute(): Attribute
    {
        return new Attribute(function () {
        });
    }
}

class HasAttributesWithConstructorArguments extends HasAttributesWithoutConstructor
{
    public function __construct($someValue)
    {
    }
}

class HasAttributesWithArrayCast
{
    use HasAttributes;

    public function getArrayableAttributes(): array
    {
        return ['foo' => ''];
    }

    public function getCasts(): array
    {
        return ['foo' => 'array'];
    }

    public function usesTimestamps(): bool
    {
        return false;
    }
}

/**
 * @property string $cacheableProperty
 */
class HasCacheableAttributeWithAccessor extends Model
{
    public function cacheableProperty(): Attribute
    {
        return Attribute::make(
            get: fn () => 'foo'
        )->shouldCache();
    }

    public function cachedAttributeIsset($attribute): bool
    {
        return isset($this->attributeCastCache[$attribute]);
    }
}
