<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;

/**
 * IndexNow key file endpoint.
 *
 * Spec: IndexNow protocol search engine'leri site ownership doğrulamak için
 * key dosyasını domain root'tan ister: https://example.com/{key}.txt
 * İçerik = aynı key string.
 *
 * Statik dosya yerine dynamic endpoint kullanmamızın sebebi: admin Settings'te
 * key değiştirildiğinde manuel dosya oluşturmak/silmek gerekmesin.
 */
final class IndexNowKeyController
{
    public function show(Request $req, string $token): Response
    {
        $token = preg_replace('/[^a-f0-9-]/i', '', $token);
        $expected = trim((string) Setting::get('indexnow_key', '', 'seo'));

        if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
            return Response::notFound();
        }

        return new Response($expected, 200, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }
}
