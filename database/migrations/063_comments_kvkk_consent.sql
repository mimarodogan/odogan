-- 063_comments_kvkk_consent.sql — Yorum gönderirken alınan KVKK açık rıza
-- timestamp'i saklanır. KVKK m.5 ispat yükümlülüğü için her yorumun yanında
-- onayın hangi saniye verildiği görünür kalmalı.
--
-- Mevcut yorumlar için NULL — gönderme tarihinden önceki kayıtlar zaten
-- consent_logs tablosunda anonim olarak yer almıyor; bu kolon sadece bu
-- migration sonrası gönderilen yorumlar için doldurulur.

ALTER TABLE comments
    ADD COLUMN kvkk_consent_at DATETIME NULL DEFAULT NULL
        COMMENT 'KVKK Aydınlatma Metni onayının verildiği an (NULL = pre-migration eski yorum)'
        AFTER user_agent;
