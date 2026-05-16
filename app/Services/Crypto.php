<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Symmetric secret-box encryption around libsodium (PHP 8.2 native).
 *
 * Used for at-rest secrets stored in DB (e.g. SMTP password in the
 * `settings` table). Key comes from APP_KEY in .env — must be a base64
 * encoded 32-byte value. Generate with:
 *
 *   openssl rand -base64 32
 *
 * Ciphertext format:  base64( nonce(24) || boxed )
 * Plaintext returned by decrypt() — or null on tamper/wrong key/legacy
 * plaintext value, so callers can transparently migrate old rows.
 */
final class Crypto
{
    /** Sentinel so encrypted blobs are distinguishable from raw strings. */
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * Returns null when:
     *   • value is not in our `enc:v1:` envelope (caller may treat as legacy plaintext)
     *   • base64 is corrupt
     *   • MAC fails (wrong key / tampered ciphertext)
     */
    public static function decrypt(string $value): ?string
    {
        if (!self::isEncrypted($value)) {
            return null;
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $key = self::key();
        } catch (\Throwable) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        return $plain === false ? null : $plain;
    }

    /**
     * True iff $value uses our envelope. Helps callers decide whether to
     * decrypt (encrypted) or treat as legacy plaintext (not yet migrated).
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Migration-friendly read: if encrypted, decrypt; otherwise return as-is.
     * Returns empty string when value is null/empty so callers stay simple.
     */
    public static function decryptIfEncrypted(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!self::isEncrypted($value)) {
            return $value; // legacy plaintext — caller may re-encrypt on next save
        }
        $plain = self::decrypt($value);
        return $plain ?? '';
    }

    private static function key(): string
    {
        $key = (string) Config::get('APP_KEY', '');
        if ($key === '') {
            throw new \RuntimeException(
                'APP_KEY .env içinde tanımlı değil. Üretmek için: openssl rand -base64 32'
            );
        }
        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException(
                'APP_KEY base64 ile çözülemedi veya ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES
                . ' byte uzunluğunda değil. Üretmek için: openssl rand -base64 32'
            );
        }
        return $decoded;
    }
}
