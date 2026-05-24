-- ════════════════════════════════════════════════════════════════════
--  068_glossary_context_multi.sql — MC1: context_type tek-değerden
--  çoklu-değer (CSV) yapıya geçiş.
--
--  Problem (kullanıcı geri bildirimi):
--    "Sadece yapı elemanı olarak düşünmeyelim — bir terim hem yapı
--     elemanı hem mimari akım/dönem olabilir. Çoktan seçmeli menü
--     ile mi ilerlesek?"
--
--  Örnek: "Kemer"
--    → yapi_elemani  (mimari öğe — açıklık üstü taşıyıcı)
--    → tarihsel      (Roma/Selçuklu kemerleri — tarihsel bağlam)
--    → mimari_akim   (kemerli üslup — Roma, Gotik, İslam mimarisi)
--
--  Çözüm:
--    VARCHAR(40) → VARCHAR(255), virgülle ayrılmış (CSV).
--    Örn: "yapi_elemani,tarihsel,mimari_akim"
--    Max 3 değer (UI'da JS limit; arka uçta yine 3'e kırpılır).
--
--  Backward compat:
--    Mevcut tek-değerli kayıtlar ("yapi_elemani") aynen geçerli kalır;
--    explode(',') tek-elemanlı array döndürür, normal akış sürer.
--
--  NOT: JSON tipi yerine CSV seçildi — sorgular basit, mevcut data
--  hiç dokunmadan çalışır, PHP tarafında implode/explode yeterli.
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE glossary
    MODIFY COLUMN context_type VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Bağlam türü(leri) — CSV (max 3 değer). Örn: "yapi_elemani,tarihsel". Bkz: GlossaryValidationService::CONTEXT_TYPES';
