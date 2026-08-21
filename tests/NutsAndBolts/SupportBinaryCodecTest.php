<?php

use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Ulid;
use Voyager\NutsAndBolts\BinaryCodec;

const CODEC_UUID = '550e8400-e29b-41d4-a716-446655440000';
const CODEC_ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

afterEach(function () {
    (new ReflectionClass(BinaryCodec::class))->getProperty('customCodecs')->setValue(null, []);
});

dataset('null and empty', [
    'null' => [null],
    'empty string' => [''],
]);

describe('formats', function () {
    test('the default formats are registered', function () {
        expect(BinaryCodec::formats())->toContain('uuid')->toContain('ulid');
    });

    test('register adds a custom format', function () {
        BinaryCodec::register('hex', fn ($v) => bin2hex($v ?? ''), fn ($v) => hex2bin($v ?? ''));

        expect(BinaryCodec::formats())->toContain('hex');
    });

    test('register overrides a default format', function () {
        BinaryCodec::register('uuid', fn ($v) => 'custom-encode', fn ($v) => 'custom-decode');

        expect(BinaryCodec::encode('test', 'uuid'))->toBe('custom-encode')
            ->and(BinaryCodec::decode('test', 'uuid'))->toBe('custom-decode');
    });

    test('encode throws on an unknown format', function () {
        BinaryCodec::encode('value', 'invalid');
    })->throws(InvalidArgumentException::class, 'Format [invalid] is invalid.');

    test('decode throws on an unknown format', function () {
        BinaryCodec::decode('value', 'invalid');
    })->throws(InvalidArgumentException::class, 'Format [invalid] is invalid.');
});

test('encode returns null for null and empty values', function ($value) {
    expect(BinaryCodec::encode($value, 'uuid'))->toBeNull()
        ->and(BinaryCodec::encode($value, 'ulid'))->toBeNull();
})->with('null and empty');

test('decode returns null for null and empty values', function ($value) {
    expect(BinaryCodec::decode($value, 'uuid'))->toBeNull()
        ->and(BinaryCodec::decode($value, 'ulid'))->toBeNull();
})->with('null and empty');

describe('uuid', function () {
    test('encode accepts a string', function () {
        expect(BinaryCodec::encode(CODEC_UUID, 'uuid'))->toBe(Uuid::fromString(CODEC_UUID)->getBytes());
    });

    test('encode passes binary through', function () {
        $bytes = Uuid::fromString(CODEC_UUID)->getBytes();

        expect(BinaryCodec::encode($bytes, 'uuid'))->toBe($bytes);
    });

    test('encode accepts an instance', function () {
        $uuid = Uuid::fromString(CODEC_UUID);

        expect(BinaryCodec::encode($uuid, 'uuid'))->toBe($uuid->getBytes());
    });

    test('decode accepts binary', function () {
        expect(BinaryCodec::decode(Uuid::fromString(CODEC_UUID)->getBytes(), 'uuid'))->toBe(CODEC_UUID);
    });

    test('decode passes a string through', function () {
        expect(BinaryCodec::decode(CODEC_UUID, 'uuid'))->toBe(CODEC_UUID);
    });
});

describe('ulid', function () {
    test('encode accepts a string', function () {
        expect(BinaryCodec::encode(CODEC_ULID, 'ulid'))->toBe(Ulid::fromString(CODEC_ULID)->toBinary());
    });

    test('encode passes binary through', function () {
        $bytes = Ulid::fromString(CODEC_ULID)->toBinary();

        expect(BinaryCodec::encode($bytes, 'ulid'))->toBe($bytes);
    });

    test('encode accepts an instance', function () {
        $ulid = Ulid::fromString(CODEC_ULID);

        expect(BinaryCodec::encode($ulid, 'ulid'))->toBe($ulid->toBinary());
    });

    test('decode accepts binary', function () {
        expect(BinaryCodec::decode(Ulid::fromString(CODEC_ULID)->toBinary(), 'ulid'))->toBe(CODEC_ULID);
    });

    test('decode passes a string through', function () {
        expect(BinaryCodec::decode(CODEC_ULID, 'ulid'))->toBe(CODEC_ULID);
    });
});

test('isBinary only reports true for non-UTF-8 strings', function () {
    expect(BinaryCodec::isBinary(null))->toBeFalse()
        ->and(BinaryCodec::isBinary(123))->toBeFalse()
        ->and(BinaryCodec::isBinary([]))->toBeFalse()
        ->and(BinaryCodec::isBinary(''))->toBeFalse()
        ->and(BinaryCodec::isBinary('hello'))->toBeFalse()
        ->and(BinaryCodec::isBinary('héllo'))->toBeFalse()
        ->and(BinaryCodec::isBinary('日本語'))->toBeFalse()
        ->and(BinaryCodec::isBinary("hello\0world"))->toBeTrue()
        ->and(BinaryCodec::isBinary("\0"))->toBeTrue()
        ->and(BinaryCodec::isBinary("\xFF\xFE"))->toBeTrue()
        ->and(BinaryCodec::isBinary(random_bytes(16)))->toBeTrue();
});
