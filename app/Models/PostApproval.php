<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Çok aşamalı onay işlemleri (Tier 9).
 *
 * Akış:
 *   draft  → submit  → review (editör görür)
 *   review → approve → approved (admin görür)
 *   approved → publish → published (yayında)
 *
 * Geçişler `post_approvals` tablosunda kayıt altına alınır.
 */
final class PostApproval
{
    public static function record(int $postId, int $reviewerId, string $stage, string $decision, ?string $note = null): int
    {
        return (int) Database::instance()->insert('post_approvals', [
            'post_id' => $postId,
            'reviewer_id' => $reviewerId,
            'stage' => $stage,
            'decision' => $decision,
            'note' => $note,
        ]);
    }

    /**
     * Bir yazının onay geçmişi (kronolojik).
     */
    public static function historyFor(int $postId): array
    {
        return Database::instance()->fetchAll(
            'SELECT pa.*, u.name AS reviewer_name, u.slug AS reviewer_slug
             FROM post_approvals pa
             LEFT JOIN users u ON u.id = pa.reviewer_id
             WHERE pa.post_id = :pid
             ORDER BY pa.created_at ASC',
            [':pid' => $postId]
        );
    }

    /**
     * İncelemeyi bekleyen yazılar (editör listesi).
     *
     * @param string $stage 'review' | 'approved'
     */
    public static function pendingPosts(string $stage = 'review', int $limit = 100): array
    {
        $stage = in_array($stage, ['review', 'approved'], true) ? $stage : 'review';
        return Database::instance()->fetchAll(
            "SELECT p.id, p.title, p.slug, p.submitted_at, p.approval_stage,
                    u.name AS author_name, u.slug AS author_slug, c.name AS category_name
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.approval_stage = :stage
             ORDER BY p.submitted_at ASC
             LIMIT " . max(1, $limit),
            [':stage' => $stage]
        );
    }

    /**
     * Pending sayıları (badge için).
     */
    public static function pendingCounts(): array
    {
        $row = Database::instance()->fetch(
            "SELECT
                SUM(CASE WHEN approval_stage = 'review' THEN 1 ELSE 0 END) AS review_count,
                SUM(CASE WHEN approval_stage = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM posts"
        );
        return [
            'review' => (int) ($row['review_count'] ?? 0),
            'approved' => (int) ($row['approved_count'] ?? 0),
        ];
    }
}
