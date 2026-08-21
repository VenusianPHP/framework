<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Casts\Attribute;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Schema\Builder;

function pendingAttributesSchema(): Builder
{
    return PendingAttributesModel::getConnectionResolver()->connection()->getSchemaBuilder();
}

function pendingAttributesBootTable(): void
{
    pendingAttributesSchema()->create((new PendingAttributesModel)->getTable(), function ($table) {
        $table->id();
        $table->boolean('is_admin');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('type');
        $table->timestamps();
    });
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
    pendingAttributesSchema()->dropIfExists((new PendingAttributesModel)->getTable());
});

test('adds attributes', function () {
    $key = 'a key';
    $value = 'the value';

    $query = PendingAttributesModel::query()
        ->withAttributes([$key => $value], asConditions: false);

    $model = $query->make();

    $this->assertSame($value, $model->$key);
});

test('does not add wheres', function () {
    $key = 'a key';
    $value = 'the value';

    $query = PendingAttributesModel::query()
        ->withAttributes([$key => $value], asConditions: false);

    $wheres = $query->toBase()->wheres;

    // Ensure no wheres exist
    $this->assertEmpty($wheres);
});

test('adds with casts', function () {
    $query = PendingAttributesModel::query()
        ->withAttributes([
            'is_admin' => 1,
            'first_name' => 'FIRST',
            'last_name' => 'LAST',
            'type' => PendingAttributesEnum::internal,
        ], asConditions: false);

    $model = $query->make();

    $this->assertSame(true, $model->is_admin);
    $this->assertSame('First', $model->first_name);
    $this->assertSame('Last', $model->last_name);
    $this->assertSame(PendingAttributesEnum::internal, $model->type);

    $this->assertEqualsCanonicalizing([
        'is_admin' => 1,
        'first_name' => 'first',
        'last_name' => 'last',
        'type' => 'int',
    ], $model->getAttributes());
});

test('adds with casts via db', function () {
    pendingAttributesBootTable();

    $query = PendingAttributesModel::query()
        ->withAttributes([
            'is_admin' => 1,
            'first_name' => 'FIRST',
            'last_name' => 'LAST',
            'type' => PendingAttributesEnum::internal,
        ], asConditions: false);

    $query->create();

    $model = PendingAttributesModel::first();

    $this->assertSame(true, $model->is_admin);
    $this->assertSame('First', $model->first_name);
    $this->assertSame('Last', $model->last_name);
    $this->assertSame(PendingAttributesEnum::internal, $model->type);
});

class PendingAttributesModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_admin' => 'boolean',
        'type' => PendingAttributesEnum::class,
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

enum PendingAttributesEnum: string
{
    case internal = 'int';
}
