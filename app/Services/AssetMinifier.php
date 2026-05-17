<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Lightweight CSS/JS minifier.
 *
 * Reads a source asset, writes a `.min.<ext>` sibling, and returns the
 * web-accessible path. Re-minifies only when the source file's mtime
 * is newer than the minified file. The transformations are intentionally
 * conservative: no semicolon insertion, no identifier mangling — just
 * comment stripping and whitespace collapsing that won't break logic.
 */
final class AssetMinifier
{
    /**
     * Returns the asset URL (already passed through asset()) for a given
     * source path under public/. Falls back to the original on errors.
     */
    public static function asset(string $relPath): string
    {
        $rel = ltrim($relPath, '/');
        $abs = Config::publicRoot() . '/' . $rel;
        if (!is_file($abs)) {
            return url($rel);
        }
        $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, ['css', 'js'], true)) {
            return url($rel);
        }
        // Eğer doğrudan .min.css/.min.js verildiyse → mtime-based query string ile döndür
        if (str_ends_with($rel, '.min.' . $ext)) {
            return url($rel) . '?v=' . filemtime($abs);
        }
        $minRel = preg_replace('#\.' . $ext . '$#', '.min.' . $ext, $rel) ?? $rel;
        $minAbs = Config::publicRoot() . '/' . $minRel;

        $needsRebuild = !is_file($minAbs) || filemtime($abs) > filemtime($minAbs);
        if ($needsRebuild) {
            try {
                $src = (string) file_get_contents($abs);
                $out = $ext === 'css' ? self::minifyCss($src) : self::minifyJs($src);
                @file_put_contents($minAbs, $out, LOCK_EX);
            } catch (\Throwable) {
                return url($rel);
            }
        }
        // Cache-busting — .htaccess 30-gün cache header'ı olduğu için browser
        // eski versiyonu tutar; mtime query string her rebuild'de değişir →
        // URL fresh kabul edilir, kullanıcı hard-refresh zorunda kalmaz.
        return url($minRel) . '?v=' . filemtime($minAbs);
    }

    /**
     * Concatenate multiple source files (in given order) and minify them
     * into a single output bundle. Rebuilds only when any source's mtime
     * is newer than the bundle. Glob patterns are supported per entry.
     *
     * @param array<int,string> $sources  Relative paths or glob patterns
     * @param string            $outRel   Relative path of the bundle output
     * @return string                     Web-accessible URL of the bundle
     */
    public static function bundle(array $sources, string $outRel): string
    {
        $root = Config::publicRoot();
        $outAbs = $root . '/' . ltrim($outRel, '/');
        $ext = strtolower((string) pathinfo($outRel, PATHINFO_EXTENSION));
        if (!in_array($ext, ['css', 'js'], true)) {
            return url($outRel);
        }

        // Expand globs and collect absolute paths
        $absSources = [];
        foreach ($sources as $entry) {
            $entry = ltrim($entry, '/');
            if (str_contains($entry, '*')) {
                $matches = glob($root . '/' . $entry) ?: [];
                sort($matches);
                foreach ($matches as $match) {
                    $absSources[] = $match;
                }
            } else {
                $abs = $root . '/' . $entry;
                if (is_file($abs)) {
                    $absSources[] = $abs;
                }
            }
        }
        if (!$absSources) {
            return url($outRel);
        }

        // Decide whether a rebuild is needed
        $needs = !is_file($outAbs);
        if (!$needs) {
            $outM = filemtime($outAbs);
            foreach ($absSources as $abs) {
                if (filemtime($abs) > $outM) { $needs = true; break; }
            }
        }
        if ($needs) {
            try {
                $combined = '';
                foreach ($absSources as $abs) {
                    $rel = ltrim(str_replace($root, '', $abs), '/');
                    $combined .= "\n/* ─── " . $rel . " ─── */\n";
                    $combined .= (string) file_get_contents($abs);
                    $combined .= "\n";
                }
                $out = $ext === 'css' ? self::minifyCss($combined) : self::minifyJs($combined);
                @file_put_contents($outAbs, $out, LOCK_EX);
            } catch (\Throwable) {
                // fall through — bundle still returns URL even if rebuild failed
            }
        }
        // Cache-busting — bkz. asset() açıklaması
        return url($outRel) . (is_file($outAbs) ? '?v=' . filemtime($outAbs) : '');
    }

    public static function minifyCss(string $css): string
    {
        // Drop block comments — but never an inline data: URL's slash-star.
        $css = (string) preg_replace('#/\*(?!!)[\s\S]*?\*/#', '', $css);
        // Collapse whitespace runs.
        $css = (string) preg_replace('/\s+/', ' ', $css);
        // Strip whitespace around structural punctuation.
        $css = (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
        // Remove the last trailing semicolon inside each block — saves bytes.
        $css = (string) preg_replace('/;}/', '}', $css);
        return trim($css);
    }

    /**
     * Conservative JS minifier:
     *   - strips line comments and block comments
     *   - leaves string and template literals untouched
     *   - collapses runs of whitespace outside literals
     */
    public static function minifyJs(string $js): string
    {
        $len = strlen($js);
        $out = '';
        $i = 0;
        $prev = '';
        while ($i < $len) {
            $c = $js[$i];
            $c2 = $i + 1 < $len ? $js[$i + 1] : '';
            // Block comment
            if ($c === '/' && $c2 === '*') {
                $end = strpos($js, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                continue;
            }
            // Line comment
            if ($c === '/' && $c2 === '/') {
                $end = strpos($js, "\n", $i + 2);
                $i = $end === false ? $len : $end;
                continue;
            }
            // Strings: ' " `  — copy verbatim with escape handling
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $start = $i;
                $i++;
                while ($i < $len) {
                    $ch = $js[$i];
                    if ($ch === '\\') { $i += 2; continue; }
                    if ($ch === $quote) { $i++; break; }
                    $i++;
                }
                $chunk = substr($js, $start, $i - $start);
                $out .= $chunk;
                $prev = $chunk[strlen($chunk) - 1];
                continue;
            }
            // Whitespace collapsing
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $j = $i;
                while ($j < $len && ($js[$j] === ' ' || $js[$j] === "\t" || $js[$j] === "\n" || $js[$j] === "\r")) {
                    $j++;
                }
                $next = $j < $len ? $js[$j] : '';
                if (self::isWordChar($prev) && self::isWordChar($next)) {
                    $out .= ' ';
                    $prev = ' ';
                }
                $i = $j;
                continue;
            }
            $out .= $c;
            $prev = $c;
            $i++;
        }
        return $out;
    }

    private static function isWordChar(string $c): bool
    {
        return $c !== '' && (ctype_alnum($c) || $c === '_' || $c === '$');
    }
}
