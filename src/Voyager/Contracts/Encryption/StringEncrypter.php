<?php

namespace Voyager\Contracts\Encryption;

interface StringEncrypter
{
    /**
     * Encrypt a string without serialization.
     *
     * @throws EncryptException
     */
    public function encryptString(#[\SensitiveParameter] string $value): string;

    /**
     * Decrypt a string without unserialization.
     *
     * @throws DecryptException
     */
    public function decryptString(string $payload): string;
}
