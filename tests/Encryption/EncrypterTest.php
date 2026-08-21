<?php

use Voyager\Contracts\Encryption\DecryptException;
use Voyager\Encryption\Encrypter;

const UNSUPPORTED_CIPHER_MESSAGE = 'Unsupported cipher or incorrect key length. Supported ciphers are: aes-128-cbc, aes-256-cbc, aes-128-gcm, aes-256-gcm.';

test('it round trips strings, empty strings, long strings and arrays', function () {
    $e = new Encrypter(str_repeat('a', 16));

    $encrypted = $e->encrypt('foo');
    expect($encrypted)->not->toBe('foo')
        ->and($e->decrypt($encrypted))->toBe('foo');

    $encrypted = $e->encrypt('');
    expect($e->decrypt($encrypted))->toBe('');

    $longString = str_repeat('a', 1000);
    $encrypted = $e->encrypt($longString);
    expect($e->decrypt($encrypted))->toBe($longString);

    $data = ['foo' => 'bar', 'baz' => 'qux'];
    $encryptedArray = $e->encrypt($data);
    expect($encryptedArray)->not->toBe($data)
        ->and($e->decrypt($encryptedArray))->toBe($data);
});

test('it round trips a raw string', function () {
    $e = new Encrypter(str_repeat('a', 16));
    $encrypted = $e->encryptString('foo');

    expect($encrypted)->not->toBe('foo')
        ->and($e->decryptString($encrypted))->toBe('foo');
});

test('a raw string encrypted under a previous key still decrypts', function () {
    $previous = new Encrypter(str_repeat('b', 16));
    $previousValue = $previous->encryptString('foo');

    $new = new Encrypter(str_repeat('a', 16));
    $new->previousKeys([str_repeat('b', 16)]);

    expect($new->decryptString($previousValue))->toBe('foo');
});

test('it validates the mac on a per key basis', function () {
    // Payload created with (key: str_repeat('b', 16)) but will
    // "successfully" decrypt with (key: str_repeat('a', 16)), however it
    // outputs a random binary string as it is not the correct key.
    $encrypted = 'eyJpdiI6Ilg0dFM5TVRibEFqZW54c3lQdWJoVVE9PSIsInZhbHVlIjoiRGJpa2p2ZHI3eUs0dUtRakJneUhUUT09IiwibWFjIjoiMjBjZWYxODdhNThhOTk4MTk1NTc0YTE1MDgzODU1OWE0ZmQ4MDc5ZjMxYThkOGM1ZmM1MzlmYzBkYTBjMWI1ZiIsInRhZyI6IiJ9';

    $new = new Encrypter(str_repeat('a', 16));
    $new->previousKeys([str_repeat('b', 16)]);

    expect($new->decryptString($encrypted))->toBe('foo');
});

test('it encrypts using a base64 encoded key', function () {
    $e = new Encrypter(random_bytes(16));
    $encrypted = $e->encrypt('foo');

    expect($encrypted)->not->toBe('foo')
        ->and($e->decrypt($encrypted))->toBe('foo');
});

test('the encrypted length is fixed for a fixed input', function () {
    $e = new Encrypter(str_repeat('a', 16));

    $lengths = [];
    for ($i = 0; $i < 100; $i++) {
        $lengths[] = strlen($e->encrypt('foo'));
    }

    expect(max($lengths))->toBe(min($lengths));
});

test('it round trips with a custom cipher', function () {
    $e = new Encrypter(str_repeat('b', 32), 'AES-256-GCM');
    $encrypted = $e->encrypt('bar');
    expect($encrypted)->not->toBe('bar')
        ->and($e->decrypt($encrypted))->toBe('bar');

    $e = new Encrypter(random_bytes(32), 'AES-256-GCM');
    $encrypted = $e->encrypt('foo');
    expect($encrypted)->not->toBe('foo')
        ->and($e->decrypt($encrypted))->toBe('foo');
});

test('cipher names can be mixed case', function () {
    $upper = new Encrypter(str_repeat('b', 16), 'AES-128-GCM');
    $encrypted = $upper->encrypt('bar');
    expect($encrypted)->not->toBe('bar');

    $lower = new Encrypter(str_repeat('b', 16), 'aes-128-gcm');
    expect($lower->decrypt($encrypted))->toBe('bar');

    $mixed = new Encrypter(str_repeat('b', 16), 'aEs-128-GcM');
    expect($mixed->decrypt($encrypted))->toBe('bar');
});

describe('aead ciphers', function () {
    test('an aead cipher includes a tag and no mac', function () {
        $e = new Encrypter(str_repeat('b', 32), 'AES-256-GCM');
        $data = json_decode(base64_decode($e->encrypt('foo')));

        expect($data->mac)->toBeEmpty()
            ->and($data->tag)->not->toBeEmpty();
    });

    test('an aead tag must be provided in full length', function () {
        $e = new Encrypter(str_repeat('b', 32), 'AES-256-GCM');
        $data = json_decode(base64_decode($e->encrypt('foo')));

        $data->tag = substr($data->tag, 0, 4);

        $e->decrypt(base64_encode(json_encode($data)));
    })->throws(DecryptException::class, 'Could not decrypt the data.');

    test('an aead tag cannot be modified', function () {
        $e = new Encrypter(str_repeat('b', 32), 'AES-256-GCM');
        $data = json_decode(base64_decode($e->encrypt('foo')));

        $data->tag[0] = $data->tag[0] === 'A' ? 'B' : 'A';

        $e->decrypt(base64_encode(json_encode($data)));
    })->throws(DecryptException::class, 'Could not decrypt the data.');

    test('a non aead cipher includes a mac and no tag', function () {
        $e = new Encrypter(str_repeat('b', 32), 'AES-256-CBC');
        $data = json_decode(base64_decode($e->encrypt('foo')));

        expect($data->tag)->toBeEmpty()
            ->and($data->mac)->not->toBeEmpty();
    });
});

describe('key and cipher validation', function () {
    test('it does not allow a longer key', function () {
        new Encrypter(str_repeat('z', 32));
    })->throws(RuntimeException::class, UNSUPPORTED_CIPHER_MESSAGE);

    test('it rejects a bad key length', function () {
        new Encrypter(str_repeat('a', 5));
    })->throws(RuntimeException::class, UNSUPPORTED_CIPHER_MESSAGE);

    test('it rejects a bad key length for an alternative cipher', function () {
        new Encrypter(str_repeat('a', 16), 'AES-256-GCM');
    })->throws(RuntimeException::class, UNSUPPORTED_CIPHER_MESSAGE);

    test('it rejects an unsupported cipher', function () {
        new Encrypter(str_repeat('c', 16), 'AES-256-CFB8');
    })->throws(RuntimeException::class, UNSUPPORTED_CIPHER_MESSAGE);
});

describe('decryption failures', function () {
    test('a shuffled payload is invalid', function () {
        $e = new Encrypter(str_repeat('a', 16));

        $e->decrypt(str_shuffle($e->encrypt('foo')));
    })->throws(DecryptException::class, 'The payload is invalid.');

    test('a tag added to a non aead payload is rejected', function () {
        $e = new Encrypter(str_repeat('a', 16));
        $decodedPayload = json_decode(base64_decode($e->encrypt('foo')));
        $decodedPayload->tag = 'set-manually';

        $e->decrypt(base64_encode(json_encode($decodedPayload)));
    })->throws(DecryptException::class, 'Unable to use tag because the cipher algorithm does not support AEAD.');

    test('a payload from a different key has an invalid mac', function () {
        $a = new Encrypter(str_repeat('a', 16));
        $b = new Encrypter(str_repeat('b', 16));

        $b->decrypt($a->encrypt('baz'));
    })->throws(DecryptException::class, 'The MAC is invalid.');

    test('a payload whose iv is too long is invalid', function () {
        $e = new Encrypter(str_repeat('a', 16));
        $data = json_decode(base64_decode($e->encrypt('foo')), true);
        $data['iv'] .= $data['value'][0];
        $data['value'] = substr($data['value'], 1);

        $e->decrypt(base64_encode(json_encode($data)));
    })->throws(DecryptException::class, 'The payload is invalid.');

    test('a tampered payload gets rejected', function (array $payload) {
        $enc = new Encrypter(str_repeat('x', 16));

        $enc->decrypt(base64_encode(json_encode($payload)));
    })->with(function () {
        $validIv = base64_encode(str_repeat('.', 16));

        return [
            'iv is an array' => [['iv' => ['value_in_array'], 'value' => '', 'mac' => '']],
            'iv is an object' => [['iv' => new class() {}, 'value' => '', 'mac' => '']],
            'value is an array' => [['iv' => $validIv, 'value' => ['value_in_array'], 'mac' => '']],
            'value is an object' => [['iv' => $validIv, 'value' => new class() {}, 'mac' => '']],
            'mac is an array' => [['iv' => $validIv, 'value' => '', 'mac' => ['value_in_array']]],
            'mac is null' => [['iv' => $validIv, 'value' => '', 'mac' => null]],
            'tag is an array' => [['iv' => $validIv, 'value' => '', 'mac' => '', 'tag' => ['value_in_array']]],
            'tag is an integer' => [['iv' => $validIv, 'value' => '', 'mac' => '', 'tag' => -1]],
        ];
    })->throws(DecryptException::class, 'The payload is invalid.');
});

test('the supported method accepts any casing', function () {
    $key = str_repeat('a', 16);

    expect(Encrypter::supported($key, 'AES-128-GCM'))->toBeTrue()
        ->and(Encrypter::supported($key, 'aes-128-CBC'))->toBeTrue()
        ->and(Encrypter::supported($key, 'aes-128-cbc'))->toBeTrue();
});

describe('appearsEncrypted', function () {
    test('it returns true for an encrypted value', function () {
        $e = new Encrypter(str_repeat('a', 16));

        expect(Encrypter::appearsEncrypted($e->encrypt('foo')))->toBeTrue();
    });

    test('it returns true for an encrypted array', function () {
        $e = new Encrypter(str_repeat('a', 16));

        expect(Encrypter::appearsEncrypted($e->encrypt(['foo' => 'bar'])))->toBeTrue();
    });

    test('it returns false for plain text', function () {
        expect(Encrypter::appearsEncrypted('foo'))->toBeFalse()
            ->and(Encrypter::appearsEncrypted('APP_NAME=Venusian'))->toBeFalse()
            ->and(Encrypter::appearsEncrypted("APP_NAME=Venusian\nAPP_ENV=local"))->toBeFalse();
    });

    test('it returns false for a non string', function () {
        expect(Encrypter::appearsEncrypted(123))->toBeFalse()
            ->and(Encrypter::appearsEncrypted(['foo' => 'bar']))->toBeFalse()
            ->and(Encrypter::appearsEncrypted(null))->toBeFalse();
    });
});
