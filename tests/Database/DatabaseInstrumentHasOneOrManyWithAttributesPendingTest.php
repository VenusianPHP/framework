<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model;

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->bootInstrument();
    $db->setAsGlobal();
});

test('has many adds attributes', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes([$key => $value], asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->parent_id)->toBe($parentId)
        ->and($relatedModel->$key)->toBe($value);
});

test('has one adds attributes', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasOne(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes([$key => $value], asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->parent_id)->toBe($parentId)
        ->and($relatedModel->$key)->toBe($value);
});

test('morph many adds attributes', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->morphMany(RelatedPendingAttributesModel::class, 'relatable')
        ->withAttributes([$key => $value], asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->relatable_id)->toBe($parentId)
        ->and($relatedModel->relatable_type)->toBe($parent::class)
        ->and($relatedModel->$key)->toBe($value);
});

test('morph one adds attributes', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->morphOne(RelatedPendingAttributesModel::class, 'relatable')
        ->withAttributes([$key => $value], asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->relatable_id)->toBe($parentId)
        ->and($relatedModel->relatable_type)->toBe($parent::class)
        ->and($relatedModel->$key)->toBe($value);
});

test('pending attributes can be overridden', function () {
    $key = 'a key';
    $defaultValue = 'a value';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'relatable')
        ->withAttributes([$key => $defaultValue], asConditions: false);

    $relatedModel = $relationship->make([$key => $value]);

    expect($relatedModel->$key)->toBe($value);
});

test('querying does not break wither', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->where($key, $value)
        ->withAttributes([$key => $value], asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->parent_id)->toBe($parentId)
        ->and($relatedModel->$key)->toBe($value);
});

test('attributes can be appended', function () {
    $parent = new RelatedPendingAttributesModel;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes(['a' => 'A'], asConditions: false)
        ->withAttributes(['b' => 'B'], asConditions: false)
        ->withAttributes(['a' => 'AA'], asConditions: false);

    $relatedModel = $relationship->make([
        'b' => 'BB',
        'c' => 'C',
    ]);

    expect($relatedModel->a)->toBe('AA')
        ->and($relatedModel->b)->toBe('BB')
        ->and($relatedModel->c)->toBe('C');
});

test('single attribute api', function () {
    $parent = new RelatedPendingAttributesModel;
    $key = 'attr';
    $value = 'Value';

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes($key, $value, asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->$key)->toBe($value);
});

test('wheres are not set', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes([$key => $value], asConditions: false);

    $wheres = $relationship->toBase()->wheres;

    $this->assertContains([
        'type' => 'Basic',
        'column' => $parent->qualifyColumn('parent_id'),
        'operator' => '=',
        'value' => $parentId,
        'boolean' => 'and',
    ], $wheres);

    $this->assertContains([
        'type' => 'NotNull',
        'column' => $parent->qualifyColumn('parent_id'),
        'boolean' => 'and',
    ], $wheres);

    // Ensure no other wheres exist
    expect($wheres)->toHaveCount(2);
});

test('null value is accepted', function () {
    $parentId = 123;
    $key = 'a key';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes([$key => null], asConditions: false);

    $wheres = $relationship->toBase()->wheres;
    $relatedModel = $relationship->make();

    expect($relatedModel->$key)->toBeNull();

    $this->assertContains([
        'type' => 'Basic',
        'column' => $parent->qualifyColumn('parent_id'),
        'operator' => '=',
        'value' => $parentId,
        'boolean' => 'and',
    ], $wheres);

    $this->assertContains([
        'type' => 'NotNull',
        'column' => $parent->qualifyColumn('parent_id'),
        'boolean' => 'and',
    ], $wheres);

    // Ensure no other wheres exist
    expect($wheres)->toHaveCount(2);
});

test('one keeps attributes from has many', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes([$key => $value], asConditions: false)
        ->one();

    $relatedModel = $relationship->make();

    expect($relatedModel->parent_id)->toBe($parentId)
        ->and($relatedModel->$key)->toBe($value);
});

test('one keeps attributes from morph many', function () {
    $parentId = 123;
    $key = 'a key';
    $value = 'the value';

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->morphMany(RelatedPendingAttributesModel::class, 'relatable')
        ->withAttributes([$key => $value], asConditions: false)
        ->one();

    $relatedModel = $relationship->make();

    expect($relatedModel->relatable_id)->toBe($parentId)
        ->and($relatedModel->relatable_type)->toBe($parent::class)
        ->and($relatedModel->$key)->toBe($value);
});

test('has many adds casted attributes', function () {
    $parentId = 123;

    $parent = new RelatedPendingAttributesModel;
    $parent->id = $parentId;

    $relationship = $parent
        ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
        ->withAttributes(['is_admin' => 1], asConditions: false);

    $relatedModel = $relationship->make();

    expect($relatedModel->parent_id)->toBe($parentId)
        ->and($relatedModel->is_admin)->toBe(true);
});

class RelatedPendingAttributesModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_admin' => 'boolean',
    ];
}
