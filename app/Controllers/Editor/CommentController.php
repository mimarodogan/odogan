<?php
declare(strict_types=1);

namespace App\Controllers\Editor;

use App\Core\Request;
use App\Core\Response;
use App\Models\Comment;
use App\Services\Logger;

final class CommentController
{
    public function index(Request $req): Response
    {
        return view('editor.comments', [
            'title' => 'Yorum Moderasyonu',
            'pending' => Comment::pending(100),
        ]);
    }

    public function approve(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        Comment::setStatus($id, Comment::STATUS_APPROVED);
        Logger::info('comment.approved', ['comment_id' => $id], 'comments');
        flash('success', 'Yorum onaylandı.');
        return Response::redirect(url('/editor/yorumlar'));
    }

    public function reject(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        Comment::setStatus($id, Comment::STATUS_REJECTED);
        Logger::info('comment.rejected', ['comment_id' => $id], 'comments');
        flash('success', 'Yorum reddedildi.');
        return Response::redirect(url('/editor/yorumlar'));
    }

    public function spam(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        Comment::setStatus($id, Comment::STATUS_SPAM);
        Logger::info('comment.spam', ['comment_id' => $id], 'comments');
        flash('success', 'Spam olarak işaretlendi.');
        return Response::redirect(url('/editor/yorumlar'));
    }

    public function destroy(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        Comment::delete($id);
        Logger::warning('comment.deleted', ['comment_id' => $id], 'comments');
        flash('success', 'Yorum silindi.');
        return Response::redirect(url('/editor/yorumlar'));
    }
}
