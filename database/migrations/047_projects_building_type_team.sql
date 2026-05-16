-- ─────────────────────────────────────────────────────────────────────────
-- 047_projects_building_type_team.sql
--
-- Projects modeline iki büyük genişleme:
--   • building_type — yapı tipolojisi (konut, otel, ofis, kamu, egitim,
--     kultur, dini, ticari, karma, saglik, endustri, restorasyon, diger).
--     "role" alanı kullanıcının projedeki ROLÜ olarak kalır; building_type
--     ise projenin TÜRÜdür. İkisi farklı eksendir.
--   • team_json — yapı künyesi: mimarlar / mühendisler / danışmanlar
--     gruplandırılmış, her üyenin adı + ünvanı + URL'i.
--
-- İdempotent: INFORMATION_SCHEMA kontrolüyle "kolon yoksa ekle".
-- ─────────────────────────────────────────────────────────────────────────

-- ── building_type kolonu ──
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'building_type'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE projects ADD COLUMN building_type VARCHAR(30) NOT NULL DEFAULT 'diger' AFTER role",
    "SELECT 'building_type column already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── team_json kolonu ──
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'team_json'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE projects ADD COLUMN team_json LONGTEXT NULL AFTER partners_json",
    "SELECT 'team_json column already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── building_type için index (filtre performansı) ──
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND INDEX_NAME = 'projects_building_type_idx'
);
SET @sql := IF(@idx_exists = 0,
    "ALTER TABLE projects ADD KEY projects_building_type_idx (building_type)",
    "SELECT 'projects_building_type_idx already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
