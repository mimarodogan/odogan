<?php
declare(strict_types=1);

namespace App\Controllers\Editor;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\PostApproval;
use App\Models\Setting;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\PostNotifier;

/**
 * Editor/Admin onay süreci kontrolcüsü (Tier 9).
 *
 * Yazar `/panel/yazilar/{id}/gonder` çağırır → review aşamasına geçer.
 * Editör `/editor/onaylar` listesinden inceler → approve/reject.
 * Admin son aşamada `/admin/onaylar` ile publish veya reddeder.
 */
final class ApprovalController
{
    /** Yazar tarafı — taslağı incelemeye gönder. */
    public function submit(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        if (!Setting::get('approval_workflow_enabled', false, 'features')) {
            flash('error', 'Onay süreci kapalı.');
            return Response::redirect(url('/panel/yazilar'));
        }
        $user = AuthService::user();
        if (!$user) return Response::redirect(url('/giris'));

        $post = Post::findById($postId);
        if (!$post || (int) $post['user_id'] !== (int) $user['id']) {
            return Response::notFound();
        }

        Database::instance()->update('posts', [
            'approval_stage' => 'review',
            'submitted_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ], 'id = :wid', [':wid' => $postId]);

        PostApproval::record($postId, (int) $user['id'], 'submitted', 'pending', (string) $req->input('note', ''));
        AuditLog::record('approval.submitted', 'post', $postId, (string) $post['title']);
        Logger::info('approval.submitted', ['post' => $postId, 'by' => $user['id']], 'editor');

        flash('success', 'Yazı incelemeye gönderildi.');
        return Response::redirect(url('/panel/yazilar/' . $postId . '/duzenle'));
    }

    /** Editör — bekleyenler. */
    public function index(Request $req): Response
    {
        $reviewItems = PostApproval::pendingPosts('review');
        $approvedItems = PostApproval::pendingPosts('approved');
        return view('editor.approvals.index', [
            'title' => 'Onay Süreci',
            'review_items' => $reviewItems,
            'approved_items' => $approvedItems,
        ]);
    }

    /** Editör — bir yazıyı incele. */
    public function show(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        $post = Post::findById($postId);
        if (!$post) return Response::notFound();
        $history = PostApproval::historyFor($postId);
        return view('editor.approvals.show', [
            'title' => 'İnceleme: ' . $post['title'],
            'post' => $post,
            'history' => $history,
        ]);
    }

    /** Editör onayı → "approved" aşamasına geçer (admin'i bekler). */
    public function approve(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        $post = Post::findById($postId);
        if (!$post) return Response::notFound();

        Database::instance()->update('posts', [
            'approval_stage' => 'approved',
        ], 'id = :wid', [':wid' => $postId]);

        PostApproval::record($postId, (int) $user['id'], 'reviewed', 'approved', (string) $req->input('note', ''));
        AuditLog::record('approval.reviewed', 'post', $postId, (string) $post['title']);
        try {
            if (method_exists(PostNotifier::class, 'notifyApprovalProgress')) {
                PostNotifier::notifyApprovalProgress($postId, 'reviewed');
            }
        } catch (\Throwable $e) { /* mail opsiyonel */ }

        flash('success', 'Yazı admin onayına gönderildi.');
        return Response::redirect(url('/editor/onaylar'));
    }

    /** Editör reddi → drafta geri döner. */
    public function reject(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        $post = Post::findById($postId);
        if (!$post) return Response::notFound();

        $note = trim((string) $req->input('note', ''));

        Database::instance()->update('posts', [
            'approval_stage' => 'rejected',
            'status' => 'draft',
        ], 'id = :wid', [':wid' => $postId]);

        PostApproval::record($postId, (int) $user['id'], 'reviewed', 'rejected', $note);
        AuditLog::record('approval.rejected', 'post', $postId, (string) $post['title']);

        flash('success', 'Yazı yazara revizyon için geri gönderildi.');
        return Response::redirect(url('/editor/onaylar'));
    }

    /** Admin — final publish. */
    public function publish(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        $post = Post::findById($postId);
        if (!$post) return Response::notFound();

        $now = date('Y-m-d H:i:s');
        Database::instance()->update('posts', [
            'approval_stage' => 'published',
            'status' => 'published',
            'approved_by' => (int) $user['id'],
            'approved_at' => $now,
            'published_at' => $post['published_at'] ?? $now,
        ], 'id = :wid', [':wid' => $postId]);

        PostApproval::record($postId, (int) $user['id'], 'published', 'approved', (string) $req->input('note', ''));
        AuditLog::record('approval.published', 'post', $postId, (string) $post['title']);

        flash('success', 'Yazı yayına alındı.');
        return Response::redirect(url('/editor/onaylar'));
    }
}
