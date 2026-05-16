-- Article alt-tipi ve HowTo schema adımları için kolonlar
-- Schema.org rich result eligibility için:
--   article_type: BlogPosting (default) / NewsArticle / TechArticle / HowTo / Article
--   howto_steps_json: HowTo seçildiğinde adım listesi + totalTime + supply + tool

ALTER TABLE `posts`
    ADD COLUMN `article_type` VARCHAR(32) NOT NULL DEFAULT 'BlogPosting'
        AFTER `body_format`,
    ADD COLUMN `howto_steps_json` LONGTEXT NULL
        AFTER `faq_json`;

-- Mevcut yazıları varsayılan BlogPosting'e taşı (kolon default zaten halleder ama explicit)
UPDATE `posts` SET `article_type` = 'BlogPosting' WHERE `article_type` = '' OR `article_type` IS NULL;
