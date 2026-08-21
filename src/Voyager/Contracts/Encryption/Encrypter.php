<?php

namespace Voyager\Contracts\Encryption;

interface Encrypter
{
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws \Voyager\Contracts\Encryption\EncryptException
     */
    public function encrypt(#[\SensitiveParameter] mixed $value, bool $serialize = true);

    /**
     * Decrypt the given value.
     *
     * @param  string  $payload
     * @param  bool  $unserialize
     * @return mixed
     *
     * @throws \Voyager\Contracts\Encryption\DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true);

    /**
     * Get the encryption key that the encrypter is currently using.
     *
     * @return string
     */
    public function getKey();

    /**
     * Get the current encryption key and all previous encryption keys.
     *
     * @return array
     */
    public function getAllKeys();

    /**
     * Get the previous encryption keys.
     *
     * @return array
     */
    public function getPreviousKeys();
}
