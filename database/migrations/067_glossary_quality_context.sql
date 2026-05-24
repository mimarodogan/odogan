-- ════════════════════════════════════════════════════════════════════
--  067_glossary_quality_context.sql — Q1: Sözlük girdileri için
--  bağlam etiketi + AI kalite denetim alanları.
--
--  Problem: AI "Döşeme" gibi çok-anlamlı kelimeleri yanlış bağlamda
--  yorumlayabilir (yapı elemanı yerine fayans döşeme eylemi).
--
--  Çözüm: 3 katmanlı savunma
--    A. context_type → AI'ya disambiguation hint (önleyici)
--    B. quality_score + drift_* → 2. AI çağrısı self-review (tespit)
--    C. Admin form'da görsel rozet (manuel review)
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE glossary
    ADD COLUMN context_type VARCHAR(40) NULL DEFAULT NULL
        COMMENT 'Disambiguation hint: yapi_elemani | yapi_teknigi | malzeme | mimari_akim | tasarim_yaklasimi | tarihsel | standart_yonetmelik | ic_mimarlik | diger'
        AFTER category,
    ADD COLUMN quality_score TINYINT UNSIGNED NULL DEFAULT NULL
        COMMENT '0-100 — AI self-review skoru (NULL = henüz denetlenmedi)'
        AFTER context_type,
    ADD COLUMN drift_flag TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = bağlam kayması var, admin incelemesi gerekli'
        AFTER quality_score,
    ADD COLUMN drift_reason TEXT NULL DEFAULT NULL
        COMMENT 'AI tarafından üretilen drift açıklaması'
        AFTER drift_flag,
    ADD COLUMN drift_suggested_fix TEXT NULL DEFAULT NULL
        COMMENT 'AI önerisi: tanım nasıl düzeltilmeli'
        AFTER drift_reason,
    ADD COLUMN drift_checked_at DATETIME NULL DEFAULT NULL
        COMMENT 'Son denetim zamanı'
        AFTER drift_suggested_fix;

-- Drift olanları admin liste view'da öne sıralamak için index
CREATE INDEX IF NOT EXISTS gloss_drift_idx ON glossary (drift_flag, quality_score);
