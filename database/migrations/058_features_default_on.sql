-- ════════════════════════════════════════════════════════════════════
--  058_features_default_on.sql — Feature flag'leri varsayılan ON
--
--  Sitede ~50 feature flag var, hepsi default OFF idi. Yatırılan
--  altyapının %30'u görünmüyordu. Bu migration 9 düşük-risk, hazır
--  altyapısı olan özelliği ON konuma getirir:
--
--   - author_bio_card_enabled   → yazı altı yazar kartı (E-E-A-T)
--   - prev_next_nav_enabled     → önceki/sonraki yazı linki
--   - series_enabled            → seri/dizi yazılar
--   - clap_enabled              → beğeni butonu
--   - reactions_enabled         → emoji tepkiler
--   - before_after_enabled      → öncesi/sonrası slider
--   - bookmark_db_enabled       → sunucu tarafı yer imi
--   - pwa_enabled               → PWA + service worker
--   - project_portfolio_enabled → proje portfolyosu sayfası
--
--  Daha riskli olanları (paywall, ab_test, sponsor) bilinçli kapalı bıraktık.
--
--  IDEMPOTENT — INSERT IGNORE ile çakışan key'leri atlar.
--  Zaten manuel açtıysan değer değişmez (ON DUPLICATE KEY UPDATE yok).
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `settings` (`group_name`, `key_name`, `value`, `value_type`) VALUES
('features', 'author_bio_card_enabled',   '1', 'bool'),
('features', 'prev_next_nav_enabled',     '1', 'bool'),
('features', 'series_enabled',            '1', 'bool'),
('features', 'clap_enabled',              '1', 'bool'),
('features', 'reactions_enabled',         '1', 'bool'),
('features', 'before_after_enabled',      '1', 'bool'),
('features', 'bookmark_db_enabled',       '1', 'bool'),
('features', 'pwa_enabled',               '1', 'bool'),
('features', 'project_portfolio_enabled', '1', 'bool');
