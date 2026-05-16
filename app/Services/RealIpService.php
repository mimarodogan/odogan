<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Trusted-proxy aware client IP çözümleyicisi.
 *
 * Mantık:
 *  - REMOTE_ADDR güvenilen proxy listesinde ise → forwarded header'lar onurlandırılır
 *      öncelik: CF-Connecting-IP (Cloudflare) → X-Forwarded-For (left-to-right, ilk
 *      non-trusted IP)
 *  - Aksi halde → sadece REMOTE_ADDR (header spoofing'e bağışık)
 *
 * Trusted proxy listesi `config/security.php`'den (`trusted_proxies` anahtarı)
 * okunur. Boşsa: hiçbir proxy güvenilmez, REMOTE_ADDR kullanılır.
 *
 * Bu sayede `X-Forwarded-For: 1.2.3.4` gönderen bir saldırgan asla
 * gerçek IP'sini gizleyemez.
 */
final class RealIpService
{
    public static function ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        // Proxy değilse → direkt
        if (!self::isTrustedProxy($remote)) {
            return $remote;
        }

        // Cloudflare: CF-Connecting-IP daima orijinal client
        $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }

        // X-Forwarded-For zinciri: client, proxy1, proxy2, ...
        // Soldan sağa: ilk non-trusted IP gerçek client'tır.
        $fwd = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $chain = array_map('trim', explode(',', $fwd));
            foreach ($chain as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP) && !self::isTrustedProxy($candidate)) {
                    return $candidate;
                }
            }
        }

        // Header yok veya tüm zincir trusted — fallback REMOTE_ADDR
        return $remote;
    }

    public static function isTrustedProxy(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach (self::trustedProxies() as $cidr) {
            if (self::cidrMatch($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    public static function trustedProxies(): array
    {
        $cfg = Config::get('security.trusted_proxies');
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * CIDR notation içeren bir aralığa IP eşleşmesi.
     * Hem IPv4 hem IPv6 destekler.
     */
    public static function cidrMatch(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $maskStr] = explode('/', $cidr, 2);
        $mask = (int) $maskStr;

        $isIpV6 = str_contains($ip, ':');
        $subIsV6 = str_contains($subnet, ':');
        if ($isIpV6 !== $subIsV6) {
            return false;
        }

        if ($isIpV6) {
            return self::cidrMatchV6($ip, $subnet, $mask);
        }
        return self::cidrMatchV4($ip, $subnet, $mask);
    }

    private static function cidrMatchV4(string $ip, string $subnet, int $mask): bool
    {
        $ipLong = ip2long($ip);
        $subLong = ip2long($subnet);
        if ($ipLong === false || $subLong === false) {
            return false;
        }
        if ($mask <= 0) {
            return true;
        }
        if ($mask >= 32) {
            return $ipLong === $subLong;
        }
        $maskLong = (~((1 << (32 - $mask)) - 1)) & 0xFFFFFFFF;
        return (($ipLong & $maskLong) === ($subLong & $maskLong));
    }

    private static function cidrMatchV6(string $ip, string $subnet, int $mask): bool
    {
        $a = @inet_pton($ip);
        $b = @inet_pton($subnet);
        if ($a === false || $b === false) {
            return false;
        }
        $bytes = intdiv($mask, 8);
        $bits  = $mask % 8;
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        if ($bits > 0) {
            $byteA = ord($a[$bytes]);
            $byteB = ord($b[$bytes]);
            $m = (0xFF << (8 - $bits)) & 0xFF;
            if (($byteA & $m) !== ($byteB & $m)) {
                return false;
            }
        }
        return true;
    }
}
