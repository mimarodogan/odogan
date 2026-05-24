<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Dipnot listesi normalize + render.
 *
 * Stored shape:
 *   [{"n": 1, "text": "Açıklama metni", "url": "https://opsiyonel.com"}]
 *
 * Body içinde [^1] [^2] markerları MarkdownService::render() ile
 * <sup><a href="#fn-1" id="fnref-1">1</a></sup> haline gelir; yazı sonuna
 * <aside class="footnotes"> ile kaynaklar listesi append edilir.
 */
final class FootnoteService
{
    public const MAX_ITEMS = 100;
    public const MAX_TEXT = 2000;
    public const MAX_URL = 500;

    /**
     * @param mixed $input  Array, JSON string veya repeater form input
     * @return array<int,array{n:int,text:string,url:string}>
     */
    public static function normalize(mixed $input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($input)) {
            return [];
        }
        $out = [];
        $n = 1;
        foreach ($input as $row) {
            if (!is_array($row)) continue;
            $text = trim((string) ($row['text'] ?? $row['t'] ?? ''));
            $url  = trim((string) ($row['url']  ?? $row['u'] ?? ''));
            if ($text === '') continue;
            // URL validate (opsiyonel)
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = '';
            }
            $out[] = [
                'n'    => $n,
                'text' => mb_substr($text, 0, self::MAX_TEXT),
                'url'  => mb_substr($url, 0, self::MAX_URL),
            ];
            $n++;
            if (count($out) >= self::MAX_ITEMS) break;
        }
        return $out;
    }

    public static function encode(array $items): string
    {
        return (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<int,array{n:int,text:string,url:string}>
     */
    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $data = json_decode($json, true);
        return self::normalize($data);
    }

    /**
     * Body içindeki [^N] markerları sup link ile değiştirir.
     * Sadece footnote listesinde tanımlı N'leri işler — aksi halde dokunulmaz.
     */
    public static function replaceMarkers(string $html, array $footnotes): string
    {
        if (!$footnotes) return $html;
        $validN = array_column($footnotes, 'n');
        return (string) preg_replace_callback(
            '/\[\^(\d+)\]/',
            static function ($m) use ($validN) {
                $n = (int) $m[1];
                if (!in_array($n, $validN, true)) {
                    return $m[0]; // dokunma
                }
                return sprintf(
                    '<sup class="footnote-ref"><a href="#fn-%d" id="fnref-%d" title="Dipnot %d">%d</a></sup>',
                    $n, $n, $n, $n
                );
            },
            $html
        );
    }

    /**
     * Yazı sonu kaynak listesi HTML'i. Render edilmiş body'nin sonuna eklenir.
     */
    public static function renderList(array $footnotes): string
    {
        if (!$footnotes) return '';
        $items = '';
        foreach ($footnotes as $fn) {
            $text = esc((string) $fn['text']);
            $url  = (string) ($fn['url'] ?? '');
            if ($url !== '') {
                $text .= sprintf(
                    ' <a href="%s" rel="noopener noreferrer external" target="_blank" title="Kaynağa git">↗</a>',
                    esc($url)
                );
            }
            $items .= sprintf(
                '<li id="fn-%d"><span class="fn-num">%d.</span> <span class="fn-text">%s</span> <a href="#fnref-%d" class="fn-back" title="Yazıya geri dön" aria-label="Geri dön">↩</a></li>',
                (int) $fn['n'], (int) $fn['n'], $text, (int) $fn['n']
            );
        }
        return '<aside class="footnotes" id="footnotes" aria-labelledby="footnotes-title">'
             . '<h2 id="footnotes-title">Kaynaklar</h2>'
             . '<ol class="footnotes-list">' . $items . '</ol>'
             . '</aside>';
    }
}
