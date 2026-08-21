<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Casts\AsBinary;
use Voyager\Database\Instrument\Model;
use Voyager\NutsAndBolts\BinaryCodec;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Ulid;

afterEach(function () {
    $reflection = new \ReflectionClass(BinaryCodec::class);
    $property = $reflection->getProperty('customCodecs');
    $property->setValue(null, []);
});

test('cast throws when format missing', function () {
    $model = new AsBinaryTestModel;
    $model->setRawAttributes(['no_format' => 'value']);
    $model->no_format;
})->throws(InvalidArgumentException::class, 'The binary codec format is required.');

test('cast throws on invalid format', function () {
    $model = new AsBinaryTestModel;
    $model->setRawAttributes(['invalid_format' => 'value']);
    $model->invalid_format;
})->throws(InvalidArgumentException::class, 'Unsupported binary codec format [invalid]. Allowed formats are: uuid, ulid.');

test('get decodes uuid from binary', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $model = new AsBinaryTestModel;
    $model->setRawAttributes(['uuid' => Uuid::fromString($uuid)->getBytes()]);

    expect($model->uuid)->toBe($uuid);
});

test('set encodes uuid to binary', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $model = new AsBinaryTestModel;
    $model->uuid = $uuid;

    expect($model->getAttributes()['uuid'])->toBe(Uuid::fromString($uuid)->getBytes());
});

test('get decodes ulid from binary', function () {
    $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    $model = new AsBinaryTestModel;
    $model->setRawAttributes(['ulid' => Ulid::fromString($ulid)->toBinary()]);

    expect($model->ulid)->toBe($ulid);
});

test('set encodes ulid to binary', function () {
    $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    $model = new AsBinaryTestModel;
    $model->ulid = $ulid;

    expect($model->getAttributes()['ulid'])->toBe(Ulid::fromString($ulid)->toBinary());
});

test('get returns null for null value', function () {
    $model = new AsBinaryTestModel;
    $model->setRawAttributes(['uuid' => null]);

    expect($model->uuid)->toBeNull();
});

test('set encodes null to null', function () {
    $model = new AsBinaryTestModel;
    $model->uuid = null;

    expect($model->getAttributes()['uuid'])->toBeNull();
});

test('uuid helper method', function () {
    expect(AsBinary::uuid())->toBe(AsBinary::class.':uuid');
});

test('ulid helper method', function () {
    expect(AsBinary::ulid())->toBe(AsBinary::class.':ulid');
});

test('of helper method', function () {
    expect(AsBinary::of('custom'))->toBe(AsBinary::class.':custom');
});

class AsBinaryTestModel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'uuid' => AsBinary::class.':uuid',
            'ulid' => AsBinary::class.':ulid',
            'no_format' => AsBinary::class,
            'invalid_format' => AsBinary::class.':invalid',
        ];
    }
}
