-- Extend posts.status enum with 'scheduled'
ALTER TABLE `posts`
    MODIFY COLUMN `status`
    ENUM('draft','pending','scheduled','published','rejected','archived')
    NOT NULL DEFAULT 'draft';
