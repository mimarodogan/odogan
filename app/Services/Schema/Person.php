<?php
declare(strict_types=1);

namespace App\Services\Schema;

/**
 * Builds a Schema.org Person object from a user row + profile_json.
 * Profile fields map to:
 *   headline    -> jobTitle
 *   bio         -> description
 *   location    -> address (PostalAddress.addressLocality)
 *   expertise   -> knowsAbout
 *   languages   -> knowsLanguage
 *   education   -> alumniOf
 *   experience  -> worksFor (current) + workExample
 *   social      -> sameAs
 */
final class Person
{
    public static function build(array $user, array $profile, ?string $url = null): array
    {
        $node = [
            '@type' => 'Person',
            '@id' => $url ? $url . '#person' : null,
            'name' => (string) $user['name'],
        ];

        if (!empty($user['avatar'])) {
            $node['image'] = $user['avatar'];
        }
        if ($url) {
            $node['url'] = $url;
        }
        if (!empty($profile['headline'])) {
            $node['jobTitle'] = (string) $profile['headline'];
        }
        if (!empty($profile['bio'])) {
            $node['description'] = mb_substr((string) $profile['bio'], 0, 500);
        }
        if (!empty($profile['location'])) {
            $node['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => (string) $profile['location'],
            ];
        }
        if (!empty($profile['expertise'])) {
            $node['knowsAbout'] = array_values($profile['expertise']);
        }

        $node['knowsLanguage'] = self::languages($profile['languages'] ?? []);
        $node['alumniOf'] = self::alumni($profile['education'] ?? []);
        $current = self::currentJob($profile['experience'] ?? []);
        if ($current) {
            $node['worksFor'] = $current;
        }
        $sameAs = self::sameAs($profile['social'] ?? []);
        if ($sameAs) {
            $node['sameAs'] = $sameAs;
        }

        return self::clean($node);
    }

    private static function languages(array $list): array
    {
        $out = [];
        foreach ($list as $l) {
            $name = trim((string) ($l['name'] ?? $l['code'] ?? ''));
            if ($name === '') {
                continue;
            }
            $node = ['@type' => 'Language', 'name' => $name];
            if (!empty($l['code'])) {
                $node['alternateName'] = (string) $l['code'];
            }
            $out[] = $node;
        }
        return $out;
    }

    private static function alumni(array $list): array
    {
        $out = [];
        foreach ($list as $e) {
            $institution = trim((string) ($e['institution'] ?? ''));
            if ($institution === '') {
                continue;
            }
            $org = ['@type' => 'EducationalOrganization', 'name' => $institution];
            if (!empty($e['degree']) || !empty($e['field'])) {
                $org['description'] = trim(($e['degree'] ?? '') . ' — ' . ($e['field'] ?? ''), ' —');
            }
            $out[] = $org;
        }
        return $out;
    }

    private static function currentJob(array $list): ?array
    {
        foreach ($list as $job) {
            if (!empty($job['current']) && !empty($job['company'])) {
                $node = ['@type' => 'Organization', 'name' => (string) $job['company']];
                if (!empty($job['role'])) {
                    $node['description'] = (string) $job['role'];
                }
                return $node;
            }
        }
        return null;
    }

    private static function sameAs(array $social): array
    {
        $out = [];
        foreach ($social as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $out[] = $url;
            }
        }
        return array_values(array_unique($out));
    }

    private static function clean(array $node): array
    {
        return array_filter($node, static fn($v) => $v !== null && $v !== '' && $v !== []);
    }
}
