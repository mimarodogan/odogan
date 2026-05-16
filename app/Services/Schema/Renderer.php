<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Core\Config;

/**
 * Collects multiple Schema.org nodes and emits a single JSON-LD
 * <script> tag using the @graph form. This keeps duplicated Person
 * references resolvable by Google's Rich Results parser.
 */
final class Renderer
{
    /** @var array<int,array> */
    private array $nodes = [];

    public function add(?array $node): self
    {
        if (is_array($node) && $node) {
            $this->nodes[] = $node;
        }
        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function emit(): string
    {
        if (!$this->nodes) {
            return '';
        }
        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $this->nodes,
        ];
        // Production'da minify (pretty print kapalı) — boyut %30 azalır.
        $env = (string) Config::get('APP_ENV', 'production');
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($env === 'local' || $env === 'development') {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = (string) json_encode($payload, $flags);
        // Script-tag breakout güvenliği.
        $json = str_replace('</', '<\/', $json);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Cache-aware emit. mtime-based key → post update edilince otomatik invalidate.
     * Boş key verilirse cache atlanır.
     *
     * @param string $cacheKey  Genelde "schema:post:{id}:{updated_at}" formatı
     * @param int    $ttlSec    Saniye cinsinden TTL (default 24h)
     */
    public function emitCached(string $cacheKey, int $ttlSec = 86400): string
    {
        if ($cacheKey === '') {
            return $this->emit();
        }
        try {
            $cache = \App\Core\Cache\CacheManager::driver();
            return $cache->remember($cacheKey, $ttlSec, fn() => $this->emit(), ['schema']);
        } catch (\Throwable) {
            return $this->emit();
        }
    }

    public static function siteOrganization(): array
    {
        $url = rtrim((string) \App\Models\Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');
        $name = (string) \App\Models\Setting::get('site_name', Config::get('APP_NAME', 'Otorite Yayin'));
        $legal = (string) \App\Models\Setting::get('org_legal_name', '', 'organization');

        $node = [
            '@type' => 'Organization',
            '@id'   => ($url ?: '') . '#org',
            'name'  => $name,
            'url'   => $url,
        ];

        if ($legal !== '' && $legal !== $name) {
            $node['legalName'] = $legal;
        }

        $description = (string) \App\Models\Setting::get('site_description', '');
        if ($description !== '') {
            $node['description'] = mb_substr($description, 0, 500);
        }

        // Logo
        $logo = (string) \App\Models\Setting::get('default_og_image', '', 'seo');
        if ($logo !== '') {
            $node['logo'] = [
                '@type' => 'ImageObject',
                '@id'   => ($url ?: '') . '#logo',
                'url'   => preg_match('#^https?://#i', $logo) ? $logo : ($url . '/' . ltrim($logo, '/')),
            ];
        }

        // Founding date — YYYY veya YYYY-MM-DD
        $founding = trim((string) \App\Models\Setting::get('org_founding_date', '', 'organization'));
        if ($founding !== '' && preg_match('/^\d{4}(-\d{2}-\d{2})?$/', $founding)) {
            $node['foundingDate'] = $founding;
        }

        // Founder
        $founder = trim((string) \App\Models\Setting::get('org_founder', '', 'organization'));
        if ($founder !== '') {
            $node['founder'] = ['@type' => 'Person', 'name' => $founder];
        }

        // Address
        $street  = trim((string) \App\Models\Setting::get('org_street_address', '', 'organization'));
        $city    = trim((string) \App\Models\Setting::get('org_city', '', 'organization'));
        $zip     = trim((string) \App\Models\Setting::get('org_postal_code', '', 'organization'));
        $country = trim((string) \App\Models\Setting::get('org_country', '', 'organization'));
        if ($street !== '' || $city !== '' || $zip !== '' || $country !== '') {
            $node['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $street ?: null,
                'addressLocality' => $city ?: null,
                'postalCode'      => $zip ?: null,
                'addressCountry'  => $country !== '' ? strtoupper($country) : null,
            ]);
        }

        // ContactPoint (email/phone)
        $email = trim((string) \App\Models\Setting::get('org_email', '', 'organization'));
        $phone = trim((string) \App\Models\Setting::get('org_phone', '', 'organization'));
        if ($email !== '' || $phone !== '') {
            $cp = ['@type' => 'ContactPoint', 'contactType' => 'editorial'];
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cp['email'] = $email;
            }
            if ($phone !== '') {
                $cp['telephone'] = $phone;
            }
            $cp['availableLanguage'] = ['Turkish'];
            $node['contactPoint'] = $cp;
        }

        // sameAs — sosyal hesaplar (Settings::social_*)
        $social = [];
        foreach (['twitter','linkedin','instagram','facebook','youtube'] as $platform) {
            $val = trim((string) \App\Models\Setting::get('social_' . $platform, '', 'social'));
            if ($val !== '' && preg_match('#^https?://#i', $val)) {
                $social[] = $val;
            }
        }
        if ($social) {
            $node['sameAs'] = array_values(array_unique($social));
        }

        return $node;
    }

    public static function siteWebsite(): array
    {
        $url = rtrim((string) \App\Models\Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');
        $node = [
            '@type' => 'WebSite',
            '@id' => ($url ?: '') . '#website',
            'name' => (string) \App\Models\Setting::get('site_name', Config::get('APP_NAME', 'Otorite Yayin')),
            'url' => $url,
            'inLanguage' => (string) \App\Models\Setting::get('site_locale', Config::get('APP_LOCALE', 'tr'), 'general'),
        ];
        if ($url !== '') {
            // Sitelinks search box potansiyeli için
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $url . '/ara?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }
        return $node;
    }
}
