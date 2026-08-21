<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\HasMany;
use Voyager\Database\Instrument\Relations\HasOne;
use Voyager\Database\Query\Builder as Query;
use Tests\Database\DynamicRelationModel2 as Related;

test('basic dynamic relations', function () {
    DynamicRelationModel::resolveRelationUsing('dynamicRel_2', fn () => new FakeHasManyRel);
    $model = new DynamicRelationModel;

    expect($model->dynamicRel_2)->toEqual(['many' => 'related'])
        ->and($model->getRelationValue('dynamicRel_2'))->toEqual(['many' => 'related']);
});

test('basic dynamic relations override', function () {
    // Dynamic Relations can override each other.
    DynamicRelationModel::resolveRelationUsing('dynamicRelConflict', fn ($m) => $m->hasOne(Related::class));
    DynamicRelationModel::resolveRelationUsing('dynamicRelConflict', fn (DynamicRelationModel $m) => new FakeHasManyRel);

    $model = new DynamicRelationModel;

    expect($model->dynamicRelConflict())->toBeInstanceOf(HasMany::class)
        ->and($model->dynamicRelConflict)->toEqual(['many' => 'related'])
        ->and($model->getRelationValue('dynamicRelConflict'))->toEqual(['many' => 'related'])
        ->and($model->isRelation('dynamicRelConflict'))->toBeTrue();
});

test('inharited dynamic relations', function () {
    DynamicRelationModel::resolveRelationUsing('inheritedDynamicRel', fn () => new FakeHasManyRel);
    $model = new DynamicRelationModel;
    $model2 = new DynamicRelationModel2;
    $model4 = new DynamicRelationModel4;

    expect($model->isRelation('inheritedDynamicRel'))->toBeTrue()
        ->and($model4->isRelation('inheritedDynamicRel'))->toBeTrue()
        ->and($model2->isRelation('inheritedDynamicRel'))->toBeFalse()
        ->and($model->inheritedDynamicRel())->toEqual($model4->inheritedDynamicRel())
        ->and($model->inheritedDynamicRel)->toEqual($model4->inheritedDynamicRel);
});

test('inherited dynamic relations override', function () {
    // Inherited Dynamic Relations can be overridden
    DynamicRelationModel::resolveRelationUsing('dynamicRelConflict', fn ($m) => $m->hasOne(Related::class));
    $model = new DynamicRelationModel;
    $model4 = new DynamicRelationModel4;

    expect($model->dynamicRelConflict())->toBeInstanceOf(HasOne::class)
        ->and($model4->dynamicRelConflict())->toBeInstanceOf(HasOne::class);

    DynamicRelationModel4::resolveRelationUsing('dynamicRelConflict', fn ($m) => $m->hasMany(Related::class));

    expect($model->dynamicRelConflict())->toBeInstanceOf(HasOne::class)
        ->and($model4->dynamicRelConflict())->toBeInstanceOf(HasMany::class);
});

test('dynamic relations can not have the same name as normal relations', function () {
    $model = new DynamicRelationModel;

    // Dynamic relations can not override hard-coded methods.
    DynamicRelationModel::resolveRelationUsing('hardCodedRelation', fn ($m) => $m->hasOne(Related::class));

    expect($model->hardCodedRelation())->toBeInstanceOf(HasMany::class)
        ->and($model->hardCodedRelation)->toEqual(['many' => 'related'])
        ->and($model->getRelationValue('hardCodedRelation'))->toEqual(['many' => 'related'])
        ->and($model->isRelation('hardCodedRelation'))->toBeTrue();
});

test('relation resolvers', function () {
    $model1 = new DynamicRelationModel;
    $model3 = new DynamicRelationModel3;

    // Same dynamic methods with the same name on two models do not conflict or override.
    DynamicRelationModel::resolveRelationUsing('dynamicRel', fn ($m) => $m->hasOne(Related::class));
    DynamicRelationModel3::resolveRelationUsing('dynamicRel', fn (DynamicRelationModel3 $m) => $m->hasMany(Related::class));

    expect($model1->dynamicRel())->toBeInstanceOf(HasOne::class)
        ->and($model3->dynamicRel())->toBeInstanceOf(HasMany::class)
        ->and($model1->isRelation('dynamicRel'))->toBeTrue()
        ->and($model3->isRelation('dynamicRel'))->toBeTrue();
});

class DynamicRelationModel extends Model
{
    public function hardCodedRelation()
    {
        return new FakeHasManyRel();
    }
}

class DynamicRelationModel2 extends Model
{
    public function getResults()
    {
        //
    }

    public function newQuery()
    {
        $query = new class extends Query
        {
            public function __construct()
            {
                //
            }
        };

        return new Builder($query);
    }
}

class DynamicRelationModel3 extends Model
{
    //
}

class DynamicRelationModel4 extends DynamicRelationModel
{
    //
}

class FakeHasManyRel extends HasMany
{
    public function __construct()
    {
        //
    }

    public function getResults()
    {
        return ['many' => 'related'];
    }
}
