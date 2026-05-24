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
        // H1: Pasifleri (onay bekleyenleri) ÜSTE koy → admin önce onları görür
        // ve onaylar/siler. Sonra aktifler alfabetik gelir.
        $all = Glossary::all();
        usort($all, static function ($a, $b) {
            $aa = (int) ($a['is_active'] ?? 0);
            $bb = (int) ($b['is_active'] ?? 0);
            if ($aa !== $bb) return $aa <=> $bb; // pasif (0) önce
            return strcasecmp((string) ($a['term'] ?? ''), (string) ($b['term'] ?? ''));
        });
        $pendingCount = 0;
        foreach ($all as $g2) {
            if (((int) ($g2['is_active'] ?? 0)) === 0) $pendingCount++;
        }
        return view('admin.glossary.index', [
            'title'         => 'Mimari Sözlük',
            'list'          => $all,
            'pending_count' => $pendingCount,
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

        // H3: validateInput kendi kaydını duplicate sayma
        $patch = self::validateInput($req, $err, $id);
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
     * H3: AJAX duplicate check — form term inputuna blur olunca çağrılır.
     * JSON: {ok: true, exists: bool, existing?: {id, term, slug, is_active}}.
     * Edit modunda mevcut kayıt dışlanır (exclude_id parametresi).
     */
    public function checkDuplicate(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $term = trim((string) $req->input('term', ''));
        if (mb_strlen($term) < 2) {
            return Response::json(['ok' => true, 'exists' => false]);
        }
        $excludeId = (int) $req->input('exclude_id', 0);
        $existing = Glossary::findByTermInsensitive($term, $excludeId > 0 ? $excludeId : null);
        if ($existing === null) {
            return Response::json(['ok' => true, 'exists' => false]);
        }
        return Response::json([
            'ok'       => true,
            'exists'   => true,
            'existing' => [
                'id'        => (int) $existing['id'],
                'term'      => (string) $existing['term'],
                'slug'      => (string) $existing['slug'],
                'is_active' => (int) ($existing['is_active'] ?? 0) === 1,
                'edit_url'  => url('/admin/sozluk/' . (int) $existing['id'] . '/duzenle'),
            ],
        ]);
    }

    /**
     * Q5/Q6: Tek bir terim için drift kontrolü — AI self-review.
     * POST /admin/sozluk/{id}/denetle
     */
    public function validateTerm(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $item = Glossary::findById($id);
        if (!$item) return Response::notFound();

        $contextType = (string) ($item['context_type'] ?? 'diger');
        try {
            $result = \App\Services\GlossaryValidationService::validate(
                (string) $item['term'],
                $contextType,
                (string) ($item['definition'] ?? '')
            );
        } catch (\Throwable $e) {
            flash('error', 'Denetim başarısız: ' . $e->getMessage());
            return Response::redirect(url('/admin/sozluk/' . $id . '/duzenle'));
        }

        Glossary::update($id, [
            'quality_score'       => $result['quality_score'],
            'drift_flag'          => $result['drift_flag'] ? 1 : 0,
            'drift_reason'        => $result['drift_reason'],
            'drift_suggested_fix' => $result['suggested_fix'],
            'drift_checked_at'    => $result['checked_at'],
        ]);
        if ($result['drift_flag']) {
            flash('error', '⚠ Bağlam kayması tespit edildi: ' . ($result['drift_reason'] ?? '—'));
        } else {
            flash('success', '✓ Bağlam doğru — kalite skoru: ' . ($result['quality_score'] ?? 'N/A') . '/100');
        }
        return Response::redirect(url('/admin/sozluk/' . $id . '/duzenle'));
    }

    /**
     * Q6: Tüm sözlük terimlerini toplu olarak denetim — drift bulunca işaretle.
     * POST /admin/sozluk/toplu-denetle
     * Synchronous: 26 terim sırayla (her biri ~3-5 sn × $0.001).
     */
    public function bulkValidate(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $list = Glossary::all();
        $checked = 0;
        $drifts = 0;
        $errors = 0;
        // 30 saniyelik PHP default time limit'ini kaldır (30 terim ~2 dk sürer)
        @set_time_limit(0);
        foreach ($list as $g2) {
            $id = (int) $g2['id'];
            $contextType = (string) ($g2['context_type'] ?? 'diger');
            try {
                $result = \App\Services\GlossaryValidationService::validate(
                    (string) $g2['term'],
                    $contextType,
                    (string) ($g2['definition'] ?? '')
                );
                Glossary::update($id, [
                    'quality_score'       => $result['quality_score'],
                    'drift_flag'          => $result['drift_flag'] ? 1 : 0,
                    'drift_reason'        => $result['drift_reason'],
                    'drift_suggested_fix' => $result['suggested_fix'],
                    'drift_checked_at'    => $result['checked_at'],
                ]);
                $checked++;
                if ($result['drift_flag']) $drifts++;
            } catch (\Throwable $e) {
                $errors++;
                if (class_exists(\App\Services\Logger::class)) {
                    \App\Services\Logger::warning('glossary.bulk_validate.error', [
                        'term' => $g2['term'] ?? '?',
                        'msg'  => $e->getMessage(),
                    ], 'editorial');
                }
            }
        }
        $msg = sprintf(
            'Denetim tamamlandı: %d terim incelendi · %d drift bulundu · %d hata.',
            $checked, $drifts, $errors
        );
        flash($drifts > 0 ? 'error' : 'success', $msg);
        return Response::redirect(url('/admin/sozluk'));
    }

    /**
     * H1: Hızlı aktivasyon toggle — listede "Onayla" butonu için.
     * AI ile üretilen taslaklar is_active=0 olarak gelir; admin tek tık ile
     * is_active=1 yapar → terim public sözlükte görünür hale gelir.
     */
    public function toggleActive(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $item = Glossary::findById($id);
        if (!$item) return Response::notFound();

        $newState = ((int) ($item['is_active'] ?? 0)) === 1 ? 0 : 1;
        Glossary::update($id, ['is_active' => $newState]);
        flash('success', $newState === 1
            ? '"' . $item['term'] . '" terimi onaylandı ve sözlükte yayınlandı.'
            : '"' . $item['term'] . '" terimi pasifleştirildi.');
        return Response::redirect(url('/admin/sozluk'));
    }

    /**
     * /admin/sozluk/{id}/autolink-debug — bu sözlük girdisinde
     * AutoLinkService hangi adayları skorladı, hangileri seçildi?
     */
    public function autoLinkDebug(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $item = Glossary::findById($id);
        if (!$item) return Response::notFound();

        $debug = \App\Services\AutoLinkService::debug(
            (string) $item['definition'],
            'glossary',
            (int) $item['id'],
            ['category' => (string) ($item['category'] ?? '')]
        );

        return view('admin.glossary.autolink-debug', [
            'title' => 'AutoLink Debug — ' . $item['term'],
            'item'  => $item,
            'debug' => $debug,
        ]);
    }

    /**
     * /admin/sozluk/toplu — toplu üretim formu + kuyruk durumu.
     */
    public function batchIndex(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $queue = [];
        $counts = ['pending' => 0, 'processing' => 0, 'done' => 0, 'error' => 0, 'skipped' => 0];
        try {
            $queue = \App\Core\Database::instance()->fetchAll(
                'SELECT * FROM glossary_ai_queue ORDER BY id DESC LIMIT 100'
            );
            $rows = \App\Core\Database::instance()->fetchAll(
                'SELECT status, COUNT(*) AS n FROM glossary_ai_queue GROUP BY status'
            );
            foreach ($rows as $r) {
                $counts[(string) $r['status']] = (int) $r['n'];
            }
        } catch (\Throwable) { /* ignore */ }

        return view('admin.glossary.batch', [
            'title'  => 'Sözlük — Toplu AI Üretim',
            'queue'  => $queue,
            'counts' => $counts,
        ]);
    }

    /**
     * POST /admin/sozluk/toplu — terim listesi yapıştırınca kuyruğa ekle.
     * Her satır = 1 terim. Yorum satırı: # ile başlar (atlanır).
     */
    public function batchEnqueue(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $raw = trim((string) $req->input('terms', ''));
        $depth = trim((string) $req->input('depth', 'orta'));
        if (!in_array($depth, ['kisa', 'orta', 'derin'], true)) $depth = 'orta';
        if ($raw === '') {
            flash('error', 'Terim listesi boş.');
            return Response::redirect(url('/admin/sozluk/toplu'));
        }

        $lines = preg_split('/\r?\n/', $raw) ?: [];
        $added = 0;
        $skipped = 0;
        $db = \App\Core\Database::instance();
        foreach ($lines as $line) {
            $term = trim($line);
            if ($term === '' || str_starts_with($term, '#')) continue;
            $term = mb_substr($term, 0, 180);
            if (mb_strlen($term) < 2) { $skipped++; continue; }
            try {
                $db->run(
                    'INSERT IGNORE INTO glossary_ai_queue (term, depth, status) VALUES (:t, :d, "pending")',
                    [':t' => $term, ':d' => $depth]
                );
                $added++;
            } catch (\Throwable) {
                $skipped++;
            }
        }
        flash('success', "$added terim kuyruğa eklendi (atlanan: $skipped). 'Sonraki işle' ile başlat.");
        return Response::redirect(url('/admin/sozluk/toplu'));
    }

    /**
     * POST /admin/sozluk/toplu/isle — kuyruktan bir sonraki "pending" terimi
     * işle (AI 6 chunk + kaydet). Tamamlanınca redirect → kullanıcı tekrar
     * tıklayarak sıradakini başlatır. 1 dakikadan uzun sürebilir.
     */
    public function batchProcessNext(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        if (!\App\Services\AiGlossaryService::isEnabled()) {
            flash('error', 'AI servisi etkin değil.');
            return Response::redirect(url('/admin/sozluk/toplu'));
        }
        $db = \App\Core\Database::instance();
        $row = null;
        try {
            $row = $db->fetch('SELECT * FROM glossary_ai_queue WHERE status = "pending" ORDER BY id ASC LIMIT 1');
        } catch (\Throwable) { /* ignore */ }
        if (!$row) {
            flash('info', 'Kuyruk boş ya da tüm terimler işlendi.');
            return Response::redirect(url('/admin/sozluk/toplu'));
        }
        $qid = (int) $row['id'];
        $term = (string) $row['term'];
        $depth = (string) $row['depth'];

        try {
            $db->run('UPDATE glossary_ai_queue SET status = "processing" WHERE id = :id', [':id' => $qid]);

            // 6 chunk üretim: outline + 5 chunk → birleştir
            $outline = \App\Services\AiGlossaryService::draftOutline($term, '', $depth);
            $combinedHtml = '';
            $term_meta = ['term' => $term, 'slug_hint' => '', 'category' => '', 'aliases' => []];
            $refs = []; $faqs = [];
            foreach (['chunk_1','chunk_2','chunk_3','chunk_4','chunk_5'] as $cid) {
                $data = \App\Services\AiGlossaryService::draftChunk($cid, $term, '', $depth, [], $outline);
                if (!empty($data['html'])) {
                    $combinedHtml .= ($combinedHtml === '' ? '' : "\n") . $data['html'];
                }
                if ($cid === 'chunk_1') {
                    $term_meta['term']      = (string) ($data['term']      ?? $term);
                    $term_meta['slug_hint'] = (string) ($data['slug_hint'] ?? '');
                    $term_meta['category']  = (string) ($data['category']  ?? '');
                    $term_meta['aliases']   = (array)  ($data['aliases']   ?? []);
                }
                if ($cid === 'chunk_5') {
                    $refs = (array) ($data['references'] ?? []);
                    $faqs = (array) ($data['faq']        ?? []);
                }
            }

            // Glossary'ye kaydet (is_active=0 — taslak, incele sonra aktif et)
            $insertData = [
                'term'       => $term_meta['term'],
                'slug'       => $term_meta['slug_hint'] !== '' ? $term_meta['slug_hint'] : '',
                'definition' => \App\Services\Sanitizer::clean($combinedHtml),
                'category'   => $term_meta['category'],
                'aliases'    => implode(', ', array_slice($term_meta['aliases'], 0, 15)),
                'references' => $refs !== [] ? (string) json_encode(
                    array_map(static fn($r) => ['text' => (string) $r['text'], 'url' => (string) $r['url']], $refs),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) : '',
                'faq_json'   => $faqs !== [] ? \App\Services\FaqService::encode($faqs) : '',
                'is_active'  => 0,
            ];
            $newId = \App\Models\Glossary::create($insertData);
            $db->run(
                'UPDATE glossary_ai_queue SET status = "done", created_glossary_id = :gid, processed_at = NOW() WHERE id = :id',
                [':gid' => $newId, ':id' => $qid]
            );
            flash('success', "'$term' üretildi → taslak olarak kaydedildi (id #$newId). Bir sonraki için tekrar tıklayın.");
        } catch (\Throwable $e) {
            $err = mb_substr($e->getMessage(), 0, 600);
            try {
                $db->run(
                    'UPDATE glossary_ai_queue SET status = "error", error_message = :err, processed_at = NOW() WHERE id = :id',
                    [':err' => $err, ':id' => $qid]
                );
            } catch (\Throwable) { /* ignore */ }
            flash('error', "'$term' üretilemedi: " . $err);
        }
        return Response::redirect(url('/admin/sozluk/toplu'));
    }

    /**
     * AI ile ilişkili terim önerileri — bir girdi sayfasından çağrılır,
     * "sözlüğü büyüt" aracı. Çıktı: [{term, category, short}, ...]
     */
    public function aiSuggestRelated(Request $req, array $args): Response
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
        $id = (int) ($args['id'] ?? 0);
        $item = Glossary::findById($id);
        if (!$item) return Response::json(['ok' => false, 'message' => 'Terim bulunamadı.'], 404);

        try {
            $suggestions = \App\Services\AiGlossaryService::suggestRelated(
                (string) $item['term'],
                (string) ($item['category'] ?? ''),
                6
            );
            return Response::json(['ok' => true, 'suggestions' => $suggestions]);
        } catch (\Throwable $e) {
            if (class_exists(\App\Services\Logger::class)) {
                \App\Services\Logger::error('admin.glossary.ai_suggest.exception', [
                    'msg' => $e->getMessage(),
                ], 'editorial');
            }
            return Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
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
            $chunkId = trim((string) $req->input('chunk', ''));
            // Q3: context_type — disambiguation hint
            $contextType = trim((string) $req->input('context_type', 'diger'));
            $allowedCtx = array_keys(\App\Services\GlossaryValidationService::CONTEXT_TYPES);
            if (!in_array($contextType, $allowedCtx, true)) {
                $contextType = 'diger';
            }
            if (mb_strlen($term) < 2) {
                return Response::json(['ok' => false, 'message' => 'Terim en az 2 karakter olmalı.'], 400);
            }

            // GELİŞTİRME modu: form mevcut bir girdiyi düzenliyorsa, current_*
            // alanları AI'a bağlam olarak geçer ve "sıfırdan yazma" yerine
            // "güçlendir" rubric'i devreye girer.
            $current = [
                'definition' => (string) $req->input('current_definition', ''),
                'category'   => (string) $req->input('current_category',   ''),
                'aliases'    => (string) $req->input('current_aliases',    ''),
                'references' => (string) $req->input('current_references', ''),
            ];

            // OUTLINE PRE-PASS: chunk=outline → küresel plan üret (3 çağrılık
            // akışın 1. adımı). Çıktısı sonraki 2 chunk'a bağlam olarak verilir.
            if ($chunkId === 'outline') {
                $outline = \App\Services\AiGlossaryService::draftOutline($term, $ctx, $depth, $current, $contextType);
                return Response::json(['ok' => true, 'chunk' => 'outline', 'data' => $outline]);
            }

            // PARÇALI üretim: chunk parametresi verilirse tek bir bölüm üretilir.
            // Client outline'ı outline_json parametresiyle gönderir (chunk_1, chunk_2).
            if ($chunkId !== '') {
                $outlineRaw = (string) $req->input('outline_json', '');
                $outline = [];
                if ($outlineRaw !== '') {
                    $dec = json_decode($outlineRaw, true);
                    if (is_array($dec)) $outline = $dec;
                }
                $data = \App\Services\AiGlossaryService::draftChunk($chunkId, $term, $ctx, $depth, $current, $outline, $contextType);
                return Response::json(['ok' => true, 'chunk' => $chunkId, 'data' => $data]);
            }

            // Legacy tek-çağrı (geriye dönük uyum)
            $draft = \App\Services\AiGlossaryService::draft($term, $ctx, $depth, $current);
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
    /**
     * @param int|null $existingId  Edit modunda kendi kaydını dışlamak için
     */
    private static function validateInput(Request $req, ?string &$err, ?int $existingId = null): array
    {
        $err = null;
        $term = trim((string) $req->input('term', ''));
        if (mb_strlen($term) < 2) {
            $err = 'Terim en az 2 karakter olmalı.';
            return [];
        }
        // H3: Duplicate kontrolü — JS bypass edilse bile server engelleme
        $dup = Glossary::findByTermInsensitive($term, $existingId);
        if ($dup !== null) {
            $err = '"' . $term . '" zaten kayıtlı (slug: ' . $dup['slug'] . '). '
                 . 'Mevcut kaydı düzenlemek için tüm terimler listesine git.';
            return [];
        }
        $def = trim((string) $req->input('definition', ''));
        if (mb_strlen($def) < 10) {
            $err = 'Tanım en az 10 karakter olmalı.';
            return [];
        }
        // Q4: context_type — sadece beklenen enum değerleri
        $contextType = trim((string) $req->input('context_type', 'diger'));
        $allowedCtx = array_keys(\App\Services\GlossaryValidationService::CONTEXT_TYPES);
        if (!in_array($contextType, $allowedCtx, true)) {
            $contextType = 'diger';
        }
        return [
            'term'         => mb_substr($term, 0, 180),
            'definition'   => Sanitizer::clean($def),
            'category'     => mb_substr(trim((string) $req->input('category', '')), 0, 80),
            'context_type' => $contextType,
            'aliases'      => mb_substr(trim((string) $req->input('aliases', '')), 0, 2000),
            'references'   => self::normalizeReferences($req->input('references', null)),
            'faq_json'     => self::normalizeFaq($req->input('faq', null)),
            'is_active'    => ((int) $req->input('is_active', 1)) === 1 ? 1 : 0,
        ];
    }

    /**
     * FAQ array'ini normalize edip JSON encode eder. FaqService kuralları
     * ile aynı: q ve a zorunlu, max 220/4000 karakter, max 30 öğe.
     */
    private static function normalizeFaq(mixed $raw): string
    {
        if (!is_array($raw)) {
            return '';
        }
        $items = \App\Services\FaqService::normalize($raw);
        return $items === [] ? '' : \App\Services\FaqService::encode($items);
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
