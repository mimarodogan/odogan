<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Request;
use App\Core\Response;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Logger;

final class RevisionController
{
    public function index(Request $req, array $args): Response
    {
        $post = self::ownedOrFail((int) ($args['id'] ?? 0));
        if ($post instanceof Response) {
            return $post;
        }
        return view('panel.posts.revisions', [
            'title' => 'Sürüm Geçmişi: ' . $post['title'],
            'post' => $post,
            'revisions' => PostRevision::listForPost((int) $post['id'], 20),
        ]);
    }

    public function show(Request $req, array $args): Response
    {
        $rev = PostRevision::findById((int) ($args['rid'] ?? 0));
        if ($rev === null) {
            return Response::notFound();
        }
        $post = self::ownedOrFail((int) $rev['post_id']);
        if ($post instanceof Response) {
            return $post;
        }
        return view('panel.posts.revision_diff', [
            'title' => 'Sürüm #' . $rev['id'],
            'post' => $post,
            'revision' => $rev,
        ]);
    }

    public function restore(Request $req, array $args): Response
    {
        $rev = PostRevision::findById((int) ($args['rid'] ?? 0));
        if ($rev === null) {
            return Response::notFound();
        }
        $post = self::ownedOrFail((int) $rev['post_id']);
        if ($post instanceof Response) {
            return $post;
        }
        $user = AuthService::user();
        // Keep current state as a revision before rolling back.
        PostRevision::snapshot($post, (int) $user['id'], 'snapshot before restore #' . $rev['id']);
        Post::update((int) $post['id'], [
            'title' => $rev['title'],
            'excerpt' => $rev['excerpt'],
            'body' => $rev['body'],
            'body_format' => $rev['body_format'],
            'faq_json' => $rev['faq_json'],
        ], (int) ($post['category_id'] ?? 0));
        Logger::info('post.revision.restored', [
            'post_id' => (int) $post['id'], 'revision_id' => (int) $rev['id'],
        ], 'editorial');
        flash('success', 'Önceki sürüm geri yüklendi.');
        return Response::redirect(url('/panel/yazilar/' . $post['id'] . '/duzenle'));
    }

    private static function ownedOrFail(int $id): array|Response
    {
        $post = Post::findById($id);
        if ($post === null) {
            return Response::notFound();
        }
        $u = AuthService::user();
        if ($u === null) {
            return Response::redirect(url('/giris'));
        }
        $isStaff = in_array($u['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);
        if (!$isStaff && (int) $post['user_id'] !== (int) $u['id']) {
            return Response::html('<h1>403</h1>', 403);
        }
        return $post;
    }
}
