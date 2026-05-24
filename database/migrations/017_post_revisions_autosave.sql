-- Post revisions: auto-save (silent draft) bayrağı.
-- Auto-save kayıtları "Sürümler" listesinde ayrı ikonla görünür ve 7 günden eski olanlar temizlenir.

ALTER TABLE `post_revisions`
    ADD COLUMN `is_autosave` TINYINT(1) NOT NULL DEFAULT 0 AFTER `note`;

-- Hızlı arama: bir post'un auto-save kayıtlarını ayıklamak için
ALTER TABLE `post_revisions`
    ADD KEY `pr_autosave_idx` (`post_id`, `is_autosave`, `created_at`);
