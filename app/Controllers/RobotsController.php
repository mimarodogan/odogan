<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

final class RobotsController
{
    public function index(Request $req): Response
    {
        $sitemap = url('/sitemap.xml');
        $env = (string) Config::get('APP_ENV', 'local');
        $allowAll = $env === 'production';
        $body  = "User-agent: *\n";
        $body .= $allowAll
            ? "Disallow: /panel/\n"
              . "Disallow: /editor/\n"
              . "Disallow: /admin/\n"
              . "Disallow: /giris\n"
              . "Disallow: /kayit\n"
              . "Disallow: /git/\n"                // affiliate / ref redirect'leri index'leme
              . "Disallow: /ara\n"                 // site içi arama
              . "Disallow: /sozluk?q=\n"           // sözlük arama sonuçları
              . "Disallow: /sozlesmeler/\n"        // hukuki / sözleşmeler
              . "Disallow: /kaydedilenler\n"       // kişisel LocalStorage listesi
              . "Disallow: /newsletter/onay/\n"    // token'lı double opt-in landing
              . "Disallow: /newsletter/cikis/\n"   // token'lı unsubscribe landing
              . "Disallow: /hesap-silindi\n"       // soft-delete teyit sayfası
              . "Disallow: /sifremi-unuttum\n"     // şifre kurtarma akışı
              . "Disallow: /sifre-sifirla/\n"      // token'lı şifre reset
              . "Disallow: /email-onay/\n"         // token'lı e-posta doğrulama
              . "Disallow: /*?ref=\n"              // ?ref= query string'li URL'ler
              . "Disallow: /*?utm_*\n"             // UTM tracking parametreleri
              . "Allow: /\n"
            : "Disallow: /\n";

        // AI arama motorları — açıkça izin ver (GEO: AI Overview, ChatGPT, Perplexity alıntı görünürlüğü)
        if ($allowAll) {
            $body .= "\n# AI Arama Motorları — içerik alıntılarına açık\n";
            $body .= "User-agent: GPTBot\nAllow: /\n\n";           // OpenAI ChatGPT web search
            $body .= "User-agent: OAI-SearchBot\nAllow: /\n\n";    // OpenAI search features
            $body .= "User-agent: ChatGPT-User\nAllow: /\n\n";     // ChatGPT browsing
            $body .= "User-agent: ClaudeBot\nAllow: /\n\n";        // Anthropic Claude
            $body .= "User-agent: anthropic-ai\nAllow: /\n\n";     // Anthropic training
            $body .= "User-agent: PerplexityBot\nAllow: /\n\n";    // Perplexity AI
            $body .= "User-agent: Bytespider\nAllow: /\n\n";       // ByteDance / TikTok AI
            $body .= "User-agent: cohere-ai\nAllow: /\n\n";        // Cohere
            $body .= "# CCBot (Common Crawl eğitim verisi) — isteğe bağlı, şimdilik açık\n";
            $body .= "User-agent: CCBot\nAllow: /\n";
        }

        $body .= "\nSitemap: " . $sitemap . "\n";
        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
