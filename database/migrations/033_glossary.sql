-- ════════════════════════════════════════════════════════════════════
--  033_glossary.sql — Architectural Glossary (Tier 7 — Architecture niche)
--  • Mimari ve mühendislik terimleri sözlüğü
--  • Posta tooltip ile entegre olabilir
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `glossary` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`        VARCHAR(120) NOT NULL,
    `term`        VARCHAR(180) NOT NULL,
    `definition`  TEXT NOT NULL,
    `category`    VARCHAR(80) NULL,
    `aliases`     VARCHAR(500) NULL,
    `references`  VARCHAR(500) NULL,
    `view_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `g_slug_uniq` (`slug`),
    KEY `g_term_idx` (`term`),
    KEY `g_active_idx` (`is_active`),
    FULLTEXT KEY `g_search_idx` (`term`, `definition`, `aliases`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
