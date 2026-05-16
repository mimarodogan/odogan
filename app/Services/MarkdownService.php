<?php
declare(strict_types=1);

namespace App\Services;

use Parsedown;

/**
 * Markdown -> sanitized HTML, plus helpers for plaintext and reading time.
 * The actual HTML allow-list lives in App\Services\Sanitizer so that
 * WYSIWYG-saved HTML can share the same defenses.
 */
final class MarkdownService
{
    public static function toHtml(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }
        $pd = new Parsedown();
        $pd->setSafeMode(true);
        $pd->setMarkupEscaped(true);
        $pd->setBreaksEnabled(false);
        $html = (string) $pd->text($markdown);
        return Sanitizer::clean($html);
    }

    /**
     * Sanitize body that already arrived as HTML (WYSIWYG editor).
     */
    public static function fromHtml(string $html): string
    {
        return Sanitizer::clean($html);
    }

    /**
     * Auto-detects markdown vs html by post['body_format'] flag.
     *
     * footnotes_enabled aktif + post.footnotes_json doluysa:
     *  - Body içinde [^N] markerları sup link'e dönüşür
     *  - Yazı sonuna <aside class="footnotes"> ile kaynak listesi append edilir
     */
    public static function render(array $post): string
    {
        $format = (string) ($post['body_format'] ?? 'markdown');
        $html = $format === 'html'
            ? self::fromHtml((string) ($post['body'] ?? ''))
            : self::toHtml((string) ($post['body'] ?? ''));

        // Footnote enrichment — feature flag default false
        if (function_exists('feature') && feature('footnotes_enabled')) {
            $footnotes = FootnoteService::decode($post['footnotes_json'] ?? null);
            if ($footnotes) {
                $html = FootnoteService::replaceMarkers($html, $footnotes);
                $html .= FootnoteService::renderList($footnotes);
            }
        }

        // AutoGlossaryLink — yazıda geçen mimari terimlerden sözlüğe otomatik link
        // (feature flag içeride kontrol edilir; off ise no-op)
        try {
            $html = AutoGlossaryLink::apply($html);
        } catch (\Throwable) {
            // DOMDocument hatası vb. — orijinal HTML dön, sayfa kırılmasın
        }

        return $html;
    }

    public static function plain(string $source, int $maxLen = 280, string $format = 'markdown'): string
    {
        $html = $format === 'html' ? self::fromHtml($source) : self::toHtml($source);
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen - 1) . '…';
        }
        return $text;
    }

    public static function readingMinutes(string $source, int $wpm = 200, string $format = 'markdown'): int
    {
        $html = $format === 'html' ? self::fromHtml($source) : self::toHtml($source);
        $words = preg_split('/\s+/u', strip_tags($html)) ?: [];
        $count = count(array_filter($words));
        return max(1, (int) ceil($count / max(1, $wpm)));
    }
}
