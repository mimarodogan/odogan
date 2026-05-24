<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Post;
use App\Models\PostReaction;
use App\Services\AuthService;

/**
 * Post Reactions (Tier 8) — JSON endpoint.
 *  POST /etkilesim/reaksiyon/{postId}/{emoji}
 *  GET  /etkilesim/reaksiyon/{postId}  → summary
 */
final class ReactionController
{
    public function toggle(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('reactions_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $postId = (int) ($args['postId'] ?? 0);
        $emoji = (string) ($args['emoji'] ?? '');
        if (!isset(PostReaction::EMOJIS[$emoji])) {
            return Response::json(['ok' => false, 'error' => 'invalid_emoji'], 400);
        }
        $post = Post::findById($postId);
        if (!$post || $post['status'] !== Post::STATUS_PUBLISHED) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $user = AuthService::user();
        $added = PostReaction::toggle(
            $postId,
            $emoji,
            $user ? (int) $user['id'] : null,
            $req->ip()
        );
        $summary = PostReaction::summary($postId, $user ? (int) $user['id'] : null, $req->ip());
        return Response::json([
            'ok'      => true,
            'added'   => $added,
            'counts'  => $summary['counts'],
            'mine'    => $summary['mine'],
        ]);
    }

    public function summary(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('reactions_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $postId = (int) ($args['postId'] ?? 0);
        $user = AuthService::user();
        $summary = PostReaction::summary($postId, $user ? (int) $user['id'] : null, $req->ip());
        return Response::json([
            'ok'     => true,
            'counts' => $summary['counts'],
            'mine'   => $summary['mine'],
        ]);
    }
}
