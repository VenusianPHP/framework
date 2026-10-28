<?php

use Venusian\Tests\Database\Fixtures\Widget;
use Voyager\Database\Instrument\Collection;

/** A plain class an Instrument Collection maps its models into, as a resource collection does. */
final class WidgetName
{
    public function __construct(public Widget $widget, public int $key) {}
}

it('maps an Instrument Collection into a non-model class, handing back a base collection', function () {
    $models = new Collection([(new Widget)->forceFill(['name' => 'gear']), (new Widget)->forceFill(['name' => 'cog'])]);

    $mapped = $models->mapInto(WidgetName::class);

    // Laravel's Eloquent collection does the same: once the items are not models, the result is a base collection.
    expect($mapped)->toBeInstanceOf(Voyager\NutsAndBolts\Collection::class)
        ->not->toBeInstanceOf(Collection::class)
        ->and($mapped->map(fn (WidgetName $item) => [$item->key, $item->widget->name])->all())->toBe([[0, 'gear'], [1, 'cog']]);
});


/** Two widgets, gear and cog, as an Instrument Collection. */
function widgets(): Collection
{
    return new Collection([(new Widget)->forceFill(['name' => 'gear']), (new Widget)->forceFill(['name' => 'cog'])]);
}

it('finds duplicates of what an Instrument Collection maps its models to', function () {
    $models = new Collection([(new Widget)->forceFill(['name' => 'gear']), (new Widget)->forceFill(['name' => 'gear'])]);

    expect($models->duplicates('name')->all())->toBe([1 => 'gear']);
});

it('finds strict duplicates of what an Instrument Collection maps its models to', function () {
    $models = new Collection([(new Widget)->forceFill(['name' => '1']), (new Widget)->forceFill(['name' => 1]), (new Widget)->forceFill(['name' => '1'])]);

    expect($models->duplicatesStrict(fn (Widget $widget) => $widget->name)->all())->toBe([2 => '1']);
});

it('still compares models by identity without a callback', function () {
    $gear = (new Widget)->forceFill(['id' => 1, 'name' => 'gear']);
    $models = new Collection([$gear, (new Widget)->forceFill(['id' => 2, 'name' => 'gear']), $gear]);

    expect($models->duplicates()->keys()->all())->toBe([2]);
});

it('transforms an Instrument Collection\'s models into non-models in place', function () {
    $models = widgets();

    expect($models->transform(fn (Widget $widget) => $widget->name)->all())->toBe(['gear', 'cog']);
});

it('spreads mapped values through an Instrument Collection into non-models', function () {
    $pairs = new Collection([[(new Widget)->forceFill(['name' => 'gear']), 1]]);

    expect($pairs->mapSpread(fn (Widget $widget, int $count) => "{$widget->name}:{$count}")->all())->toBe(['gear:1']);
});

it('groups an Instrument Collection by what its callback returns', function () {
    expect(widgets()->mapToGroups(fn (Widget $widget) => [strlen($widget->name) => $widget->name])->toArray())
        ->toBe([4 => ['gear'], 3 => ['cog']]);
});

it('flat-maps an Instrument Collection into non-models', function () {
    expect(widgets()->flatMap(fn (Widget $widget) => [$widget->name, strtoupper($widget->name)])->all())
        ->toBe(['gear', 'GEAR', 'cog', 'COG']);
});
