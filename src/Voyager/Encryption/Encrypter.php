<?php

namespace Voyager\Encryption;

use Voyager\Contracts\Encryption\DecryptException;
use Voyager\Contracts\Encryption\EncryptException;

/**
 * Authenticated symmetric encryption over openssl.
 *
 * A CBC cipher carries a separate HMAC; a GCM cipher authenticates itself and carries a tag
 * instead. Either way nothing is decrypted before it is verified.
 */
class Encrypter
{
    /**
     * Key length in bytes, and whether the cipher authenticates itself.
     *
     * @var array<string, array{size: int, aead: bool}>
     */
    private const array SUPPORTED = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    private readonly string $key;

    private readonly string $cipher;

    public function __construct(string $key, string $cipher = 'AES-256-CBC')
    {
        if (! static::supported($key, $cipher)) {
            throw new EncryptException(
                'Unsupported cipher or incorrect key length. Supported ciphers are: '
                .implode(', ', array_keys(self::SUPPORTED)).'.'
            );
        }

        $this->key = $key;
        $this->cipher = $cipher;
    }

    /**
     * Is this key the right length for this cipher?
     */
    public static function supported(string $key, string $cipher): bool
    {
        $spec = self::SUPPORTED[strtolower($cipher)] ?? null;

        return ! is_null($spec) && strlen($key) === $spec['size'];
    }

    /**
     * A fresh key of the right length for the cipher.
     */
    public static function generateKey(string $cipher): string
    {
        return random_bytes(self::SUPPORTED[strtolower($cipher)]['size'] ?? 32);
    }

    /**
     * Does this look like something encrypt() produced?
     */
    public static function appearsEncrypted(string $contents): bool
    {
        $payload = json_decode(base64_decode($contents, true) ?: '', true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /**
     * @throws EncryptException
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(strtolower($this->cipher)) ?: 0);
        $tag = '';

        $encrypted = $this->aead()
            ? openssl_encrypt($serialize ? serialize($value) : $value, strtolower($this->cipher), $this->key, 0, $iv, $tag)
            : openssl_encrypt($serialize ? serialize($value) : $value, strtolower($this->cipher), $this->key, 0, $iv);

        if ($encrypted === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        $iv = base64_encode($iv);
        $tag = base64_encode($tag);

        // a CBC payload is only as trustworthy as the mac over it; a GCM payload has its own tag
        $mac = $this->aead() ? '' : $this->hash($iv, $encrypted);

        $json = json_encode([
            'iv' => $iv,
            'value' => $encrypted,
            'mac' => $mac,
            'tag' => $tag,
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * @throws DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = $this->validPayload($payload);

        $iv = base64_decode($payload['iv']);
        $tag = empty($payload['tag']) ? null : base64_decode($payload['tag']);

        $decrypted = openssl_decrypt(
            $payload['value'], strtolower($this->cipher), $this->key, 0, $iv, $tag ?? '', ''
        );

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Read the payload and prove it is ours before anything is decrypted.
     *
     * @return array{iv: string, value: string, mac: string, tag: string}
     * @throws DecryptException
     */
    private function validPayload(string $payload): array
    {
        $decoded = json_decode(base64_decode($payload, true) ?: '', true);

        if (! is_array($decoded) || ! isset($decoded['iv'], $decoded['value'], $decoded['mac'])) {
            throw new DecryptException('The payload is invalid.');
        }

        if (strlen(base64_decode($decoded['iv'], true) ?: '') !== openssl_cipher_iv_length(strtolower($this->cipher))) {
            throw new DecryptException('The payload is invalid.');
        }

        if (! $this->aead() && ! hash_equals($this->hash($decoded['iv'], $decoded['value']), $decoded['mac'])) {
            throw new DecryptException('The MAC is invalid.');
        }

        return $decoded + ['tag' => ''];
    }

    /**
     * The HMAC a non-AEAD payload is checked against.
     */
    private function hash(string $iv, string $value): string
    {
        return hash_hmac('sha256', $iv.$value, $this->key);
    }

    private function aead(): bool
    {
        return self::SUPPORTED[strtolower($this->cipher)]['aead'];
    }
}
