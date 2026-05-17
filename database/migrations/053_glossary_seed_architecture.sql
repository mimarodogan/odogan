-- ════════════════════════════════════════════════════════════════════
--  053_glossary_seed_architecture.sql
--  Mimari sözlük starter pack — 20 temel terim.
--
--  Idempotent: INSERT IGNORE — slug çakışırsa atlar, eski terimleri
--  ezmez. Tekrar tekrar çalıştırılabilir.
--
--  Düzeltme için: admin /admin/sozluk üzerinden terim/tanım/alias
--  düzenle ya da sil. Aliases'lar virgülle ayrılır ve Türkçe çekim
--  ekleri için yaygın formları içerir (silüetin, silüeti, vb.).
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `glossary` (`slug`, `term`, `definition`, `category`, `aliases`, `is_active`) VALUES

-- ─── KAVRAM & TASARIM ─────────────────────────────────────────────────
('siluet', 'Silüet',
'Bir yerleşimin gökyüzü ile kesişen dış hattının oluşturduğu kolektif görüntüdür. Tarihi yarımadanın silüeti gibi tanımlamalarda kullanılan silüet, sadece bina yüksekliklerinin değil, yapı tipolojilerinin, çatı formlarının ve topografya ilişkisinin toplam ifadesidir.',
'Kentsel Tasarım', 'silüetin, silüeti, silüete, skyline', 1),

('gabari', 'Gabari',
'Bir yapının üst sınır yüksekliği veya konturunu belirleyen, imar planında ya da yönetmelikte tanımlı sınırdır. Bina cephesinin yatay-düşey hareket toleransını belirler, kentsel doku tutarlılığı için kritik bir parametredir.',
'Mevzuat & İmar', 'gabarisi, gabariye, gabarinin', 1),

('emsal', 'Emsal (KAKS)',
'Parselin imar planındaki arsa alanına oranla inşa edilebilecek toplam yapı inşaat alanını belirleyen katsayıdır. <strong>KAKS</strong> (Kat Alanı Katsayısı) olarak da geçer; örneğin emsal=2.00 ise 500 m² arsada 1000 m² toplam inşaat hakkı vardır.',
'Mevzuat & İmar', 'kaks, kat alanı katsayısı, emsali, emsalin', 1),

('taks', 'TAKS',
'<strong>Taban Alanı Katsayısı</strong> — parselin imar planındaki arsa alanına oranla yapının zemine oturabileceği maksimum taban alanını belirler. TAKS=0.40 ise 500 m² arsada zeminde en fazla 200 m² taban alanı kullanılabilir.',
'Mevzuat & İmar', 'taban alanı katsayısı, taksin, taksi', 1),

('referans-bina', 'Referans Bina',
'Yeni yapının çevre kütlesel ölçeği, yüksekliği veya cephe karakterinde dayanak alacağı, çoğunlukla aynı bağlam içindeki tarihi veya nitelikli bir yapı. İmar plan notları ve koruma kurulu kararlarında "referans bina" tanımı sıkça geçer — bu yöntem yeni yapının çevresine entegre olmasını sağlar.',
'Mevzuat & İmar', 'referans yapı, referans binası, kontrol binası', 1),

('doluluk-bosluk', 'Doluluk-Boşluk Oranı',
'Bir cephe ya da kentsel dokuda dolu (yapı, masif) ve boş (açık alan, avlu, pencere) yüzeylerin oranıdır. Klasik Osmanlı dokusunda %30-40 boşluk, modern apartman cephelerinde ise %60+ cam yüzey örnek verilebilir.',
'Cephe & Tasarım', 'doluluk-boşluk, dolu boş oranı, solid-void', 1),

-- ─── YAPI ELEMANI & CEPHE ─────────────────────────────────────────────
('brise-soleil', 'Brise-Soleil',
'Fransızca "güneş kıran" anlamına gelen, cepheye sabitlenmiş yatay veya dikey güneş kontrolü elemanlarıdır. Le Corbusier''nin Chandigarh ve Marsilya konutlarında popülerleştirdiği bu eleman, hem güneş ışınımını kontrol eder hem cepheye plastik karakter kazandırır.',
'Yapı Elemanı', 'brise soleil, güneş kırıcı, güneş kesici', 1),

('sagir-cephe', 'Sağır Cephe',
'Pencere, kapı veya açıklık içermeyen, tamamen masif olan duvar yüzeyidir. Genellikle parsel sınırına bitişik komşu duvar olarak ya da tasarımda akustik/ısı performans veya cephe ritmi amacıyla kullanılır.',
'Yapı Elemanı', 'sağır duvar, kör cephe, sağır cephesi', 1),

('konsol', 'Konsol',
'Bir ucu mesnede bağlı, diğer ucu serbest olan, bina yüzeyinden dışarı taşan strüktürel veya dekoratif elemandır. Balkon, çıkma cephe, sundurma gibi öğelerin temelini oluşturur. Mühendislik açısından moment alan, hesabı kritik bir yapı elemanıdır.',
'Strüktür', 'konsol kiriş, çıkma, kantilever, balkon konsolu', 1),

('strikturel-cephe', 'Strüktürel Cephe',
'Yapı taşıyıcı sisteminin (kolon, perde, kafes) bina dışından doğrudan okunabildiği cephe yaklaşımıdır. Ülker Kaya''nın betonarme kafes cepheli yapıları, Foster''ın Hearst Tower''ı bu yaklaşımın örnekleridir.',
'Cephe & Tasarım', 'strüktürel cephe, taşıyıcı cephe, exposed structure', 1),

('giydirme-cephe', 'Giydirme Cephe',
'Bina taşıyıcı sistemine bağlı, ancak yapısal yük taşımayan, sadece kendi ağırlığını ve dış etkileri (rüzgar, yağmur) taşıyan hafif cephe sistemidir. Cam ve alüminyum profillerle kurulan modern ofis kuleleri tipik örnekleridir.',
'Cephe & Tasarım', 'curtain wall, giydirme cephesi, cam cephe', 1),

('kanopisi', 'Kanopi',
'Bina girişi veya yaya geçişi üzerinde, ana kütleden bağımsız olarak çıkma yapan, çoğunlukla hafif strüktürlü örtü elemanıdır. Gölgelendirme + yağmur koruması + giriş vurgusu işlevlerini birleştirir.',
'Yapı Elemanı', 'kanopisi, sundurma, baldaken, marquise', 1),

-- ─── STRÜKTÜR & MALZEME ───────────────────────────────────────────────
('brut-beton', 'Brüt Beton',
'Kalıbından çıktığı haliyle, üzeri sıvanmadan veya boyanmadan bırakılan betonarme yüzeydir. Le Corbusier''nin "béton brut" terimi Brutalizm akımının doğmasına neden olmuştur. Marsilya Unité d''Habitation, Sirkeci Adliyesi örnek verilebilir.',
'Strüktür', 'beton brüt, raw concrete, béton brut, expose beton', 1),

('mukarnas', 'Mukarnas',
'Selçuklu ve Osmanlı mimarisinin karakteristik bir mimari süs elemanıdır — geometrik prizmatik birimlerin üst üste bindirilerek oluşturduğu, çoğunlukla mihrap, taç kapı ve sütun başlıklarında görülen üç boyutlu bal peteği desenidir.',
'Restorasyon & Tarihi', 'mukarnaslı, stalaktit süsleme, honeycomb vault', 1),

('eyvan', 'Eyvan',
'Üç tarafı duvarla çevrili, bir tarafı tamamen açık olan, geleneksel İslam mimarisinin temel mekan birimlerinden biridir. Sasani döneminden başlayıp Selçuklu, Türk-İslam ve Osmanlı dönemlerinde camii, medrese, kervansaray planlarında merkezi rol oynar.',
'Restorasyon & Tarihi', 'eyvanlı, iwan, eyvanı', 1),

-- ─── STİL & AKIM ──────────────────────────────────────────────────────
('brutalism', 'Brutalism',
'1950-70 arası yaygınlaşan, ham yapı malzemelerini (özellikle brüt beton) gizlemeden ifade eden mimari akımdır. Le Corbusier''nin geç dönem yapıları, Trellick Tower (Londra), Boston Şehir Konseyi binası gibi kütle ağırlıklı, ifadeci yapılarla tanımlanır.',
'Stil & Akım', 'brutalist, brutalizm, brütalist', 1),

('bauhaus', 'Bauhaus',
'1919-1933 arası Almanya''da, Walter Gropius önderliğinde kurulan tasarım ve mimarlık okulu ile bu okulun savunduğu işlevsellik-form bütünlüğüne dayalı modernist hareket. "Form follows function" ilkesinin kurumsallaştığı yapıdır.',
'Stil & Akım', 'bauhaus okulu, modernizm, modernist hareket', 1),

('vernakular', 'Vernaküler Mimari',
'Resmi mimari eğitimi olmayan, geleneksel bilgi ve yerel malzemelerle inşa edilen halk mimarisidir. Anadolu''nun taş köy evleri, Karadeniz''in serenderi, Mardin''in kireç taşı evleri bu kategoride değerlendirilir; akademik mimarlık için zengin bir referans kaynağıdır.',
'Stil & Akım', 'vernaküler, halk mimarisi, yerel mimari', 1),

-- ─── PRATİK & OPERASYONEL ─────────────────────────────────────────────
('parametrik', 'Parametrik Tasarım',
'Tasarımın geometri ve davranışının sabit form yerine değişken parametreler (kullanıcı yoğunluğu, güneş açısı, akustik) ile tanımlandığı yaklaşımdır. Grasshopper + Rhino, Dynamo + Revit gibi yazılımlarla popülerleşmiş, Zaha Hadid Architects yapılarında en görünür örneklerini bulur.',
'Tasarım Yaklaşımı', 'parametric design, parametrik mimari, algoritmik tasarım', 1),

('biyomimikri', 'Biyomimikri',
'Tasarım çözümlerinin doğadan, canlı organizmaların evrimsel stratejilerinden ilham alınarak geliştirildiği yaklaşımdır. Eastgate Centre''ın termit yuvasından esinlenen pasif soğutma sistemi, Stefano Boeri''nin Bosco Verticale''si bu yaklaşımın mimarideki örnekleridir.',
'Tasarım Yaklaşımı', 'biyomimikri, biomimicry, biyomimetik tasarım', 1);

-- ════════════════════════════════════════════════════════════════════
-- Sözlük cache'i temizle ki AutoGlossaryLink yeni terimleri tarama
-- listesine alsın. Cache tag: 'glossary' (053'ten sonra otomatik geçerli).
-- ════════════════════════════════════════════════════════════════════
