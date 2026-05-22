<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Profile JSON schema (Schema.org Person uyumlu).
 *
 * {
 *   "headline": "string",
 *   "bio": "string (markdown)",
 *   "location": "string",
 *   "expertise": ["..."],            // -> Person.knowsAbout
 *   "languages": [{"code","name","level"}],   // -> Person.knowsLanguage
 *   "education": [{"institution","degree","field","year_start","year_end"}], // -> alumniOf
 *   "experience": [{"company","role","url","year_start","year_end","current"}], // -> worksFor (current → @id cross-link)
 *   "social": {"website","twitter","linkedin","github","youtube"},           // -> sameAs
 *   "profiles": ["https://..."]                                              // -> sameAs (serbest doğrulama profilleri)
 * }
 */
final class ProfileService
{
    public const SOCIAL_KEYS = ['website', 'twitter', 'linkedin', 'github', 'youtube', 'mastodon'];

    public static function defaults(): array
    {
        return [
            'headline' => '',
            'bio' => '',
            'location' => '',
            'expertise' => [],
            'languages' => [],
            'education' => [],
            'experience' => [],
            'certificates' => [],
            'social' => array_fill_keys(self::SOCIAL_KEYS, ''),
            'profiles' => [],
        ];
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    public static function validate(array $input): array
    {
        $errors = [];
        $out = self::defaults();

        $out['headline'] = self::trimStr($input['headline'] ?? '', 160);
        $out['bio'] = self::trimStr($input['bio'] ?? '', 4000);
        $out['location'] = self::trimStr($input['location'] ?? '', 120);

        $out['expertise'] = self::cleanList((array) ($input['expertise'] ?? []), 60, 25);

        foreach ((array) ($input['languages'] ?? []) as $i => $lang) {
            $code = self::trimStr($lang['code'] ?? '', 10);
            $name = self::trimStr($lang['name'] ?? '', 60);
            $level = self::trimStr($lang['level'] ?? '', 30);
            if ($name === '' && $code === '') {
                continue;
            }
            $out['languages'][] = [
                'code' => mb_strtolower($code),
                'name' => $name,
                'level' => $level,
            ];
        }

        foreach ((array) ($input['education'] ?? []) as $i => $edu) {
            $inst = self::trimStr($edu['institution'] ?? '', 160);
            if ($inst === '') {
                continue;
            }
            $out['education'][] = [
                'institution' => $inst,
                'degree' => self::trimStr($edu['degree'] ?? '', 120),
                'field' => self::trimStr($edu['field'] ?? '', 120),
                'year_start' => self::yearOrNull($edu['year_start'] ?? null),
                'year_end' => self::yearOrNull($edu['year_end'] ?? null),
            ];
        }

        foreach ((array) ($input['certificates'] ?? []) as $cert) {
            $name = self::trimStr($cert['name'] ?? '', 160);
            if ($name === '') {
                continue;
            }
            $url = trim((string) ($cert['url'] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $url = '';
            }
            $out['certificates'][] = [
                'name' => $name,
                'issuer' => self::trimStr($cert['issuer'] ?? '', 160),
                'year' => self::yearOrNull($cert['year'] ?? null),
                'url' => $url,
            ];
        }

        foreach ((array) ($input['experience'] ?? []) as $i => $exp) {
            $company = self::trimStr($exp['company'] ?? '', 160);
            if ($company === '') {
                continue;
            }
            $current = !empty($exp['current']);
            // Kurum web adresi — doluysa Person.worksFor @id cross-link kurar
            // (örn. https://onalti.com.tr → @id https://onalti.com.tr/#organization).
            $url = trim((string) ($exp['url'] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $url = '';
            }
            $out['experience'][] = [
                'company' => $company,
                'role' => self::trimStr($exp['role'] ?? '', 160),
                'url' => $url,
                'year_start' => self::yearOrNull($exp['year_start'] ?? null),
                'year_end' => $current ? null : self::yearOrNull($exp['year_end'] ?? null),
                'current' => $current,
            ];
        }

        $social = (array) ($input['social'] ?? []);
        foreach (self::SOCIAL_KEYS as $key) {
            $val = trim((string) ($social[$key] ?? ''));
            if ($val === '') {
                $out['social'][$key] = '';
                continue;
            }
            if ($key !== 'website' && !preg_match('#^https?://#i', $val)) {
                $val = 'https://' . ltrim($val, '/');
            }
            if (filter_var($val, FILTER_VALIDATE_URL) === false) {
                $errors["social.$key"] = 'Geçerli bir URL girin.';
                continue;
            }
            $out['social'][$key] = $val;
        }

        // Serbest doğrulama profilleri (sameAs) — kişinin başka sitelerdeki
        // sayfaları (örn. şirket ekip sayfası, ORCID, Wikipedia). Geçersiz/boş
        // satırlar sessizce atılır; en fazla 15 benzersiz URL.
        foreach ((array) ($input['profiles'] ?? []) as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $p)) {
                $p = 'https://' . ltrim($p, '/');
            }
            if (filter_var($p, FILTER_VALIDATE_URL) !== false && !in_array($p, $out['profiles'], true)) {
                $out['profiles'][] = $p;
            }
            if (count($out['profiles']) >= 15) {
                break;
            }
        }

        return [$out, $errors];
    }

    public static function encode(array $profile): string
    {
        return (string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(?string $json): array
    {
        $data = $json ? json_decode($json, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        return array_replace_recursive(self::defaults(), $data);
    }

    private static function trimStr(mixed $v, int $max): string
    {
        $s = trim((string) $v);
        return mb_substr($s, 0, $max);
    }

    private static function cleanList(array $list, int $maxLen, int $maxItems): array
    {
        $out = [];
        foreach ($list as $item) {
            $s = self::trimStr($item, $maxLen);
            if ($s !== '' && !in_array($s, $out, true)) {
                $out[] = $s;
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return $out;
    }

    private static function yearOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $y = (int) $v;
        if ($y < 1900 || $y > (int) date('Y') + 5) {
            return null;
        }
        return $y;
    }
}
