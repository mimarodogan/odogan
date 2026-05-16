<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\MediaService;

final class MediaController
{
    private static function publicRoot(): string
    {
        $root = method_exists(Config::class, 'root') ? Config::root() : dirname(__DIR__, 4);
        if (is_file($root . '/index.php')) return $root;
        if (is_file($root . '/public/index.php')) return $root . '/public';
        return $root;
    }

    /**
     * Move files from the wrong /public/uploads/ subdirectory (created during
     * the publicRoot bug) back to the real web root /uploads/.
     */
    public function relocate(Request $req): Response
    {
        $root = method_exists(Config::class, 'root') ? Config::root() : dirname(__DIR__, 4);
        $wrong = $root . '/public/uploads';
        $right = self::publicRoot() . '/uploads';
        $moved = [];
        $errors = [];
        $skipped = [];
        if (!is_dir($wrong)) {
            return Response::redirect(url('/panel/medya') . '?flash=' . urlencode($wrong . ' klasörü zaten yok — taşınacak dosya yok.'));
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($wrong, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            $abs = $f->getPathname();
            $rel = ltrim(substr($abs, strlen($wrong)), '/\\');
            $target = $right . '/' . $rel;
            if ($f->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0775, true);
                continue;
            }
            if (is_file($target)) {
                $skipped[] = $rel . ' (hedefte zaten var)';
                continue;
            }
            if (@rename($abs, $target)) {
                $moved[] = $rel;
            } else {
                // Fall back to copy+unlink for cross-device cases
                if (@copy($abs, $target) && @unlink($abs)) {
                    $moved[] = $rel;
                } else {
                    $errors[] = $rel;
                }
            }
        }
        // Clean up empty source dirs
        $this->removeEmptyDirs($wrong);

        $msg = count($moved) . ' dosya taşındı.';
        if ($skipped) $msg .= ' Atlanan: ' . count($skipped);
        if ($errors) $msg .= ' Hatalı: ' . count($errors);
        return Response::redirect(url('/panel/medya') . '?flash=' . urlencode($msg));
    }

    private function removeEmptyDirs(string $dir): void
    {
        if (!is_dir($dir)) return;
        $children = glob($dir . '/*') ?: [];
        foreach ($children as $c) {
            if (is_dir($c)) $this->removeEmptyDirs($c);
        }
        @rmdir($dir);
    }

    /**
     * Remove media rows whose master file is no longer on disk
     * (orphans created when uploads landed in the wrong directory).
     */
    public function cleanupOrphanRows(Request $req): Response
    {
        $rows = Database::instance()->fetchAll('SELECT id, path FROM media ORDER BY id DESC');
        $removed = [];
        $kept = [];
        foreach ($rows as $r) {
            $abs = self::publicRoot() . '/' . $r['path'];
            if (!is_file($abs)) {
                try {
                    Database::instance()->delete('media', 'id = :id', [':id' => $r['id']]);
                    $removed[] = $r['path'];
                } catch (\Throwable $e) {
                    $kept[] = $r['path'] . ' (silinemedi: ' . $e->getMessage() . ')';
                }
            }
        }
        $msg = count($removed) . ' yetim DB kaydı silindi.';
        if ($kept) $msg .= ' Silinemedi: ' . count($kept);
        return Response::redirect(url('/panel/medya') . '?flash=' . urlencode($msg));
    }

    /**
     * Preview screen: list files on disk that have no media DB record.
     */
    public function reindex(Request $req): Response
    {
        $orphans = $this->findOrphans();
        $rows = '';
        foreach ($orphans as $o) {
            $rows .= '<tr><td>' . htmlspecialchars($o['rel']) . '</td><td>'
                   . htmlspecialchars(number_format((int) $o['size'])) . ' bayt</td><td>'
                   . htmlspecialchars($o['width'] . '×' . $o['height']) . '</td></tr>';
        }
        $count = count($orphans);
        $csrf = csrf_field();
        $action = url('/panel/medya/reindex');
        $html = '<!doctype html><meta charset=utf-8><title>Yeniden İndeksle</title>'
              . '<link rel=stylesheet href="' . url('assets/css/app.css') . '">'
              . '<link rel=stylesheet href="' . url('assets/css/panel.css') . '">'
              . '<link rel=stylesheet href="' . url('assets/css/admin.css') . '">'
              . '<main class=container style="padding-block:3rem 5rem">'
              . '<div class=panel-head><h1>Yeniden İndeksle</h1></div>'
              . '<p class=muted><strong>' . $count . '</strong> tane dosya diskte var ama medya tablosunda kayıtlı değil. '
              . 'Aşağıdaki "Çalıştır" butonu bu dosyaları DB\'ye ekler ve kütüphanede görünür yapar.</p>';
        if ($count > 0) {
            $html .= '<table class=table><thead><tr><th>Yol</th><th>Boyut</th><th>Boyutlar</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                  . '<form method=post action="' . $action . '" class=form-actions>' . $csrf
                  . '<button class="btn btn-primary" type=submit>Çalıştır — ' . $count . ' dosyayı indeksle</button>'
                  . '<a class=btn href="' . url('/panel/medya') . '">← Kütüphane</a></form>';
        } else {
            $html .= '<p class=muted>Yetim dosya bulunamadı — her şey kayıt altında.</p>'
                  . '<div class=form-actions><a class=btn href="' . url('/panel/medya') . '">← Kütüphane</a></div>';
        }
        $html .= '</main>';
        return Response::html($html);
    }

    public function reindexRun(Request $req): Response
    {
        $orphans = $this->findOrphans();
        $user = AuthService::user();
        $added = 0;
        $errors = [];
        foreach ($orphans as $o) {
            try {
                Database::instance()->insert('media', [
                    'user_id' => $user['id'] ?? null,
                    'original_name' => basename($o['rel']),
                    'path' => $o['rel'],
                    'mime' => 'image/jpeg',
                    'width' => (int) $o['width'],
                    'height' => (int) $o['height'],
                    'bytes' => (int) $o['size'],
                    'variants_json' => (string) json_encode($o['variants'], JSON_UNESCAPED_SLASHES),
                    'alt' => null,
                ]);
                $added++;
            } catch (\Throwable $e) {
                $errors[] = $o['rel'] . ': ' . $e->getMessage();
            }
        }
        $msg = $added . ' dosya indekslendi.';
        if ($errors) {
            $msg .= ' Hata: ' . implode(' | ', array_slice($errors, 0, 5));
        }
        return Response::redirect(url('/panel/medya') . '?flash=' . urlencode($msg));
    }

    /**
     * Scan uploads/ for *.jpg files without a media row, harvesting variants.
     * @return array<int,array{rel:string,size:int,width:int,height:int,variants:array}>
     */
    private function findOrphans(): array
    {
        $root = self::publicRoot() . '/uploads';
        if (!is_dir($root)) return [];
        $known = [];
        try {
            $rows = Database::instance()->fetchAll('SELECT path FROM media');
            foreach ($rows as $r) $known[$r['path']] = true;
        } catch (\Throwable) {}

        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $abs = $f->getPathname();
            // Master files: .jpg or .webp at the base level (no width suffix)
            if (!preg_match('#\.(jpg|webp)$#i', $abs)) continue;
            if (preg_match('#-(?:320|768|1280)\.(?:jpg|webp|avif)$#i', $abs)) continue;
            $rel = 'uploads/' . ltrim(substr($abs, strlen($root) + 1), '/');
            if (isset($known[$rel])) continue;
            $size = @filesize($abs) ?: 0;
            $info = @getimagesize($abs);
            $w = $info[0] ?? 0;
            $h = $info[1] ?? 0;
            $base = preg_replace('#\.(jpg|webp)$#i', '', $rel);
            $variants = [];
            foreach ([320, 768, 1280] as $width) {
                $v = ['w' => 0, 'h' => 0];
                $webpRel = $base . '-' . $width . '.webp';
                if (is_file(self::publicRoot() . '/' . $webpRel)) {
                    $vi = @getimagesize(self::publicRoot() . '/' . $webpRel);
                    $v['webp'] = $webpRel;
                    $v['w'] = $vi[0] ?? 0;
                    $v['h'] = $vi[1] ?? 0;
                }
                $avifRel = $base . '-' . $width . '.avif';
                if (is_file(self::publicRoot() . '/' . $avifRel)) {
                    $v['avif'] = $avifRel;
                }
                if (isset($v['webp']) || isset($v['avif'])) {
                    $variants[$width] = $v;
                }
            }
            $out[] = ['rel' => $rel, 'size' => $size, 'width' => $w, 'height' => $h, 'variants' => $variants];
        }
        // Newest first by filename suffix
        usort($out, fn($a, $b) => strcmp($b['rel'], $a['rel']));
        return $out;
    }

    public function upload(Request $req): Response
    {
        try {
            $user = AuthService::user();
            if (empty($req->files['image'])) {
                return Response::json(['ok' => false, 'error' => 'Dosya alanı boş.'], 400);
            }
            $alt = (string) $req->input('alt', '');
            $result = MediaService::uploadFromForm(
                $req->files['image'],
                (int) ($user['id'] ?? 0),
                $alt
            );
            if (!$result['ok']) {
                return Response::json(['ok' => false, 'error' => $result['error']], 422);
            }
            $m = $result['media'];
            $absMaster = self::publicRoot() . '/' . $m['path'];
            return Response::json([
                'ok' => true,
                'id' => (int) $m['id'],
                'path' => $m['path'],
                'url' => url($m['path']),
                'width' => $m['width'],
                'height' => $m['height'],
                'variants' => $m['variants'],
                // Diagnostic fields — visible in DevTools Network → Response
                '_publicRoot' => self::publicRoot(),
                '_absPath' => $absMaster,
                '_fileExists' => is_file($absMaster),
            ]);
        } catch (\Throwable $e) {
            // Never let a fatal escape as HTML — XHR clients can't parse it.
            @error_log('[MediaController::upload] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            // APP_DEBUG=true ise full mesaj, aksi halde generic — prod'da
            // dosya yolları / iç hata mesajları sızdırmasın.
            $debug = (bool) Config::get('APP_DEBUG', false);
            $payload = ['ok' => false];
            if ($debug) {
                $payload['error'] = 'Fatal: ' . $e->getMessage()
                    . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            } else {
                $payload['error'] = 'Yükleme başarısız oldu. Lütfen tekrar deneyin.';
            }
            return Response::json($payload, 500);
        }
    }
}
