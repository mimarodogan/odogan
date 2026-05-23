<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Glossary;
use App\Services\Sanitizer;

/**
 * Admin → Mimari Sözlük yönetimi (Tier 7 — Architecture niche).
 */
final class GlossaryController
{
    private static function gate(): ?Response
    {
        if (!function_exists('feature') || !feature('glossary_enabled')) {
            return Response::notFound();
        }
        return null;
    }

    public function index(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        return view('admin.glossary.index', [
            'title' => 'Mimari Sözlük',
            'list'  => Glossary::all(),
        ]);
    }

    public function create(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        return view('admin.glossary.form', [
            'title' => 'Yeni Terim',
            'item'  => [
                'id' => null, 'term' => '', 'slug' => '', 'definition' => '',
                'category' => '', 'aliases' => '', 'references' => '', 'is_active' => 1,
            ],
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $item = Glossary::findById($id);
        if (!$item) return Response::notFound();
        return view('admin.glossary.form', [
            'title' => 'Terim Düzenle — ' . $item['term'],
            'item'  => $item,
        ]);
    }

    public function store(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $patch = self::validateInput($req, $err);
        if ($err) {
            flash('error', $err);
            return Response::redirect(url('/admin/sozluk/yeni'));
        }
        $id = Glossary::create($patch);
        flash('success', 'Terim eklendi.');
        return Response::redirect(url('/admin/sozluk/' . $id . '/duzenle'));
    }

    public function update(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $item = Glossary::findById($id);
        if (!$item) return Response::notFound();

        $patch = self::validateInput($req, $err);
        if ($err) {
            flash('error', $err);
            return Response::redirect(url('/admin/sozluk/' . $id . '/duzenle'));
        }
        // slug değişti mi
        $newSlug = trim((string) $req->input('slug', ''));
        if ($newSlug !== '' && $newSlug !== $item['slug']) {
            $patch['slug'] = $newSlug;
        }
        Glossary::update($id, $patch);
        flash('success', 'Terim güncellendi.');
        return Response::redirect(url('/admin/sozluk/' . $id . '/duzenle'));
    }

    public function destroy(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        Glossary::delete($id);
        flash('success', 'Terim silindi.');
        return Response::redirect(url('/admin/sozluk'));
    }

    /**
     * AI Sözlük Taslak Üreteci — talep-üzerine.
     * Editör panelinde "AI ile doldur" butonu bu endpoint'i POST eder; servis
     * Claude API çağırır, çıktıyı JSON döndürür. Front-end form alanlarını
     * yanıttan doldurur.
     */
    public function aiDraft(Request $req): Response
    {
        if ($g = self::gate()) return $g;

        if (!function_exists('feature') || !feature('glossary_ai_enabled')) {
            return Response::json(['ok' => false, 'message' => 'AI sözlük üreteci kapalı.'], 404);
        }
        if (!\App\Services\AiGlossaryService::isEnabled()) {
            return Response::json([
                'ok' => false,
                'message' => 'AI servisi etkin değil veya Claude API anahtarı tanımlı değil.',
            ], 400);
        }

        try {
            $term    = trim((string) $req->input('term', ''));
            $ctx     = trim((string) $req->input('context', ''));
            $depth   = trim((string) $req->input('depth', 'orta'));
            if (mb_strlen($term) < 2) {
                return Response::json(['ok' => false, 'message' => 'Terim en az 2 karakter olmalı.'], 400);
            }

            $draft = \App\Services\AiGlossaryService::draft($term, $ctx, $depth);
            return Response::json(['ok' => true, 'draft' => $draft]);
        } catch (\Throwable $e) {
            if (class_exists(\App\Services\Logger::class)) {
                \App\Services\Logger::error('admin.glossary.ai_draft.exception', [
                    'msg' => $e->getMessage(),
                ], 'editorial');
            }
            return Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function validateInput(Request $req, ?string &$err): array
    {
        $err = null;
        $term = trim((string) $req->input('term', ''));
        if (mb_strlen($term) < 2) {
            $err = 'Terim en az 2 karakter olmalı.';
            return [];
        }
        $def = trim((string) $req->input('definition', ''));
        if (mb_strlen($def) < 10) {
            $err = 'Tanım en az 10 karakter olmalı.';
            return [];
        }
        return [
            'term'        => mb_substr($term, 0, 180),
            'definition'  => Sanitizer::clean($def),
            'category'    => mb_substr(trim((string) $req->input('category', '')), 0, 80),
            'aliases'     => mb_substr(trim((string) $req->input('aliases', '')), 0, 500),
            'references'  => self::normalizeReferences($req->input('references', null)),
            'is_active'   => ((int) $req->input('is_active', 1)) === 1 ? 1 : 0,
        ];
    }

    /**
     * Kaynaklar alanını JSON dizisine normalize eder.
     *
     * Kabul edilen girdiler:
     *  - dizi:  [['text' => 'Kitap', 'url' => 'https://...'], ...]  → JSON
     *  - string (legacy):  'A; B; https://x'                        → JSON dizisine çevrilir
     *  - boş:   ''
     *
     * Görünüm tarafı hem JSON hem de eski `;` formatını okuyabilir
     * (glossary-term.php geriye dönük decode yapar) — yine de yazarken
     * her zaman JSON üretiyoruz ki yeni düzen tutarlı olsun.
     */
    private static function normalizeReferences(mixed $raw): string
    {
        $rows = [];

        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $text = mb_substr(trim((string) ($row['text'] ?? '')), 0, 2000);
                $url  = mb_substr(trim((string) ($row['url']  ?? '')), 0, 500);
                if ($text === '' && $url === '') {
                    continue;
                }
                // Salt URL girilmişse, görüntü için text alanına da koy
                if ($text === '' && $url !== '') {
                    $text = $url;
                }
                // URL şeması zorunlu — değilse boşalt
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $url = '';
                }
                $rows[] = ['text' => $text, 'url' => $url];
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            // Legacy: noktalı virgülle ayrılmış string. Her parça URL ise link,
            // değilse düz metin.
            foreach (array_filter(array_map('trim', explode(';', $raw))) as $part) {
                $isUrl = (bool) preg_match('#^https?://#i', $part);
                $rows[] = [
                    'text' => mb_substr($part, 0, 2000),
                    'url'  => $isUrl ? mb_substr($part, 0, 500) : '',
                ];
            }
        }

        if ($rows === []) {
            return '';
        }
        return (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
