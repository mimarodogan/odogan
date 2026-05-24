-- Co-author / Çoklu yazar pivot tablosu.
-- Mevcut posts.user_id (primary) + posts.editor_id (editör) bu pivot'a migrate edilir.
-- editor_id kolonu DEPRECATED — yeni query'ler pivot'tan okur.
-- role: 'primary' (asıl yazar), 'co_author' (eş yazar), 'editor' (düzenleyen)

CREATE TABLE IF NOT EXISTS `post_authors` (
    `post_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role` ENUM('primary','co_author','editor') NOT NULL DEFAULT 'co_author',
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`post_id`, `user_id`),
    KEY `pa_user_idx` (`user_id`),
    KEY `pa_role_idx` (`role`),
    CONSTRAINT `pa_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `pa_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut yazıların primary yazarlarını pivot'a kopyala
INSERT IGNORE INTO `post_authors` (`post_id`, `user_id`, `role`, `position`)
    SELECT `id`, `user_id`, 'primary', 1
    FROM `posts`
    WHERE `user_id` IS NOT NULL;

-- editor_id de pivot'a (varsa)
INSERT IGNORE INTO `post_authors` (`post_id`, `user_id`, `role`, `position`)
    SELECT `id`, `editor_id`, 'editor', 2
    FROM `posts`
    WHERE `editor_id` IS NOT NULL;
