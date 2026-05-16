-- ════════════════════════════════════════════════════════════════════
--  041_ab_tests.sql — A/B Başlık Testi (Tier 9)
--
--  Bir yazıya 2 alternatif başlık tanımla → trafiği %50/%50 böl,
--  tıklama (CTR) ölç → admin kazananı seçer veya 7 gün sonra otomatik.
--
--  Feature flag: features.ab_test_enabled (default false)
--  IDEMPOTENT — FK ayrı idempotent ALTER ile eklenir.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `ab_tests` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id`     BIGINT UNSIGNED NOT NULL,
    `variant_a`   VARCHAR(220) NOT NULL,
    `variant_b`   VARCHAR(220) NOT NULL,
    `views_a`     INT UNSIGNED NOT NULL DEFAULT 0,
    `views_b`     INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks_a`    INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks_b`    INT UNSIGNED NOT NULL DEFAULT 0,
    `winner`      ENUM('none','a','b','tie') NOT NULL DEFAULT 'none',
    `active`      TINYINT(1) NOT NULL DEFAULT 1,
    `started_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at`    DATETIME NULL,
    UNIQUE KEY `ab_post_uniq` (`post_id`),
    KEY `ab_active_idx` (`active`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ab_tests' AND CONSTRAINT_NAME = 'ab_tests_post_fk') = 0,
    'ALTER TABLE `ab_tests` ADD CONSTRAINT `ab_tests_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
