-- ─────────────────────────────────────────────────────────────────────────
-- 048_projects_address_structured.sql
--
-- Schema.org PostalAddress için opsiyonel structured address alanları.
-- Mevcut `location` (serbest string) gibi kalır; bu alanlar sadece
-- admin doldurursa schema'da kullanılır (sallama YOK — boşsa atlanır).
--
--   address_locality — ilçe/şehir (örn. "Osmangazi")
--   address_region   — il/bölge (örn. "Bursa")
--   postal_code      — posta kodu (opsiyonel, örn. "16050")
--
-- `address_country` her zaman "TR" — sabit, kolona gerek yok.
--
-- İdempotent: kolon yoksa ekler.
-- ─────────────────────────────────────────────────────────────────────────

-- ── address_locality kolonu ──
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'address_locality'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE projects ADD COLUMN address_locality VARCHAR(100) NULL AFTER location",
    "SELECT 'address_locality already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── address_region kolonu ──
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'address_region'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE projects ADD COLUMN address_region VARCHAR(100) NULL AFTER address_locality",
    "SELECT 'address_region already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── postal_code kolonu ──
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'postal_code'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE projects ADD COLUMN postal_code VARCHAR(20) NULL AFTER address_region",
    "SELECT 'postal_code already exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
