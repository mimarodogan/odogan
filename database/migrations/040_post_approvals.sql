-- ════════════════════════════════════════════════════════════════════
--  040_post_approvals.sql — Çok aşamalı onay süreci (Tier 9)
--
--  Akış: draft → review (yazardan editöre) → approved (editörden adm.)
--        → published  (adm. final onay)
--
--  • posts.approval_stage ENUM: 'none' | 'review' | 'approved' | 'published_pending'
--  • posts.submitted_at, posts.approved_at, posts.published_at (var)
--  • post_approvals tablosu: aşama geçiş geçmişi + reviewer + not
--
--  Feature flag: features.approval_workflow_enabled (default false)
--  IDEMPOTENT
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `post_approvals` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id`      BIGINT UNSIGNED NOT NULL,
    `reviewer_id`  BIGINT UNSIGNED NULL,
    `stage`        ENUM('submitted','reviewed','approved','rejected','published') NOT NULL,
    `decision`     ENUM('pending','approved','rejected','revision') NOT NULL DEFAULT 'pending',
    `note`         TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `pa_post_idx` (`post_id`, `created_at`),
    KEY `pa_reviewer_idx` (`reviewer_id`),
    KEY `pa_stage_idx` (`stage`, `decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK idempotent — adı `post_approvals_post_fk` ile unique (022'deki pa_post_fk ile çakışmaz)
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post_approvals' AND CONSTRAINT_NAME = 'post_approvals_post_fk') = 0,
    'ALTER TABLE `post_approvals` ADD CONSTRAINT `post_approvals_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.approval_stage
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'approval_stage') = 0,
    'ALTER TABLE `posts` ADD COLUMN `approval_stage` ENUM(\'none\',\'review\',\'approved\',\'rejected\',\'published\') NOT NULL DEFAULT \'none\'', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.submitted_at
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'submitted_at') = 0,
    'ALTER TABLE `posts` ADD COLUMN `submitted_at` DATETIME NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.approved_at
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'approved_at') = 0,
    'ALTER TABLE `posts` ADD COLUMN `approved_at` DATETIME NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.approved_by
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'approved_by') = 0,
    'ALTER TABLE `posts` ADD COLUMN `approved_by` BIGINT UNSIGNED NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Approval stage indeksi
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'posts_approval_idx') = 0,
    'ALTER TABLE `posts` ADD KEY `posts_approval_idx` (`approval_stage`, `submitted_at`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
