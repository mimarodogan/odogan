<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Centralizes mail notifications for the editorial workflow so that
 * controllers stay slim and the wording is consistent.
 */
final class PostNotifier
{
    public static function notifyEditorsOfSubmission(int $postId, string $title, string $authorName): void
    {
        $editors = Database::instance()->fetchAll(
            "SELECT email, name FROM users WHERE role IN ('admin','editor') AND status='active'"
        );
        if (!$editors) {
            return;
        }
        $vars = [
            'post_title'  => $title,
            'author_name' => $authorName,
            'review_link' => url('/editor/onay/' . $postId),
        ];
        foreach ($editors as $e) {
            MailService::sendTemplate('post_submitted', (string) $e['email'], $vars);
        }
    }

    public static function notifyAuthorOfApproval(string $email, string $title, string $publicUrl, string $userName = ''): void
    {
        MailService::sendTemplate('post_approved', $email, [
            'user_name'   => $userName,
            'post_title'  => $title,
            'public_link' => $publicUrl,
        ]);
    }

    public static function notifyAuthorOfRejection(string $email, string $title, string $reason, string $userName = ''): void
    {
        MailService::sendTemplate('post_rejected', $email, [
            'user_name'  => $userName,
            'post_title' => $title,
            'reason'     => $reason !== '' ? nl2br(esc($reason)) : 'Belirtilmedi.',
        ]);
    }

    /**
     * Yeni yazar başvurusu geldiğinde admin'lere mail (Tier 5 feature 5.1).
     */
    public static function notifyAdminOfAuthorApplication(
        int $applicantId,
        string $applicantName,
        string $applicantEmail,
        array $appData
    ): void {
        $admins = Database::instance()->fetchAll(
            "SELECT email, name FROM users WHERE role IN ('admin') AND status='active'"
        );
        if (!$admins) {
            return;
        }
        $vars = [
            'applicant_name'  => $applicantName,
            'applicant_email' => $applicantEmail,
            'headline'        => mb_substr((string) ($appData['headline'] ?? ''), 0, 160),
            'expertise'       => mb_substr((string) ($appData['expertise'] ?? ''), 0, 200),
            'review_link'     => url('/admin/yazar-basvurulari/' . $applicantId),
        ];
        foreach ($admins as $a) {
            MailService::sendTemplate('author_app_submitted', (string) $a['email'], $vars);
        }

        Logger::info(
            'author_app.notify_admins',
            ['applicant_id' => $applicantId, 'admin_count' => count($admins)],
            'editorial'
        );
    }

    /**
     * Başvuru onaylandığında başvurucuya bildirim (Tier 5 feature 5.1).
     */
    public static function notifyAuthorOfApprovalAsWriter(string $email, string $name): void
    {
        MailService::sendTemplate('author_app_approved', $email, [
            'user_name'  => $name,
            'panel_link' => url('/panel/yazilar/yeni'),
        ]);
    }

    /**
     * Başvuru reddedildiğinde başvurucuya bildirim (Tier 5 feature 5.1).
     */
    public static function notifyAuthorOfApplicationRejection(string $email, string $name, string $reason): void
    {
        MailService::sendTemplate('author_app_rejected', $email, [
            'user_name' => $name,
            'reason'    => $reason !== '' ? nl2br(esc($reason)) : 'Editörler ek bir not bırakmadı.',
        ]);
    }

    /**
     * Yeni yorum geldiğinde admin/editor'lere mail bildirimi (Tier 5 feature 4.4).
     * Yorum onay kuyruğuna düşmüş, hala pending durumdadır.
     *
     * Bilgilendirici özet + onayla/reddet direkt link.
     */
    public static function notifyAdminOfComment(
        int $commentId,
        int $postId,
        string $postTitle,
        string $commenterName,
        string $commenterEmail,
        string $body
    ): void {
        if (!function_exists('feature') || !feature('comment_admin_mail')) {
            return;
        }
        $admins = Database::instance()->fetchAll(
            "SELECT email, name FROM users WHERE role IN ('admin','editor') AND status='active'"
        );
        if (!$admins) {
            return;
        }
        $excerpt = mb_substr($body, 0, 400);
        if (mb_strlen($body) > 400) $excerpt .= '…';

        $vars = [
            'post_title'        => $postTitle,
            'commenter_name'    => $commenterName,
            'commenter_email'   => $commenterEmail,
            'comment_excerpt'   => nl2br(esc($excerpt)),
            'moderation_link'   => url('/editor/yorumlar'),
        ];
        foreach ($admins as $a) {
            MailService::sendTemplate('comment_admin_notify', (string) $a['email'], $vars);
        }

        Logger::info(
            'comment.notify_admin',
            ['comment_id' => $commentId, 'post_id' => $postId, 'admin_count' => count($admins)],
            'editorial'
        );
    }
}
