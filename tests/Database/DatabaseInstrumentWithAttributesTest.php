<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Casts\Attribute;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Schema\Builder;

function dbWithAttrsBootTable() : void
{
    dbWithAttrsSchema()->create((new WithAttributesModel)->getTable(), function ($table) {
        $table->id();
        $table->boolean('is_admin');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('type');
        $table->timestamps();
    });
}

function dbWithAttrsSchema() : Builder
{
    return WithAttributesModel::getConnectionResolver()->connection()->getSchemaBuilder();
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->bootInstrument();
    $db->setAsGlobal();
});

afterEach(function () {
    dbWithAttrsSchema()->dropIfExists((new WithAttributesModel)->getTable());

    parent::tearDown();
});

test('adds attributes', function () {
    $key = 'a key';
    $value = 'the value';

    $query = WithAttributesModel::query()
        ->withAttributes([$key => $value]);

    $model = $query->make();

    $this->assertSame($value, $model->$key);
});

test('adds wheres', function () {
    $key = 'a key';
    $value = 'the value';

    $query = WithAttributesModel::query()
        ->withAttributes([$key => $value]);

    $wheres = $query->toBase()->wheres;

    $this->assertContains([
        'type' => 'Basic',
        'column' => 'with_attributes_models.'.$key,
        'operator' => '=',
        'value' => $value,
        'boolean' => 'and',
    ], $wheres);
});

test('adds with casts', function () {
    $query = WithAttributesModel::query()
        ->withAttributes([
            'is_admin' => 1,
            'first_name' => 'FIRST',
            'last_name' => 'LAST',
            'type' => WithAttributesEnum::internal,
        ]);

    $model = $query->make();

    $this->assertSame(true, $model->is_admin);
    $this->assertSame('First', $model->first_name);
    $this->assertSame('Last', $model->last_name);
    $this->assertSame(WithAttributesEnum::internal, $model->type);

    $this->assertEqualsCanonicalizing([
        'is_admin' => 1,
        'first_name' => 'first',
        'last_name' => 'last',
        'type' => 'int',
    ], $model->getAttributes());
});

test('adds with casts via db', function () {
    dbWithAttrsBootTable();

    $query = WithAttributesModel::query()
        ->withAttributes([
            'is_admin' => 1,
            'first_name' => 'FIRST',
            'last_name' => 'LAST',
            'type' => WithAttributesEnum::internal,
        ]);

    $query->create();

    $model = WithAttributesModel::first();

    $this->assertSame(true, $model->is_admin);
    $this->assertSame('First', $model->first_name);
    $this->assertSame('Last', $model->last_name);
    $this->assertSame(WithAttributesEnum::internal, $model->type);
});

class WithAttributesModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_admin' => 'boolean',
        'type' => WithAttributesEnum::class,
    ];

    public function setFirstNameAttribute(string $value): void
    {
        $this->attributes['first_name'] = strtolower($value);
    }

    public function getFirstNameAttribute(?string $value): string
    {
        return ucfirst($value);
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucfirst($value),
            set: fn (string $value) => strtolower($value),
        );
    }
}

enum WithAttributesEnum: string
{
    case internal = 'int';
}
