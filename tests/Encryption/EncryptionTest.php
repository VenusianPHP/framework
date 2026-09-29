<?php

use Voyager\Encryption\Encrypter;
use Voyager\Encryption\MissingAppKeyException;
use Voyager\Contracts\Encryption\DecryptException;
use Voyager\Contracts\Encryption\EncryptException;
use Voyager\Contracts\Encryption\StringEncrypter;
use Voyager\Contracts\Encryption\Encrypter as EncrypterContract;
use Laravel\SerializableClosure\SerializableClosure;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(function () {
    SerializableClosure::setSecretKey(null);
    LogApp::tearDown($this->app, $this);
});

dataset('ciphers', [
    'aes-128-cbc' => ['AES-128-CBC', 16],
    'aes-256-cbc' => ['AES-256-CBC', 32],
    'aes-128-gcm' => ['AES-128-GCM', 16],
    'aes-256-gcm' => ['AES-256-GCM', 32],
]);

it('encrypts and decrypts values and strings on every cipher', function (string $cipher, int $size) {
    $encrypter = new Encrypter(Encrypter::generateKey($cipher), $cipher);

    expect(strlen($encrypter->getKey()))->toBe($size)
        ->and($encrypter->decrypt($encrypter->encrypt(['answer' => 42])))->toBe(['answer' => 42])
        ->and($encrypter->decryptString($encrypter->encryptString('plain text')))->toBe('plain text')
        ->and($encrypter->encryptString('same'))->not->toBe($encrypter->encryptString('same'))
        ->and(Encrypter::appearsEncrypted($encrypter->encryptString('x')))->toBeTrue()
        ->and(Encrypter::appearsEncrypted('APP_NAME=Venusian'))->toBeFalse();
})->with('ciphers');

it('refuses a key the wrong length for its cipher, and a cipher it doesn\'t support', function () {
    expect(fn () => new Encrypter(str_repeat('a', 16), 'AES-256-CBC'))->toThrow(EncryptException::class, 'Unsupported cipher or incorrect key length.')
        ->and(fn () => new Encrypter(str_repeat('a', 32), 'AES-256-CTR'))->toThrow(EncryptException::class, 'Supported ciphers are: aes-128-cbc, aes-256-cbc, aes-128-gcm, aes-256-gcm.')
        ->and(fn () => new Encrypter(str_repeat('a', 32))->previousKeys([str_repeat('b', 16)]))->toThrow(EncryptException::class, 'Unsupported cipher or incorrect key length.');
});

it('rejects a CBC payload whose value was tampered with, before decrypting it', function () {
    $encrypter = new Encrypter(str_repeat('a', 32));
    $payload = json_decode(base64_decode($encrypter->encrypt('secret')), true);
    $payload['value'] = base64_encode('tampered-bytes-!');

    expect(fn () => $encrypter->decrypt(base64_encode(json_encode($payload))))->toThrow(DecryptException::class, 'The MAC is invalid.');
});

it('rejects a GCM payload whose tag was tampered with or cut short', function () {
    $encrypter = new Encrypter(str_repeat('a', 32), 'AES-256-GCM');
    $payload = json_decode(base64_decode($encrypter->encrypt('secret')), true);

    $flipped = $payload;
    $flipped['tag'] = base64_encode(str_repeat("\0", 16));
    $short = $payload;
    $short['tag'] = base64_encode(substr(base64_decode($payload['tag']), 0, 8));

    expect(fn () => $encrypter->decrypt(base64_encode(json_encode($flipped))))->toThrow(DecryptException::class, 'Could not decrypt the data.')
        ->and(fn () => $encrypter->decrypt(base64_encode(json_encode($short))))->toThrow(DecryptException::class, 'Could not decrypt the data.');
});

it('rejects a payload that isn\'t one, and one made under another key', function () {
    $encrypter = new Encrypter(str_repeat('a', 32));
    $other = new Encrypter(str_repeat('b', 32));

    expect(fn () => $encrypter->decrypt('not a payload'))->toThrow(DecryptException::class, 'The payload is invalid.')
        ->and(fn () => $encrypter->decrypt(base64_encode(json_encode(['iv' => 'x', 'value' => 'y', 'mac' => 'z']))))->toThrow(DecryptException::class, 'The payload is invalid.')
        ->and(fn () => $encrypter->decrypt($other->encrypt('secret')))->toThrow(DecryptException::class, 'The MAC is invalid.');
});

it('decrypts what an earlier key encrypted, once that key is a previous key', function (string $cipher) {
    $old = new Encrypter(Encrypter::generateKey($cipher), $cipher);
    $payload = $old->encrypt('from before the rotation');

    $rotated = new Encrypter(Encrypter::generateKey($cipher), $cipher)->previousKeys([$old->getKey()]);

    expect($rotated->decrypt($payload))->toBe('from before the rotation')
        ->and($rotated->getAllKeys())->toBe([$rotated->getKey(), $old->getKey()])
        ->and($rotated->getPreviousKeys())->toBe([$old->getKey()])
        // Rotation only reaches backwards: the old key alone can't read what the new one wrote.
        ->and(fn () => new Encrypter($old->getKey(), $cipher)->decrypt($rotated->encrypt('new')))->toThrow(DecryptException::class);
})->with('ciphers');

it('resolves the encrypter from app.key, app.cipher and app.previous_keys, as the class and both contracts', function () {
    $old = Encrypter::generateKey('AES-256-CBC');
    $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    $this->app['config']->set('app.previous_keys', ['base64:'.base64_encode($old)]);

    $encrypter = $this->app->make('encrypter');

    expect($encrypter)->toBeInstanceOf(Encrypter::class)
        ->and($this->app->make(EncrypterContract::class))->toBe($encrypter)
        ->and($this->app->make(StringEncrypter::class))->toBe($encrypter)
        ->and($encrypter->getKey())->toBe(str_repeat('k', 32))
        ->and($encrypter->decrypt(new Encrypter($old)->encrypt('rotated')))->toBe('rotated');
});

it('refuses to build the encrypter without an app key', function () {
    $this->app['config']->set('app.key', null);

    expect(fn () => $this->app->make('encrypter'))->toThrow(MissingAppKeyException::class, 'No application encryption key has been specified.');
});
