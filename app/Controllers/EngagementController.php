<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuthorFollow;
use App\Models\Bookmark;
use App\Models\Post;
use App\Models\PostClap;
use App\Services\AuthService;

/**
 * Engagement Controller (Tier 7).
 *
 * Tüm engagement aksiyonları JSON endpoint:
 *  - POST /etkilesim/clap/{postId}
 *  - POST /etkilesim/bookmark/{postId}
 *  - POST /etkilesim/takip/{authorId}
 *  - GET  /etkilesim/durum/{postId}  → user state (clapped, bookmarked)
 *  - GET  /kaydedilenler  (server-side bookmarks listesi, /panel kullanıcı için)
 */
final class EngagementController
{
    public function clap(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('clap_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $postId = (int) ($args['postId'] ?? 0);
        if ($postId <= 0) {
            return Response::json(['ok' => false, 'error' => 'invalid_post'], 400);
        }
        $post = Post::findById($postId);
        if (!$post || $post['status'] !== Post::STATUS_PUBLISHED) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $user = AuthService::user();
        $userId = $user['id'] ?? null;
        $ip = $req->ip();

        $newTotal = PostClap::clap($postId, $userId ? (int) $userId : null, $ip);
        $myCount = PostClap::userClapCount($postId, $userId ? (int) $userId : null, $ip);

        return Response::json([
            'ok'        => true,
            'total'     => $newTotal,
            'my_count'  => $myCount,
        ]);
    }

    public function bookmark(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('bookmark_db_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::json(['ok' => false, 'error' => 'login_required'], 401);
        }
        $postId = (int) ($args['postId'] ?? 0);
        $post = Post::findById($postId);
        if (!$post) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $saved = Bookmark::toggle((int) $user['id'], $postId);
        return Response::json([
            'ok'    => true,
            'saved' => $saved,
            'count' => Bookmark::countFor((int) $user['id']),
        ]);
    }

    public function follow(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('author_follow_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::json(['ok' => false, 'error' => 'login_required'], 401);
        }
        $authorId = (int) ($args['authorId'] ?? 0);
        if ($authorId === (int) $user['id']) {
            return Response::json(['ok' => false, 'error' => 'self_follow'], 400);
        }
        $following = AuthorFollow::toggle((int) $user['id'], $authorId);
        return Response::json([
            'ok'        => true,
            'following' => $following,
            'follower_count' => AuthorFollow::followerCountFor($authorId),
        ]);
    }

    public function state(Request $req, array $args): Response
    {
        $postId = (int) ($args['postId'] ?? 0);
        $user = AuthService::user();
        $userId = $user['id'] ?? null;
        $ip = $req->ip();
        return Response::json([
            'ok'              => true,
            'clap_total'      => PostClap::totalFor($postId),
            'clap_my_count'   => PostClap::userClapCount($postId, $userId ? (int) $userId : null, $ip),
            'bookmarked'      => $userId && function_exists('feature') && feature('bookmark_db_enabled')
                ? Bookmark::isBookmarked((int) $userId, $postId)
                : false,
        ]);
    }

    /**
     * /kaydedilenler (panel) — sunucu tarafı bookmark listesi (giriş yapmış kullanıcı için).
     */
    public function myBookmarks(Request $req): Response
    {
        if (!function_exists('feature') || !feature('bookmark_db_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        $bookmarks = Bookmark::forUser((int) $user['id'], 100);
        return view('pages.bookmarks-server', [
            'title'     => 'Kaydedilen Yazılar',
            'description' => 'Daha sonra okumak üzere kaydettiğin yazılar.',
            'canonical' => absolute_url('/panel/kaydedilenler'),
            'bookmarks' => $bookmarks,
        ]);
    }
}
