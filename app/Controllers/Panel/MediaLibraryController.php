<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\MediaService;

/**
 * WordPress-style central media library.
 * Owners + editors+ can view all; authors see only their own uploads.
 */
final class MediaLibraryController
{
    public const PER_PAGE = 24;

    public function index(Request $req): Response
    {
        $page = max(1, (int) $req->input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;
        [$where, $params] = self::scope();

        $total = (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM media m $where", $params
        );
        $items = Database::instance()->fetchAll(
            "SELECT m.*, u.name AS uploader_name
             FROM media m
             LEFT JOIN users u ON u.id = m.user_id
             $where
             ORDER BY m.id DESC
             LIMIT " . self::PER_PAGE . " OFFSET " . $offset,
            $params
        );
        foreach ($items as &$m) {
            $m['variants'] = $m['variants_json']
                ? (array) json_decode((string) $m['variants_json'], true)
                : [];
            // Gösterilen boyut: diskteki gerçek (webp) master dosyası. Eski
            // kayıtlarda `bytes` orijinal yükleme boyutunu tutuyor olabilir.
            $abs = Config::publicRoot() . '/' . ltrim((string) $m['path'], '/');
            $real = @filesize($abs);
            if ($real !== false) {
                $m['bytes'] = $real;
            }
        }
        unset($m);

        return view('panel.media.index', [
            'title' => 'Görsel Kütüphanesi',
            'items' => $items,
            'page' => $page,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
        ]);
    }

    /**
     * JSON list for the WYSIWYG image picker modal.
     */
    public function listJson(Request $req): Response
    {
        $page = max(1, (int) $req->input('page', 1));
        $q = trim((string) $req->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;

        [$where, $params] = self::scope();
        if ($q !== '') {
            $where .= ($where === '' ? ' WHERE ' : ' AND ')
                . '(m.original_name LIKE :q OR m.alt LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $items = Database::instance()->fetchAll(
            "SELECT m.id, m.path, m.original_name, m.alt, m.width, m.height, m.variants_json
             FROM media m $where
             ORDER BY m.id DESC
             LIMIT " . self::PER_PAGE . " OFFSET " . $offset,
            $params
        );
        $out = [];
        foreach ($items as $m) {
            $variants = $m['variants_json']
                ? (array) json_decode((string) $m['variants_json'], true)
                : [];
            $thumbWebp = $variants[320]['webp'] ?? null;
            $bigWebp = $variants[1280]['webp'] ?? ($variants[768]['webp'] ?? null);
            $out[] = [
                'id' => (int) $m['id'],
                'path' => $m['path'],
                'url' => url($m['path']),
                'thumb' => $thumbWebp ? url($thumbWebp) : url($m['path']),
                'preview' => $bigWebp ? url($bigWebp) : url($m['path']),
                'name' => $m['original_name'],
                'alt' => $m['alt'] ?? '',
                'width' => (int) $m['width'],
                'height' => (int) $m['height'],
                'variants' => $variants,
            ];
        }
        return Response::json(['items' => $out, 'page' => $page]);
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $row = self::findOwned($id);
        if ($row === null) {
            return Response::notFound();
        }
        Database::instance()->update('media', [
            'alt' => mb_substr(trim((string) $req->input('alt', '')), 0, 255),
            'original_name' => mb_substr(trim((string) $req->input('title', $row['original_name'])), 0, 255),
        ], 'id = :wid', [':wid' => $id]);
        flash('success', 'Görsel bilgileri güncellendi.');
        return Response::redirect(self::backUrl($req));
    }

    public function destroy(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $row = self::findOwned($id);
        if ($row === null) {
            return Response::notFound();
        }
        self::deleteFiles($row);
        Database::instance()->delete('media', 'id = :wid', [':wid' => $id]);
        Logger::warning('media.deleted', ['id' => $id, 'path' => $row['path']], 'media');
        flash('success', 'Görsel silindi.');
        return Response::redirect(self::backUrl($req));
    }

    /**
     * Authors only see their own media; editor+ see everything.
     * @return array{0:string,1:array}
     */
    private static function scope(): array
    {
        $u = AuthService::user();
        if ($u && in_array($u['role'], ['admin', 'editor'], true)) {
            return ['', []];
        }
        return [' WHERE m.user_id = :uid ', [':uid' => $u['id'] ?? 0]];
    }

    /** Düzenleme/silme sonrası bulunulan sayfaya geri dön (sayfalama korunur). */
    private static function backUrl(Request $req): string
    {
        $page = max(1, (int) $req->input('page', 1));
        return url('/panel/medya') . ($page > 1 ? '?page=' . $page : '');
    }

    private static function findOwned(int $id): ?array
    {
        $u = AuthService::user();
        $row = Database::instance()->fetch('SELECT * FROM media WHERE id = :id', [':id' => $id]);
        if ($row === null) {
            return null;
        }
        if (!in_array($u['role'] ?? '', ['admin', 'editor'], true)
            && (int) $row['user_id'] !== (int) ($u['id'] ?? 0)) {
            return null;
        }
        return $row;
    }

    private static function deleteFiles(array $row): void
    {
        $base = Config::publicRoot() . '/';
        @unlink($base . ltrim((string) $row['path'], '/'));
        $variants = $row['variants_json']
            ? (array) json_decode((string) $row['variants_json'], true)
            : [];
        foreach ($variants as $v) {
            foreach (['webp', 'avif'] as $k) {
                if (!empty($v[$k])) {
                    @unlink($base . ltrim((string) $v[$k], '/'));
                }
            }
        }
    }
}
