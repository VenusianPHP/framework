<?php

namespace Tests\Database;

use Voyager\Database\ClassMorphViolationException;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\Pivot;
use Voyager\Database\Instrument\Relations\Relation;

beforeEach(function () {
    Relation::requireMorphMap();
});

afterEach(function () {
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
});

test('strict mode throws an exception on class map', function () {
    $model = new TestModel;

    $model->getMorphClass();
})->throws(ClassMorphViolationException::class);

test('strict mode does not throw exception when morph map', function () {
    $model = new TestModel;

    Relation::morphMap([
        'test' => TestModel::class,
    ]);

    $morphName = $model->getMorphClass();
    expect($morphName)->toBe('test');
});

test('maps can be enforced in one method', function () {
    $model = new TestModel;

    Relation::requireMorphMap(false);

    Relation::enforceMorphMap([
        'test' => TestModel::class,
    ]);

    $morphName = $model->getMorphClass();
    expect($morphName)->toBe('test');
});

test('map ignore generic pivot class', function () {
    $this->expectNotToPerformAssertions();

    $pivotModel = new Pivot();

    $pivotModel->getMorphClass();
});

test('map can be enforced to custom pivot class', function () {
    $pivotModel = new TestPivotModel();

    $pivotModel->getMorphClass();
})->throws(ClassMorphViolationException::class);

class TestModel extends Model
{
}

class TestPivotModel extends Pivot
{
}
