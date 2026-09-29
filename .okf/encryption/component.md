---
type: Module
title: Encryption
description: Authenticated AES encryption behind app('encrypter'); key and cipher from app config, previous keys for decrypt; signs serialized closures.
resource: src/Voyager/Encryption/Encrypter.php
tags: [encryption, aes, keys]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: encrypter
    resource: src/Voyager/Encryption/Encrypter.php
    title: Encrypter
  - id: provider
    resource: src/Voyager/Encryption/EncryptionServiceProvider.php
    title: EncryptionServiceProvider
---

# Overview

`app('encrypter')` is `Encrypter`; aliases the `Encrypter` and `StringEncrypter` contracts. Built from `app.key` (`base64:` prefix decoded) and `app.cipher`; `app.previous_keys` are tried when decrypting. No key throws `MissingAppKeyException`.[^provider]

Ciphers: `aes-128-cbc`, `aes-256-cbc` (HMAC-SHA256 over iv + value), `aes-128-gcm`, `aes-256-gcm` (tag). Nothing is decrypted before it verifies. API: `encrypt` / `decrypt` (serialize by default), `encryptString` / `decryptString`, `getKey`, `getAllKeys`, `getPreviousKeys`, `previousKeys()`, static `supported`, `generateKey`, `appearsEncrypted`. Failures throw `EncryptException` / `DecryptException`.[^encrypter]

The provider also sets `SerializableClosure`'s secret to the app key, so workers only run closures this app signed.[^provider]

No async: one encrypt or decrypt is openssl work far below a worker round trip.

[^encrypter]: Encrypter
[^provider]: EncryptionServiceProvider
