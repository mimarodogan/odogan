<?php
declare(strict_types=1);

namespace App\Services;

/**
 * HTML allow-list sanitizer. Used by:
 *   - MarkdownService for parsed Markdown output
 *   - PanelPostController when body_format='html' (WYSIWYG editor)
 */
final class Sanitizer
{
    public const ALLOWED_TAGS = [
        'p','br','strong','em','b','i','u','s','del','ins','sub','sup',
        'h1','h2','h3','h4','h5','h6',
        'ul','ol','li',
        'blockquote','hr','code','pre','kbd','samp','mark','span',
        'a','img','picture','source',
        'table','thead','tbody','tr','th','td',
        'figure','figcaption','div',
        // Footnote (dipnot) sistemine açılan eklemeler
        'aside','section',
    ];

    public const ALLOWED_ATTRS = [
        'a' => ['href','title','rel','target','class','style','id'],
        'img' => ['src','alt','title','width','height','loading','decoding','srcset','sizes','class','style'],
        'picture' => ['class','style'],
        'source' => ['srcset','sizes','type','media'],
        'th' => ['align','colspan','rowspan','class','style'],
        'td' => ['align','colspan','rowspan','class','style'],
        'table' => ['class','style'],
        'thead' => ['class','style'],
        'tbody' => ['class','style'],
        'tr' => ['class','style'],
        'code' => ['class','style'],
        'pre' => ['class','style'],
        'span' => ['class','style'],
        'div' => ['class','style'],
        'figure' => ['class','style'],
        'figcaption' => ['class','style'],
        'p' => ['class','style'],
        'h1' => ['class','style','id'],
        'h2' => ['class','style','id'],
        'h3' => ['class','style','id'],
        'h4' => ['class','style','id'],
        'h5' => ['class','style','id'],
        'h6' => ['class','style','id'],
        'ul' => ['class','style'],
        'ol' => ['class','style','id'],
        'li' => ['class','style','id'],
        'blockquote' => ['class','style'],
        'strong' => ['class','style'],
        'em' => ['class','style'],
        'mark' => ['class','style'],
        'sup' => ['class','style','id'],
        'sub' => ['class','style','id'],
        'aside' => ['class','style','id','aria-labelledby'],
        'section' => ['class','style','id','aria-labelledby'],
    ];

    /**
     * CSS properties allowed inside style="…". Values are validated with a
     * conservative regex — no url(), no expression(), no @import, no
     * !important, no escapes/comments.
     */
    private const ALLOWED_CSS_PROPS = [
        'color',
        'background-color',
        'text-align',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'text-decoration',
        'line-height',
        'letter-spacing',
        'vertical-align',
    ];

    public static function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if ($wrapper instanceof \DOMElement) {
            self::walk($wrapper);
            $out = '';
            foreach ($wrapper->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }
            return trim($out);
        }
        return '';
    }

    private static function walk(\DOMElement $node): void
    {
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::stripOrUnwrap($child, $tag);
                    continue;
                }
                self::filterAttrs($child, $tag);
                if ($tag === 'a') {
                    self::hardenAnchor($child);
                }
                self::walk($child);
            }
        }
    }

    private static function stripOrUnwrap(\DOMElement $node, string $tag): void
    {
        $drop = ['script','style','iframe','object','embed','form','input','button',
                 'link','meta','svg','math','base','frame','frameset'];
        if (in_array($tag, $drop, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }
        $textNode = $node->ownerDocument->createTextNode($node->textContent ?? '');
        $node->parentNode?->replaceChild($textNode, $node);
    }

    private static function filterAttrs(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];
        $attrs = iterator_to_array($el->attributes);
        foreach ($attrs as $attr) {
            $name = strtolower($attr->nodeName);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }
            if (in_array($name, ['href','src','srcset'], true)) {
                $value = trim((string) $attr->nodeValue);
                if (!self::isSafeUrl($value, $name === 'srcset')) {
                    $el->removeAttribute($attr->nodeName);
                }
                continue;
            }
            if ($name === 'style') {
                $clean = self::sanitizeStyle((string) $attr->nodeValue);
                if ($clean === '') {
                    $el->removeAttribute($attr->nodeName);
                } else {
                    $el->setAttribute('style', $clean);
                }
            }
        }
    }

    /**
     * Allow only a small whitelist of CSS properties with conservative values.
     * Rejects url(), expression(), !important, comments, escapes, @rules.
     */
    private static function sanitizeStyle(string $css): string
    {
        // Block obvious attack surfaces up front.
        $lc = strtolower($css);
        foreach (['url(', 'expression(', '@import', '@charset', '!important', '/*', '*/', '\\'] as $bad) {
            if (str_contains($lc, $bad)) {
                return '';
            }
        }
        $out = [];
        foreach (explode(';', $css) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || !str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (!in_array($prop, self::ALLOWED_CSS_PROPS, true)) {
                continue;
            }
            if (!self::isSafeCssValue($prop, $value)) {
                continue;
            }
            $out[] = $prop . ':' . $value;
        }
        return implode(';', $out);
    }

    private static function isSafeCssValue(string $prop, string $value): bool
    {
        if ($value === '' || mb_strlen($value) > 60) {
            return false;
        }
        // Bytes only — no smuggled control chars.
        if (preg_match('/[^\x20-\x7E\xC0-\xFF]/', $value)) {
            return false;
        }
        switch ($prop) {
            case 'color':
            case 'background-color':
                return (bool) preg_match(
                    '/^(#[0-9a-f]{3}|#[0-9a-f]{6}|#[0-9a-f]{8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(?:0|1|0?\.\d+)\s*\)|transparent|inherit|currentcolor|black|white|red|green|blue|yellow|orange|purple|pink|gray|grey|cyan|magenta|brown|navy|teal|lime|olive|maroon|silver|gold|aqua|fuchsia)$/i',
                    $value
                );
            case 'text-align':
                return in_array(strtolower($value), ['left','right','center','justify','start','end'], true);
            case 'font-family':
                // Only generic families & quoted-safe names with letters/spaces.
                return (bool) preg_match('/^[a-z0-9\s,\-"\'.]+$/i', $value);
            case 'font-size':
                return (bool) preg_match('/^(\d{1,3}(\.\d{1,2})?(px|pt|em|rem|%)|small|medium|large|x-small|x-large|xx-large|smaller|larger)$/i', $value);
            case 'font-weight':
                return (bool) preg_match('/^(\d{3}|normal|bold|lighter|bolder)$/i', $value);
            case 'font-style':
                return in_array(strtolower($value), ['normal','italic','oblique'], true);
            case 'text-decoration':
                return (bool) preg_match('/^(none|underline|overline|line-through)(\s+(none|underline|overline|line-through))*$/i', $value);
            case 'line-height':
                return (bool) preg_match('/^(\d{1,2}(\.\d{1,2})?|normal)$/', $value);
            case 'letter-spacing':
                return (bool) preg_match('/^(-?\d{1,2}(\.\d{1,2})?(px|em|rem)|normal)$/i', $value);
            case 'vertical-align':
                return in_array(strtolower($value), ['baseline','sub','super','top','middle','bottom','text-top','text-bottom'], true);
        }
        return false;
    }

    public static function isSafeUrl(string $url, bool $isSrcset = false): bool
    {
        if ($url === '' || $url === '#') {
            return false;
        }
        if ($isSrcset) {
            // srcset: "url 320w, url 768w" — every URL must be safe
            foreach (preg_split('/\s*,\s*/', $url) as $entry) {
                $u = trim((string) preg_replace('/\s+\d+[wx]\s*$/', '', $entry));
                if (!self::isSafeUrl($u)) {
                    return false;
                }
            }
            return true;
        }
        $lower = strtolower($url);
        if (str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'vbscript:')) {
            return false;
        }
        return (bool) preg_match('#^(https?:|//|/|\#|mailto:|tel:)#i', $url);
    }

    private static function hardenAnchor(\DOMElement $a): void
    {
        $href = (string) $a->getAttribute('href');
        if (preg_match('#^https?://#i', $href)) {
            $tokens = preg_split('/\s+/', trim($a->getAttribute('rel') . ' noopener noreferrer ugc')) ?: [];
            $tokens = array_values(array_unique(array_filter($tokens)));
            $a->setAttribute('rel', implode(' ', $tokens));
            if (!$a->hasAttribute('target')) {
                $a->setAttribute('target', '_blank');
            }
        }
    }
}
