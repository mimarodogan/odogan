<?php
declare(strict_types=1);

/**
 * Güvenlik ayarları.
 *
 * trusted_proxies: REMOTE_ADDR bu listedeyse, X-Forwarded-For ve
 * CF-Connecting-IP header'ları onurlandırılır. Aksi durumda sadece
 * REMOTE_ADDR kullanılır (header spoofing'e karşı bağışık).
 *
 * Cloudflare arkasındaysanız: https://www.cloudflare.com/ips-v4/
 * Listeyi periyodik güncelleyin (yıllık bir kez yeterli).
 *
 * Boş bırakırsanız, X-Forwarded-For TAMAMEN yok sayılır (sadece REMOTE_ADDR).
 * Bu en güvenli moddur — direkt internet expose oluyorsanız böyle bırakın.
 *
 * cidr formatı kabul edilir: "127.0.0.1", "10.0.0.0/8", "2400:cb00::/32"
 */

return [
    'trusted_proxies' => [
        // Localhost (development + dahili loopback)
        '127.0.0.1',
        '::1',

        // Cloudflare IPv4 (https://www.cloudflare.com/ips-v4/) — örnek, prod'da güncellenmeli
        // '173.245.48.0/20',
        // '103.21.244.0/22',
        // '103.22.200.0/22',
        // '103.31.4.0/22',
        // '141.101.64.0/18',
        // '108.162.192.0/18',
        // '190.93.240.0/20',
        // '188.114.96.0/20',
        // '197.234.240.0/22',
        // '198.41.128.0/17',
        // '162.158.0.0/15',
        // '104.16.0.0/13',
        // '104.24.0.0/14',
        // '172.64.0.0/13',
        // '131.0.72.0/22',

        // Cloudflare IPv6 (https://www.cloudflare.com/ips-v6/)
        // '2400:cb00::/32',
        // '2606:4700::/32',
        // '2803:f800::/32',
        // '2405:b500::/32',
        // '2405:8100::/32',
        // '2a06:98c0::/29',
        // '2c0f:f248::/32',
    ],

    'rate_limit' => [
        'login_ip'    => ['max' => 10, 'window' => 300],   // 10 / 5 dk per IP
        'login_email' => ['max' => 5,  'window' => 900],   // 5 / 15 dk per email
        'register_ip' => ['max' => 5,  'window' => 3600],  // 5 / saat per IP
        'comment_ip'  => ['max' => 6,  'window' => 600],   // 6 / 10 dk per IP
    ],
];
