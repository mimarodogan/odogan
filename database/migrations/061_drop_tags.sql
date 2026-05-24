-- 061_drop_tags.sql — Etiket sistemini tamamen kaldır.
--
-- Karar gerekçesi:
--   • Etiketler kategori + serie + sözlük autolink sistemiyle örtüşüyordu.
--   • Yazar etiket girmeyi unutuyordu / tutarsız etiketliyordu → düşük değer.
--   • İlgili yazılar zaten kategori-bazlı (Post::relatedSmart) çalışıyor.
--   • SEO katkısı: /etiket/{slug} arşivleri ince içerik (thin content) riskli.
--
-- Etki:
--   • Tablo: tags, post_tag (FK cascade ile pivot temizlenir)
--   • Sitemap: /sitemap-tags.xml route da PHP tarafında siliniyor
--   • Post arşiv URL'leri: /etiket/{slug} 404'e düşer — sonradan 301 yönlendirme
--     gerekirse admin/yonlendirmeler ile manuel ayarlanabilir.

DROP TABLE IF EXISTS post_tag;
DROP TABLE IF EXISTS tags;
