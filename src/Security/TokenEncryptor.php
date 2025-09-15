<?php

namespace App\Security;

class TokenEncryptor
{
    private string $key;

    public function __construct(string $appSecret)
    {
        // Derive a libsodium key from APP_SECRET
        $hash = sodium_crypto_generichash($appSecret, '', 32);
        $this->key = $hash;
    }

    public function encrypt(?string $plaintext): ?string
    {
        if (!$plaintext) return null;
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return base64_encode($nonce.$cipher);
    }

    public function decrypt(?string $encoded): ?string
    {
        if (!$encoded) return null;
        $bin = base64_decode($encoded, true);
        if (false === $bin) return null;
        $nonce = substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        return false === $plain ? null : $plain;
    }
}

