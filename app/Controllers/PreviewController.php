<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Post;
use App\Models\User;
use App\Services\AuthService;
use App\Services\MarkdownService;

/**
 * Draft Preview Controller (Tier 7 — Editorial Pro).
 *
 * /onizleme/{token} — token URL'siyle taslak yazı dış kişilere gösterilir.
 * Token PostController::generatePreviewToken'da üretilir.
 *
 * Güvenlik: token sadece o yazıya özel, brute-force'a karşı 32+ hex char.
 */
final class PreviewController
{
    public function show(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('draft_preview_enabled')) {
            return Response::notFound();
        }
        $token = (string) ($args['token'] ?? '');
        if (mb_strlen($token) < 32) {
            return Response::notFound();
        }

        $post = Database::instance()->fetch(
            'SELECT p.*, c.slug AS category_slug, c.name AS category_name,
                    u.name AS author_name, u.slug AS author_slug
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.preview_token = :t LIMIT 1',
            [':t' => $token]
        );
        if (!$post) {
            return Response::notFound();
        }

        // Yayında ise normal URL'sine yönlendir (token gerek yok)
        if ($post['status'] === Post::STATUS_PUBLISHED) {
            return Response::redirect(url('/' . $post['category_slug'] . '/' . $post['slug']));
        }

        $author = User::findById((int) $post['user_id']);
        $body = MarkdownService::render($post);

        return view('pages.post-preview', [
            'title'       => 'Önizleme · ' . $post['title'],
            'description' => 'Yayınlanmamış taslak önizleme',
            'canonical'   => null,
            'page_type'   => 'preview',
            'robots'      => 'noindex, nofollow', // Önizleme indexlenmesin
            'post'        => $post,
            'author'      => $author,
            'body_html'   => $body,
            'is_preview'  => true,
            'preview_status' => $post['status'],
        ]);
    }

    /**
     * POST /panel/yazilar/{id}/onizleme-token — token üret/yenile (sadece sahip+).
     */
    public function generateToken(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('draft_preview_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::json(['ok' => false, 'error' => 'auth'], 401);
        }
        $id = (int) ($args['id'] ?? 0);
        $post = Post::findById($id);
        if (!$post) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $isOwner = (int) $post['user_id'] === (int) $user['id'];
        $isStaff = in_array($user['role'] ?? '', ['admin', 'editor'], true);
        if (!$isOwner && !$isStaff) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $token = bin2hex(random_bytes(20)); // 40 char hex
        Post::update($id, ['preview_token' => $token]);
        return Response::json([
            'ok'    => true,
            'token' => $token,
            'url'   => url('/onizleme/' . $token),
        ]);
    }
}
