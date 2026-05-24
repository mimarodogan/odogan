<?php
declare(strict_types=1);

namespace App\Services;

/**
 * TOTP (RFC 6238) — Time-Based One-Time Password.
 *
 * Standart:
 *  - 6 haneli kod
 *  - 30 saniye periyot
 *  - SHA-1 HMAC
 *  - ±1 step (60sn) drift tolerance
 *  - 160-bit (20-byte) secret, Base32 encoded
 *
 * Google Authenticator, Microsoft Authenticator, 1Password, Authy, FreeOTP
 * gibi tüm yaygın uygulamalarla uyumludur.
 */
final class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALGO = 'sha1';
    private const SECRET_BYTES = 20;

    // Base32 (RFC 4648)
    private const B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * 10 adet 10-karakter (hex) tek kullanımlık recovery code üretir.
     * @return string[]
     */
    public static function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5))); // 10 char hex
        }
        return $codes;
    }

    /**
     * Authenticator app'in tanıyacağı otpauth:// URL.
     * Bunu QR kod olarak göstermek için kullanırız (veya manuel entry).
     */
    public static function otpauthUrl(string $secret, string $accountName, string $issuer): string
    {
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
            'algorithm' => 'SHA1',
        ];
        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($accountName)
            . '?' . http_build_query($params);
    }

    /**
     * Belirtilen secret + zaman penceresi için 6-haneli TOTP üret.
     */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();
        $counter = intdiv($ts, self::PERIOD);
        return self::hotp($secret, $counter);
    }

    /**
     * Kullanıcının girdiği kodu doğrular.
     * ±1 step tolerance — kullanıcının saati 30sn drift'li olabilir.
     * hash_equals timing-safe karşılaştırma kullanır.
     */
    public static function verify(string $secret, string $userInput, ?int $timestamp = null): bool
    {
        $input = (string) preg_replace('/\D/', '', $userInput);
        if (strlen($input) !== self::DIGITS) {
            return false;
        }
        $ts = $timestamp ?? time();
        $counter = intdiv($ts, self::PERIOD);
        for ($d = -1; $d <= 1; $d++) {
            $expected = self::hotp($secret, $counter + $d);
            if (hash_equals($expected, $input)) {
                return true;
            }
        }
        return false;
    }

    /**
     * HOTP (RFC 4226) — counter-based; TOTP'nin temeli.
     */
    private static function hotp(string $secretB32, int $counter): string
    {
        $key = self::base32Decode($secretB32);
        // 64-bit big-endian counter
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac(self::ALGO, $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        );
        $modulo = 10 ** self::DIGITS;
        return str_pad((string) ($code % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    // ─── Base32 (RFC 4648) ───────────────────────────────────────

    public static function base32Encode(string $bytes): string
    {
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $out .= self::B32_ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $out .= self::B32_ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }
        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = (string) preg_replace('/[^A-Z2-7]/', '', strtoupper($encoded));
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        $len = strlen($encoded);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::B32_ALPHABET, $encoded[$i]);
            if ($pos === false) continue;
            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $out;
    }

    /**
     * Recovery code'ları görüntüleme için formatla: "ABCD-EFGHIJ"
     * @param string[] $codes
     * @return string[]
     */
    public static function formatRecoveryCodes(array $codes): array
    {
        return array_map(function ($c) {
            $c = strtoupper((string) $c);
            return substr($c, 0, 4) . '-' . substr($c, 4);
        }, $codes);
    }

    /**
     * Recovery code consume — bulunursa listeden çıkarılmış halini döner.
     * @param string[] $list
     * @return string[]|null  Yeni liste (consume sonrası) ya da null (bulunamadı)
     */
    public static function consumeRecoveryCode(array $list, string $input): ?array
    {
        $clean = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $input));
        if ($clean === '') return null;

        $remaining = [];
        $consumed = false;
        foreach ($list as $code) {
            $codeClean = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', (string) $code));
            if (!$consumed && hash_equals($codeClean, $clean)) {
                $consumed = true;
                continue; // skip = consume
            }
            $remaining[] = $code;
        }
        return $consumed ? $remaining : null;
    }
}
