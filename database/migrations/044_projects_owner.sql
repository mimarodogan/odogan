-- ════════════════════════════════════════════════════════════════════
--  044_projects_owner.sql — Proje sahipliği + onay süreci (Tier 9.2)
--
--  • projects.user_id  — projeyi oluşturan kullanıcı
--  • projects.approval_stage  — 'none' | 'review' | 'approved' | 'rejected'
--    (Author/Editor draft kaydeder, admin yayına alır)
--  • projects.approved_by, approved_at — son onaylayan admin + zaman
--
--  IDEMPOTENT — phpMyAdmin'de güvenli.
-- ════════════════════════════════════════════════════════════════════

-- user_id
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'user_id') = 0,
    'ALTER TABLE `projects` ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `id`', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- approval_stage
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'approval_stage') = 0,
    'ALTER TABLE `projects` ADD COLUMN `approval_stage` ENUM(\'none\',\'review\',\'approved\',\'rejected\') NOT NULL DEFAULT \'none\'', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- approved_by
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'approved_by') = 0,
    'ALTER TABLE `projects` ADD COLUMN `approved_by` BIGINT UNSIGNED NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- approved_at
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'approved_at') = 0,
    'ALTER TABLE `projects` ADD COLUMN `approved_at` DATETIME NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- submitted_at
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'submitted_at') = 0,
    'ALTER TABLE `projects` ADD COLUMN `submitted_at` DATETIME NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- user_id index
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'projects_user_idx') = 0,
    'ALTER TABLE `projects` ADD KEY `projects_user_idx` (`user_id`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- approval_stage index
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'projects_approval_idx') = 0,
    'ALTER TABLE `projects` ADD KEY `projects_approval_idx` (`approval_stage`, `submitted_at`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK user → users (ON DELETE SET NULL — user soft-delete edilse de proje korunur)
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND CONSTRAINT_NAME = 'projects_user_fk') = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `projects_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
