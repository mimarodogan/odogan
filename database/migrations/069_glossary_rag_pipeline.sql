-- ════════════════════════════════════════════════════════════════════
--  069_glossary_rag_pipeline.sql — RAG v2 pipeline için DB destekleri.
--
--  Bkz: docs/GLOSSARY_AI_REDESIGN.md
--
--  3 yeni kolon:
--    1) source_urls TEXT
--       Kullanıcının form'da manuel girdiği fallback kaynak URL'leri (JSON).
--       Wikipedia'da terim bulunamazsa pipeline bu URL'lerden çeker.
--       Örn: [{"url":"https://archnet.org/sites/12345","title":"..."}]
--
--    2) rag_source_pasajs MEDIUMTEXT
--       Writer AI'a inject edilen ham pasajlar (JSON). Debug/transparency:
--       hangi kaynaklar kullanıldı, neden bu tanım üretildi sorularına cevap.
--       Public sayfada gösterilmez (admin debug only).
--       Örn: { "tr": "...wiki pasajı...", "en": "...slab pasajı..." }
--
--    3) rag_engine VARCHAR(20) DEFAULT 'legacy'
--       Hangi sistem üretmiş: 'legacy' (Q1-Q7 + MC) | 'rag_v2' (yeni RAG).
--       A/B test sırasında ayrımı tutmak için. Migration sonrası mevcut
--       26 terim 'legacy' kalır, yeni üretimler 'rag_v2' işaretlenir.
--
--  Backward compat: tüm yeni kolonlar NULL/DEFAULT — eski INSERT'ler etkilenmez.
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE glossary
    ADD COLUMN source_urls TEXT NULL DEFAULT NULL
        COMMENT 'JSON: manuel girilen fallback kaynak URLs (Wikipedia yoksa)'
        AFTER drift_checked_at,
    ADD COLUMN rag_source_pasajs MEDIUMTEXT NULL DEFAULT NULL
        COMMENT 'JSON: Writer''a inject edilen Wikipedia/manuel pasajlar (debug)'
        AFTER source_urls,
    ADD COLUMN rag_engine VARCHAR(20) NOT NULL DEFAULT 'legacy'
        COMMENT 'legacy | rag_v2 — hangi sistem üretti (A/B telemetri)'
        AFTER rag_source_pasajs;

-- A/B raporlaması için rag_engine üzerinden filtreleme indeksi
CREATE INDEX IF NOT EXISTS gloss_rag_engine_idx ON glossary (rag_engine, is_active);
