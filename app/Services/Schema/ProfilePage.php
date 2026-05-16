<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Core\Config;
use App\Models\Setting;

/**
 * ProfilePage — yazar profili sayfası için özel WebPage alt-tipi.
 *
 * Google Author Knowledge Panel adaylığı için kritik. mainEntity olarak
 * Person node'una @id ile bağlanır (Schema\Person::build() ayrıca üretir).
 */
final class ProfilePage
{
    public static function build(string $url, array $user, ?int $postCount = null, ?string $lastPublishedAt = null): array
    {
        $siteUrl = rtrim((string) Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');

        $node = [
            '@type'      => 'ProfilePage',
            '@id'        => $url . '#webpage',
            'url'        => $url,
            'name'       => (string) $user['name'],
            'description' => mb_substr((string) ($user['bio'] ?? ''), 0, 280),
            'inLanguage' => (string) Setting::get('site_locale', Config::get('APP_LOCALE', 'tr'), 'general'),
            'mainEntity' => ['@id' => $url . '#person'],
        ];

        if ($siteUrl !== '') {
            $node['isPartOf'] = ['@id' => $siteUrl . '#website'];
        }

        // Profil oluşturma + son güncelleme — ProfilePage spec
        if (!empty($user['created_at'])) {
            $ts = strtotime((string) $user['created_at']);
            if ($ts) {
                $node['dateCreated'] = date('c', $ts);
            }
        }
        if ($lastPublishedAt !== null) {
            $ts = strtotime($lastPublishedAt);
            if ($ts) {
                $node['dateModified'] = date('c', $ts);
            }
        }

        // Yazar istatistikleri
        if ($postCount !== null && $postCount > 0) {
            $node['mainContentOfPage'] = [
                '@type' => 'WebPageElement',
                'description' => sprintf('%d yayınlanmış yazı', $postCount),
            ];
            $node['interactionStatistic'] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/WriteAction',
                'userInteractionCount' => $postCount,
            ];
        }

        return array_filter($node, static fn($v) => $v !== null && $v !== '' && $v !== []);
    }
}
