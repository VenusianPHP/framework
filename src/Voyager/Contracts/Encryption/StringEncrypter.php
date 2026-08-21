<?php

namespace Voyager\Contracts\Encryption;

interface StringEncrypter
{
    /**
     * Encrypt a string without serialization.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \Voyager\Contracts\Encryption\EncryptException
     */
    public function encryptString(#[\SensitiveParameter] string $value);

    /**
     * Decrypt the given string without unserialization.
     *
     * @param  string  $payload
     * @return string
     *
     * @throws \Voyager\Contracts\Encryption\DecryptException
     */
    public function decryptString(string $payload);
}
