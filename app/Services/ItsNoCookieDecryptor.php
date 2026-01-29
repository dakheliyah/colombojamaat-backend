<?php

namespace App\Services;

class ItsNoCookieDecryptor
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Decrypt the its_no cookie value (AES-256-CBC).
     *
     * Expects: URL-encoded Base64 string; after decode, first 16 bytes = IV, remainder = ciphertext.
     * Key: 32-byte key from 64-char hex in config.
     *
     * @return string|null Plain ITS number or null on failure.
     */
    public function decrypt(string $raw): ?string
    {
        $key = $this->getKey();
        if ($key === null || strlen($key) !== 32) {
            return null;
        }

        $decoded = urldecode($raw);
        $binary = base64_decode($decoded, true);
        if ($binary === false || strlen($binary) <= 16) {
            return null;
        }

        $iv = substr($binary, 0, 16);
        $ciphertext = substr($binary, 16);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plain === false) {
            return null;
        }

        $value = trim($plain);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function getKey(): ?string
    {
        $hex = config('auth_session.decryption_key');
        if (! is_string($hex) || strlen($hex) !== 64 || ! ctype_xdigit($hex)) {
            return null;
        }

        return hex2bin($hex) ?: null;
    }
}
