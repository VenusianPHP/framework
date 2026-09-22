<?php

use Voyager\Contracts\Encryption\DecryptException;
use Voyager\Contracts\Encryption\EncryptException;
use Voyager\Encryption\Encrypter;

function encrypter(string $cipher = 'AES-256-CBC'): Encrypter
{
    return new Encrypter(Encrypter::generateKey($cipher), $cipher);
}

dataset('ciphers', ['AES-128-CBC', 'AES-256-CBC', 'AES-128-GCM', 'AES-256-GCM']);

it('round trips a value', function (string $cipher) {
    $encrypter = encrypter($cipher);

    expect($encrypter->decrypt($encrypter->encrypt('secret sauce')))->toBe('secret sauce');
})->with('ciphers');

it('round trips a value without serializing', function (string $cipher) {
    $encrypter = encrypter($cipher);

    expect($encrypter->decrypt($encrypter->encrypt('plain', serialize: false), unserialize: false))->toBe('plain');
})->with('ciphers');

it('round trips something that is not a string', function (string $cipher) {
    $encrypter = encrypter($cipher);

    expect($encrypter->decrypt($encrypter->encrypt(['a' => 1, 'b' => [2, 3]])))->toBe(['a' => 1, 'b' => [2, 3]]);
})->with('ciphers');

it('never leaves the plaintext in the payload', function (string $cipher) {
    expect(base64_decode(encrypter($cipher)->encrypt('secret sauce')))->not->toContain('secret sauce');
})->with('ciphers');

it('gives a different payload every time', function (string $cipher) {
    $encrypter = encrypter($cipher);

    expect($encrypter->encrypt('same'))->not->toBe($encrypter->encrypt('same'));
})->with('ciphers');

it('refuses a key of the wrong length', function () {
    expect(fn () => new Encrypter('too short', 'AES-256-CBC'))
        ->toThrow(EncryptException::class, 'Unsupported cipher or incorrect key length');
});

it('refuses a cipher it does not know', function () {
    expect(fn () => new Encrypter(str_repeat('a', 32), 'AES-256-XYZ'))
        ->toThrow(EncryptException::class);
});

it('rejects a tampered payload', function (string $cipher) {
    $encrypter = encrypter($cipher);

    $payload = json_decode(base64_decode($encrypter->encrypt('secret sauce')), true);
    $payload['value'] = base64_encode('tampered');

    expect(fn () => $encrypter->decrypt(base64_encode(json_encode($payload))))->toThrow(DecryptException::class);
})->with('ciphers');

it('rejects a payload from a different key', function (string $cipher) {
    $payload = encrypter($cipher)->encrypt('secret sauce');

    expect(fn () => encrypter($cipher)->decrypt($payload))->toThrow(DecryptException::class);
})->with('ciphers');

it('rejects a payload that is not one of ours', function () {
    expect(fn () => encrypter()->decrypt(base64_encode('{"not":"a payload"}')))
        ->toThrow(DecryptException::class, 'The payload is invalid.');
});

it('rejects a payload whose iv is the wrong length', function () {
    $encrypter = encrypter();

    $payload = json_decode(base64_decode($encrypter->encrypt('x')), true);
    $payload['iv'] = base64_encode('short');

    expect(fn () => $encrypter->decrypt(base64_encode(json_encode($payload))))
        ->toThrow(DecryptException::class, 'The payload is invalid.');
});

it('recognises its own payloads and nothing else', function () {
    expect(Encrypter::appearsEncrypted(encrypter()->encrypt('x')))->toBeTrue()
        ->and(Encrypter::appearsEncrypted('APP_NAME=Venusian'))->toBeFalse();
});

it('generates a key of the length the cipher wants', function () {
    expect(strlen(Encrypter::generateKey('AES-128-CBC')))->toBe(16)
        ->and(strlen(Encrypter::generateKey('AES-256-GCM')))->toBe(32);
});
