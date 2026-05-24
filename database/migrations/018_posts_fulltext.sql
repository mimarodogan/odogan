-- Full-text arama için title + excerpt + body üzerine FULLTEXT index.
-- MySQL 5.6+ InnoDB FULLTEXT'i destekler.
-- MATCH(...) AGAINST (... IN BOOLEAN MODE) ile aranır.

ALTER TABLE `posts`
    ADD FULLTEXT KEY `ft_search` (`title`, `excerpt`, `body`);

-- innodb_ft_min_token_size default 3 — 2 harfli kelimeler için aşağıdaki
-- ayar gerekebilir (my.cnf): innodb_ft_min_token_size = 2
-- Bu, sysadmin tarafından opsiyonel ayarlanabilir.
