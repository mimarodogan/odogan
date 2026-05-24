<?php
declare(strict_types=1);

if (!function_exists('picture')) {
    /**
     * Render a responsive <picture> with AVIF + WebP sources and a JPEG fallback.
     *
     * @param array{path?:string, variants?:array<int,array{webp:string,avif?:string,w:int,h:int}>}|null $media
     */
    function picture(?array $media, string $alt = '', array $opts = []): string
    {
        if (!$media || empty($media['path'])) {
            return '';
        }
        $variants = $media['variants'] ?? [];
        if (!$variants && !empty($media['variants_json'])) {
            $variants = (array) json_decode((string) $media['variants_json'], true);
        }
        ksort($variants);

        $loading = (string) ($opts['loading'] ?? 'lazy');
        $sizes = (string) ($opts['sizes'] ?? '(max-width: 768px) 100vw, 768px');
        $class = (string) ($opts['class'] ?? '');
        $fetchpriority = (string) ($opts['fetchpriority'] ?? '');  // 'high' for LCP
        // LCP image için fetchpriority="high" verilmişse, loading default'unu eager yap
        if ($fetchpriority === 'high' && !isset($opts['loading'])) {
            $loading = 'eager';
        }

        $avifSet = [];
        $webpSet = [];
        foreach ($variants as $w => $v) {
            if (!empty($v['avif'])) {
                $avifSet[] = url($v['avif']) . ' ' . (int) $w . 'w';
            }
            if (!empty($v['webp'])) {
                $webpSet[] = url($v['webp']) . ' ' . (int) $w . 'w';
            }
        }
        $largest = end($variants) ?: null;
        $fallbackW = $largest['w'] ?? (int) ($media['width'] ?? 0);
        $fallbackH = $largest['h'] ?? (int) ($media['height'] ?? 0);

        // BlurHash placeholder — feature aktif ve hash mevcutsa img'a data-blurhash bas
        $blurhash = '';
        if (function_exists('feature') && feature('blurhash_enabled')) {
            $blurhash = (string) ($media['blurhash'] ?? '');
        }

        // Image class — blurhash varsa "has-blurhash" eklenir, JS decode için işaretler
        $imgClass = trim($class . ($blurhash !== '' ? ' has-blurhash' : ''));

        $html = '<picture>';
        if ($avifSet) {
            $html .= '<source type="image/avif" srcset="' . esc(implode(', ', $avifSet)) . '" sizes="' . esc($sizes) . '">';
        }
        if ($webpSet) {
            $html .= '<source type="image/webp" srcset="' . esc(implode(', ', $webpSet)) . '" sizes="' . esc($sizes) . '">';
        }
        $html .= '<img src="' . esc(url((string) $media['path'])) . '"'
            . ' alt="' . esc($alt !== '' ? $alt : (string) ($media['alt'] ?? '')) . '"'
            . ($fallbackW ? ' width="' . (int) $fallbackW . '"' : '')
            . ($fallbackH ? ' height="' . (int) $fallbackH . '"' : '')
            . ' loading="' . esc($loading) . '" decoding="async"'
            . ($fetchpriority !== '' ? ' fetchpriority="' . esc($fetchpriority) . '"' : '')
            . ($imgClass !== '' ? ' class="' . esc($imgClass) . '"' : '')
            . ($blurhash !== '' ? ' data-blurhash="' . esc($blurhash) . '"' : '')
            . '>';
        $html .= '</picture>';
        return $html;
    }
}

if (!function_exists('picture_from_path')) {
    /**
     * Render a responsive <picture> from a raw image path string,
     * using MediaService's naming convention:
     *   {base}-320.webp  {base}-768.webp  {base}-1280.webp
     *
     * Use this when you only have cover_image (a plain path) and no full
     * media record with variants_json. Use picture() when the full record is available.
     *
     * @param array{loading?:string,fetchpriority?:string,sizes?:string,width?:int,height?:int,class?:string} $opts
     */
    function picture_from_path(string $path, string $alt = '', array $opts = []): string
    {
        if ($path === '') {
            return '';
        }
        // cover_image bazen göreceli ('uploads/...'), bazen mutlak URL ('https://...') saklanır.
        // url() yalnızca göreceli yollara uygulanmalı; mutlak URL'ler olduğu gibi kullanılır.
        $isAbsolute = (bool) preg_match('#^https?://#i', $path);
        $toUrl      = static fn(string $p): string => $isAbsolute ? $p : url($p);

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $base = substr($path, 0, -(strlen($ext) + 1));

        $loading      = (string) ($opts['loading'] ?? 'lazy');
        $fetchpriority = (string) ($opts['fetchpriority'] ?? '');
        // LCP görsel: fetchpriority="high" verilmişse loading otomatik eager olur
        if ($fetchpriority === 'high' && !isset($opts['loading'])) {
            $loading = 'eager';
        }
        $sizesAttr = (string) ($opts['sizes'] ?? '(max-width: 768px) 100vw, 800px');
        $class     = (string) ($opts['class'] ?? '');

        // WebP srcset — MediaService::SIZES = [320, 768, 1280]
        $webpSet = [];
        foreach ([320, 768, 1280] as $w) {
            $webpSet[] = esc($toUrl("{$base}-{$w}.webp")) . " {$w}w";
        }

        // AVIF auto-detection — yalnızca göreceli (yerel) path'lerde diskte mevcut
        // varyantları taramaya çalışırız. Mutlak URL'lerde dosya kontrolü
        // anlamlı değil (uzak host); böyle durumlarda AVIF source eklemeyiz.
        $avifSet = [];
        if (!$isAbsolute) {
            $publicRoot = \App\Core\Config::publicRoot();
            foreach ([320, 768, 1280] as $w) {
                $avifRel = "{$base}-{$w}.avif";
                if (is_file($publicRoot . '/' . ltrim($avifRel, '/'))) {
                    $avifSet[] = esc($toUrl($avifRel)) . " {$w}w";
                }
            }
        }

        $imgAttrs  = ' src="' . esc($toUrl($path)) . '"';
        $imgAttrs .= ' alt="' . esc($alt) . '"';
        if (!empty($opts['width']))  $imgAttrs .= ' width="' . (int) $opts['width'] . '"';
        if (!empty($opts['height'])) $imgAttrs .= ' height="' . (int) $opts['height'] . '"';
        $imgAttrs .= ' loading="' . esc($loading) . '" decoding="async"';
        if ($fetchpriority !== '') $imgAttrs .= ' fetchpriority="' . esc($fetchpriority) . '"';
        if ($class !== '')         $imgAttrs .= ' class="' . esc($class) . '"';

        $html = '<picture>';
        // AVIF önce — daha iyi sıkıştırma, browser ilk eşleşeni seçer.
        if ($avifSet) {
            $html .= '<source type="image/avif" srcset="' . implode(', ', $avifSet) . '" sizes="' . esc($sizesAttr) . '">';
        }
        $html .= '<source type="image/webp" srcset="' . implode(', ', $webpSet) . '" sizes="' . esc($sizesAttr) . '">';
        $html .= '<img' . $imgAttrs . '></picture>';
        return $html;
    }
}

if (!function_exists('og_image')) {
    /**
     * Pick a reasonable OG/Twitter image URL from a media row or a raw path.
     */
    function og_image(mixed $media): ?string
    {
        if (is_string($media) && $media !== '') {
            return preg_match('#^https?://#i', $media) ? $media : url($media);
        }
        if (!is_array($media)) {
            return null;
        }
        $variants = $media['variants'] ?? [];
        if (!$variants && !empty($media['variants_json'])) {
            $variants = (array) json_decode((string) $media['variants_json'], true);
        }
        ksort($variants);
        $largest = end($variants);
        if ($largest && !empty($largest['webp'])) {
            return url((string) $largest['webp']);
        }
        if (!empty($media['path'])) {
            return url((string) $media['path']);
        }
        return null;
    }
}
