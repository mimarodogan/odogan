-- 062_consent_logs.sql — KVKK açık rıza denetim defteri.
--
-- KVKK m.5 ve GDPR Art. 7(1): rızanın "açık, bilinçli ve özgür irade ile
-- verildiğini" kanıtlama yükümlülüğü veri sorumlusundadır. localStorage
-- tek başına yeterli değil — denetimde ihtilafa karşı sunucu tarafında
-- kayıt tutulmalı.
--
-- Bu tablo her consent eylemini (kabul / red / tercih değişikliği) IP +
-- user-agent + zaman damgası ile birlikte loglar. Üye girişliyse user_id
-- doldurulur; misafir ziyaretçi için NULL.
--
-- Saklama süresi: 5 yıl (genel zamanaşımı). cron ile eski kayıtlar
-- anonimleştirilir (IP NULL'a çevrilir).

CREATE TABLE IF NOT EXISTS consent_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NULL,
    visitor_token   VARCHAR(64)     NULL COMMENT 'Misafir için anonim çerez UUID — IP rotasyonunda kişi takibi',
    ip_address      VARBINARY(16)   NULL COMMENT 'INET6_ATON sonucu — retention sonrası NULL',
    user_agent      VARCHAR(255)    NULL,
    action          ENUM('accept_all','reject_optional','prefs_save','withdraw') NOT NULL,
    categories_json JSON            NULL COMMENT '{"essential":true,"analytics":false,"marketing":false}',
    policy_version  VARCHAR(20)     NOT NULL DEFAULT '1.0' COMMENT 'Çerez/Aydınlatma metninin sürümü — değişince yeniden onay',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_user (user_id, created_at),
    KEY idx_visitor (visitor_token, created_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
