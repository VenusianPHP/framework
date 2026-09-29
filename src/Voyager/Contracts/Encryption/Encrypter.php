<?php

namespace Voyager\Contracts\Encryption;

interface Encrypter
{
    /**
     * @throws EncryptException
     */
    public function encrypt(#[\SensitiveParameter] mixed $value, bool $serialize = true): string;

    /**
     * @throws DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed;

    /** The key the encrypter encrypts with. */
    public function getKey(): string;

    /**
     * The current key, then every previous one.
     *
     * @return list<string>
     */
    public function getAllKeys(): array;

    /**
     * The keys decryption falls back on once the current one fails.
     *
     * @return list<string>
     */
    public function getPreviousKeys(): array;
}
