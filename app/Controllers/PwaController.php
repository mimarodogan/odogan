<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;

/**
 * PWA Controller (Tier 7 — Performance & PWA).
 *
 *  /manifest.webmanifest — uygulamayı "Ana ekrana ekle" için
 *  /sw.js                — Service Worker (offline cache + revalidate)
 */
final class PwaController
{
    public function manifest(Request $req): Response
    {
        if (!function_exists('feature') || !feature('pwa_enabled')) {
            return Response::notFound();
        }
        $siteName = (string) Setting::get('site_name', Config::get('APP_NAME', 'Odogan'));
        $shortName = mb_substr($siteName, 0, 12);
        $description = (string) Setting::get('site_description', 'Mimari ve mühendislik yayını');
        $themeColor = '#1F3A8A';   // cobalt
        $bgColor    = '#FAF7F2';   // bone

        $manifest = [
            'name'             => $siteName,
            'short_name'       => $shortName,
            'description'      => $description,
            'start_url'        => url('/'),
            'scope'            => url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'theme_color'      => $themeColor,
            'background_color' => $bgColor,
            'lang'             => 'tr',
            'icons' => [
                ['src' => url('/favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
                ['src' => url('/favicon.svg'), 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
                ['src' => url('/favicon.svg'), 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
            ],
            'categories' => ['news', 'lifestyle', 'magazine'],
        ];

        return new Response(
            (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type'  => 'application/manifest+json; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    public function serviceWorker(Request $req): Response
    {
        if (!function_exists('feature') || !feature('pwa_enabled')) {
            return Response::notFound();
        }
        // Service Worker root scope — JS dosyası
        $js = self::serviceWorkerJs();
        return new Response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * Minimal Service Worker — cache-first images, network-first HTML.
     */
    private static function serviceWorkerJs(): string
    {
        $version = 'odogan-v1-' . date('Ymd');
        return <<<JS
// Odogan Service Worker — Tier 7 PWA
// Strateji:
//   - HTML (yazı sayfaları): network-first, fallback cache (offline okuma)
//   - Asset (CSS/JS/img): cache-first, background revalidate

const CACHE_VERSION = '{$version}';
const CACHE_HTML = CACHE_VERSION + '-html';
const CACHE_ASSETS = CACHE_VERSION + '-assets';
const OFFLINE_URL = '/';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) {
                return !k.startsWith(CACHE_VERSION);
            }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);
    if (url.origin !== location.origin) return;
    if (url.pathname.startsWith('/panel') || url.pathname.startsWith('/admin') || url.pathname.startsWith('/editor')) return;
    if (url.pathname.startsWith('/api')) return;

    // HTML — network-first, cache fallback
    if (req.headers.get('accept') && req.headers.get('accept').indexOf('text/html') >= 0) {
        event.respondWith(
            fetch(req).then(function (resp) {
                var copy = resp.clone();
                caches.open(CACHE_HTML).then(function (c) { c.put(req, copy); });
                return resp;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || caches.match(OFFLINE_URL);
                });
            })
        );
        return;
    }

    // Assets — cache-first, background revalidate
    event.respondWith(
        caches.match(req).then(function (cached) {
            var network = fetch(req).then(function (resp) {
                if (resp && resp.status === 200 && resp.type === 'basic') {
                    var copy = resp.clone();
                    caches.open(CACHE_ASSETS).then(function (c) { c.put(req, copy); });
                }
                return resp;
            }).catch(function () { return cached; });
            return cached || network;
        })
    );
});
JS;
    }
}
