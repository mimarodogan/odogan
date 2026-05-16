<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Cache\CacheManager;
use App\Core\Database;

/**
 * Mail Şablonları (Tier 6) — sistemin gönderdiği tüm maillerin admin'de düzenlenebilir kaynağı.
 *
 * `MailService::sendTemplate(string $key, string $to, array $vars)` ile kullanılır.
 * Yer tutucu: {user_name}, {site_name}, vb. — render sırasında değiştirilir.
 */
final class MailTemplate
{
    /**
     * Anahtarla şablon getir — cache'lenir.
     */
    public static function findByKey(string $key): ?array
    {
        try {
            $cache = CacheManager::driver();
            return $cache->remember(
                'mailtpl:' . $key,
                3600,
                static function () use ($key) {
                    return Database::instance()->fetch(
                        'SELECT * FROM mail_templates WHERE key_name = :k AND is_active = 1 LIMIT 1',
                        [':k' => $key]
                    );
                },
                ['mailtpl']
            );
        } catch (\Throwable) {
            try {
                return Database::instance()->fetch(
                    'SELECT * FROM mail_templates WHERE key_name = :k AND is_active = 1 LIMIT 1',
                    [':k' => $key]
                );
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM mail_templates WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function all(): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT id, key_name, label, subject, is_active, updated_at
                 FROM mail_templates
                 ORDER BY key_name ASC'
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function update(int $id, array $patch): int
    {
        $n = Database::instance()->update('mail_templates', $patch, 'id = :wid', [':wid' => $id]);
        try {
            CacheManager::driver()->invalidateTags(['mailtpl']);
        } catch (\Throwable) {}
        return $n;
    }

    /**
     * Şablonu değişkenlerle render et: {key} → değer.
     * Subject + body birlikte döner.
     *
     * @param array<string,string|int> $vars
     * @return array{subject:string, body:string} | null
     */
    public static function render(string $key, array $vars): ?array
    {
        $tpl = self::findByKey($key);
        if (!$tpl) {
            return null;
        }

        $subject = self::substitute((string) $tpl['subject'], $vars);
        $body    = self::substitute((string) $tpl['body_html'], $vars);

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Curly-brace substitution: "Merhaba {user_name}" + ['user_name' => 'Ali']
     */
    public static function substitute(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $k => $v) {
            $replacements['{' . $k . '}'] = is_scalar($v) ? (string) $v : '';
        }
        return strtr($template, $replacements);
    }
}
