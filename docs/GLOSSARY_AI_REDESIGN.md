# Sözlük AI Sistemi — Yeniden Tasarım (v2)

> **Status**: Design accepted, implementation pending.
> **Author**: Osman Doğan + Claude (brainstorming session, 2026-05-25)
> **Supersedes**: Q1-Q7 + MC paketinin backend kısmı (DB+UI korunur)

---

## 1. Problem Statement

Mevcut sistem (Q1-Q7 + MC) AI ile sözlük üretiminde **kontekst kayması (drift)** sorununu çözemedi. Canlı örnek:

- `/sozluk/doseme` → "Yapı Elemanı" bağlamı seçildi
- AI üretti: *"Döşeme, yüzeylere uygulanan koruyucu/estetik kaplama sistemi…"* (FLOORING)
- Olması gereken: *"Döşeme, kolon ve kirişler üzerine oturan yatay taşıyıcı strüktürel plak (SLAB)…"*

**Kök neden**: AI'ın eğitim verisinde "döşeme" kelimesi en yaygın olarak "yer döşemesi" anlamında geçer (perakende/tadilat içeriği). Disambiguation hint inject etmek bu istatistiksel ağırlığı yenmiyor. Self-review Haiku da aynı kör noktaya sahip — drift'i kendisi yakalamıyor.

**Hedef**: AI'ın çıktısını **gerçek kaynak metinlerine zorunlu olarak bağlamak** — hallüsinasyonu mekanik olarak imkansızlaştırmak.

---

## 2. Understanding Summary

- **Ne**: RAG-tabanlı, 3-aşamalı sözlük üretim pipeline'ı (Librarian → Writer → Judge)
- **Neden**: Drift mekanik olarak çözümlenir; SEO'da gerçek referans URL bonusu kazanılır
- **Kim için**: Osman (editör) — günde 1-2 terim üretir, hepsini manuel onaylar
- **Constraint'ler**: cPanel File Manager dışı deploy gücü yok; PHP 8.2+ / MySQL 8.0+; Wikipedia REST API ücretsiz
- **Non-goals**: Auto-publish, multi-AI consensus, web search, sıfırdan rewrite

---

## 3. Decision Log

| # | Karar | Neden | Alternatifler (ret) |
|---|---|---|---|
| **D1** | AI tam tanım yazar + güçlü denetim katmanı | Ölçek hedefi (50+ terim) manuel yazıma uymaz | Hibrit/Mentor (yetersiz), Manuel (yavaş) |
| **D2** | **RAG** — AI yazmadan önce gerçek kaynak okur | Hallüsinasyonu mekanik olarak imkansız kılar; bonus referans URL'leri | Multi-AI consensus (pahalı), Seed-only (yorucu) |
| **D3** | **MVP: Wikipedia TR + EN** (REST API, ücretsiz, anonim) | Hızlı kurulum, ~%70 vakayı çözer; TDK/akademik sonradan | TDK scrape (zahmet), akademik (overkill), web search (kontrolsüz) |
| **D4** | **Librarian + Writer 2-aşamalı AI** | Librarian drift yapsa bile gerçek Wikipedia metni Writer'a inject olur — sonuç anchored | Manual mapping (yorucu), disambig parser (kırılgan) |
| **D5** | **AI judge verifier** (3. AI çağrısı) | Anlamsal anlama yapar; drift'i niteleyebilir; ~$0.005/terim | Citation parse (talimat-bağımlı), embedding (yüzeysel) |
| **D6** | **Tam manuel onay** — verifier skoru sadece rozet | Yeni sistemde güven kazanmadan auto-publish riskli; mevcut `is_active=0 → toggle` korunur | Hibrid (premature), tam auto (güven yok) |
| **D7** | **Manuel kaynak alanı + reddedilen üretim**: Wikipedia yoksa sen URL girersin, hiç URL yoksa AI reddeder | 0 drift garantili; senin uzmanlığını kaynağa çevirir; SEO referans bonusu | Reddet-only (esnek değil), web search (kontrolsüz) |
| **D8** | **Üzerine inşa et**: DB+UI korunur; backend (servisler) değişir; migrasyon 069 ekler | Mevcut yapı %80 uyumlu; deploy hızlı, regresyon riski düşük | Sıfırdan (emek+risk), feature flag (geçici karmaşa) |
| **D9** | **Yan yana A/B**: form'da 2 buton (eski/yeni AI), aynı çıktı yan yana | En pedagojik, en düşük risk, geri dönüş kolay | Canary (yine flag), golden set (kurulum yorucu) |

---

## 4. Architecture

### Yüksek Seviye Diyagram

```
┌─────────────────────────────────────────────────────────────────┐
│  ADMIN FORM (/admin/sozluk/{id}/duzenle)                         │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Term: "Döşeme"                                             │ │
│  │ Bağlam (MC checkbox): [✓] yapı_elemani                     │ │
│  │ Manuel kaynak URL'leri (opsiyonel): [______________]       │ │
│  │                                                            │ │
│  │ [Eski AI ile Üret]    [Yeni AI (RAG) ile Üret] ← A/B BTN  │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────────────┬──────────────────────────────────┘
                               ↓
        ┌──────────────────────────────────────┐
        │  GlossaryRagPipelineService          │
        └──────────────────┬───────────────────┘
                           ↓
   ┌───────────────────────────────────────────────────┐
   │  1. LIBRARIAN AI (Anthropic Haiku ~$0.001)         │
   │  Input:  term + contextTypes + manual_urls         │
   │  Output: { tr_articles: [...], en_articles: [...] }│
   └───────────────────────┬───────────────────────────┘
                           ↓
   ┌───────────────────────────────────────────────────┐
   │  2. WIKIPEDIA FETCHER (parallel HTTP)              │
   │  - tr.wikipedia.org/api/rest_v1/page/summary/...   │
   │  - en.wikipedia.org/api/rest_v1/page/summary/...   │
   │  - Fallback: manual_urls (sen önceden girdiysen)   │
   │  - Hiçbiri yoksa → REJECT, "manuel yaz"            │
   │  Cache: 24h file cache (data/cache/wiki/)          │
   └───────────────────────┬───────────────────────────┘
                           ↓
   ┌───────────────────────────────────────────────────┐
   │  3. WRITER AI (Anthropic Sonnet ~$0.015)           │
   │  Prompt: system + pasajlar + context + term        │
   │  Output: HTML tanım + citation [1][2] cümle sonu   │
   │  Rule: Pasajda olmayan iddia YAZMA                 │
   └───────────────────────┬───────────────────────────┘
                           ↓
   ┌───────────────────────────────────────────────────┐
   │  4. JUDGE AI (Anthropic Sonnet ~$0.005)            │
   │  Input: tanım + pasajlar                           │
   │  Output: { score: 0-100, sentence_map: [...],      │
   │            drift_reason: "...", suggested_fix }    │
   └───────────────────────┬───────────────────────────┘
                           ↓
   ┌───────────────────────────────────────────────────┐
   │  5. KAYDET (Glossary::create)                      │
   │  is_active=0 (draft), quality_score, drift_*       │
   │  source_urls JSON (Wikipedia + manuel)             │
   └───────────────────────┬───────────────────────────┘
                           ↓
   ┌───────────────────────────────────────────────────┐
   │  6. SEN ONAYLA: edit ekranında karşılaştır         │
   │     - Eski AI çıktısı vs Yeni AI çıktısı           │
   │     - Birini seç → "Bunu Kaydet" → is_active=1     │
   └───────────────────────────────────────────────────┘
```

### Servis Yapısı (yeni dosyalar)

| Dosya | Sorumluluk |
|---|---|
| `app/Services/Rag/WikipediaFetcher.php` | TR+EN REST API çağrıları, cache, parser |
| `app/Services/Rag/LibrarianService.php` | "Hangi makaleler?" AI çağrısı (Haiku) |
| `app/Services/Rag/JudgeService.php` | "Tanım kaynaktan destekli mi?" AI çağrısı |
| `app/Services/Rag/GlossaryRagPipeline.php` | Orkestratör: 3 servisi birleştirir |

### Mevcut Dosyalar (modifiye)

| Dosya | Değişiklik |
|---|---|
| `app/Services/AiGlossaryService.php` | `draftWithRag()` yeni method; eski `draft*` korunur (A/B için) |
| `app/Controllers/Admin/GlossaryController.php` | `aiDraft()` `?engine=rag` param; `source_urls` POST kabul |
| `app/Views/admin/glossary/form.php` | "Manuel Kaynak URL'leri" repeater + "Yeni AI" buton |
| `assets/js/glossary-ai.js` | Engine seçimi, side-by-side render |
| `database/migrations/069_glossary_source_urls.sql` | `ADD COLUMN source_urls TEXT NULL` |

---

## 5. Data Model

### Migration 069 (yeni)

```sql
ALTER TABLE glossary
    ADD COLUMN source_urls TEXT NULL DEFAULT NULL
        COMMENT 'JSON array: kullanıcının manuel olarak girdiği fallback kaynak URLs (Wikipedia yoksa)'
        AFTER drift_checked_at,
    ADD COLUMN rag_source_pasajs MEDIUMTEXT NULL DEFAULT NULL
        COMMENT 'JSON: Writer''a inject edilen pasajlar (debug/transparency için)'
        AFTER source_urls,
    ADD COLUMN rag_engine VARCHAR(20) NOT NULL DEFAULT 'legacy'
        COMMENT 'legacy = Q1-Q7 sistemi, rag_v2 = yeni pipeline'
        AFTER rag_source_pasajs;
```

### Mevcut kolonların yeni semantiği

| Kolon | Eski semantik | Yeni semantik |
|---|---|---|
| `context_type` | Self-review drift detection input | Librarian AI'a "hangi Wikipedia makalesi" hint |
| `quality_score` | Self-review skoru | Judge skoru (semantically aynı, daha güvenilir) |
| `drift_flag` | Self-review flag | Judge "<60 destekli" flag |
| `drift_reason` | Self-review açıklama | Judge "şu cümle pasajda yok" |
| `drift_suggested_fix` | Self-review öneri | Judge "şu pasaja göre şöyle düzelt" |

---

## 6. Non-Functional Requirements

### Performance
- Eski: 30-90 sn/terim (chunked AI)
- Yeni: 40-110 sn/terim (+10-20 sn RAG)
- **Kabul edilebilir** (kullanıcı tek terim için bekler)

### Cost
- Eski: ~$0.012/terim
- Yeni: ~$0.021/terim (1.75x)
- 50 terim toplam: ~$1.05 (önemsiz)

### Scale
- Mevcut: 26 terim
- Hedef: 50+ terim (12-18 ay)
- Düşük hacim, scale endişesi yok

### Security
- Mevcut .env API anahtarı yeterli
- Wikipedia anonim (rate limit ~200 req/sn — yeterli)
- Kullanıcının manuel URL'leri sanitize edilir (HTTPS only, allowed domains)

### Reliability
- Wikipedia uptime ~%99.9
- Cache 24 saat (down ise eski sonuç kullan)
- Retry: 2x exponential backoff (1s, 3s)

### Maintenance
- 4 yeni servis (~600 satır)
- Mevcut AiGlossaryService refactor (~200 satır değişim)
- Toplam ~800 satır net artış

---

## 7. Risks

| ID | Risk | Olasılık | Etki | Mitigation |
|---|---|---|---|---|
| **R1** | Librarian yanlış makale önerir, Wikipedia 404 döner | Orta | Orta | Search API fallback; bulunamadıysa manuel URL alanına yönlendir |
| **R2** | A/B süresince eski+yeni 2 sistem geçici karmaşa | Düşük | Düşük | 2 hafta sonra eski kod silinir |
| **R3** | Yeni servislerin Q1-Q7 ile entegrasyon regresyonu | Orta | Yüksek | A/B paralel çalıştığı için eski yine kullanılabilir |
| **R4** | Wikipedia spesifik mimari terimleri yetersiz | Yüksek | Orta | Manuel URL girişi (D7) bu boşluğu kapatır |
| **R5** | Türkçe terimler için EN Wikipedia çevirisi bozuk | Düşük | Orta | Writer prompt'a "Türkçeleştir" direktifi |

---

## 8. Open Questions (implementation aşamasında karar)

1. **Judge senkron mu async mı?** — Senkron (basit, 5 sn ek bekleme); async overkill bu ölçekte
2. **A/B UI tasarımı** — Önce 2 buton, sonuçlar yan yana iki sütunda; "Bunu Seç" butonu her sütunda
3. **Mevcut 26 terim retroactive update?** — Önce A/B test, sonra sen toplu "Yeni AI ile yeniden üret" tetikleyebilirsin (opsiyonel)
4. **Wikipedia rate limit aşılırsa** — Cache+retry yeterli; queue overengineering

---

## 9. Implementation Phases (yüksek seviye)

> Detay implementation plan ayrıca yazılacak (handoff aşaması). Burada sadece faz adımları.

**Faz 1: Foundation (3-4 saat)**
- Migration 069 (source_urls + rag_source_pasajs + rag_engine kolonları)
- WikipediaFetcher servisi (REST API + cache)
- Test: tek bir terim için manual fetch çalışıyor mu

**Faz 2: Pipeline (4-5 saat)**
- LibrarianService (Haiku prompt + JSON output)
- JudgeService (Sonnet prompt + sentence_map)
- GlossaryRagPipeline (3'ünü birleştir)
- Test: "Döşeme" için end-to-end pipeline çalışıyor mu

**Faz 3: Integration (2-3 saat)**
- AiGlossaryService::draftWithRag()
- GlossaryController::aiDraft() engine param
- Form'a "Manuel Kaynak URL'leri" repeater
- Form'a "Yeni AI (RAG)" buton

**Faz 4: A/B UI (2-3 saat)**
- Yan yana karşılaştırma view (eski çıktı + yeni çıktı)
- "Bunu Seç" butonları
- Public sayfada "Kaynaklar: Wikipedia [link]" otomatik gösterim

**Faz 5: Test + Deploy (2 saat)**
- 5 zor terim ile manuel A/B karşılaştırma
- cPanel deploy listesi hazırla
- Cron / cache temizleme

**Toplam tahmin: 13-17 saat geliştirme**

---

## 10. Success Criteria

Sistem **başarılı** sayılır eğer:

- [ ] "Döşeme" + yapı_elemani → çıktıda "slab/floor deck" geçiyor; "flooring/kaplama" geçmiyor
- [ ] "Kemer" + yapı_elemani + tarihsel → çıktıda hem mimari öğe hem Roma/Selçuklu kemerleri
- [ ] 5 zor terim için A/B karşılaştırmada yeni AI ≥4'ünde tercih ediliyor
- [ ] Judge skoru ortalaması ≥75 (mevcut Q5 sisteminde ~60 civarı)
- [ ] Her tanımda en az 2 gerçek Wikipedia URL'i kaynak olarak görünüyor
- [ ] Wikipedia'da olmayan terimlerde "manuel kaynak yok, üretemiyorum" net mesajı

---

## Appendix A — Brainstorming Process

Bu döküman 9 sıralı karar sorusu ile inşa edildi (brainstorming skill). Her soru çoktan seçmeli, kullanıcı seçimi log'a yazıldı. Bkz: Decision Log (Section 3).

---

## Appendix B — Glossary of New Terms

- **RAG**: Retrieval-Augmented Generation — AI üretmeden önce dış kaynaktan ilgili metin çekme tekniği
- **Librarian AI**: Hangi kaynak makalelerin çekileceğini belirleyen AI rolü
- **Writer AI**: Çekilen kaynak metne dayanarak final içerik üreten AI rolü
- **Judge AI**: Üretilen içeriğin kaynaklardan destekli olup olmadığını skorlayan AI rolü
- **Drift**: AI'ın seçilen bağlam dışında bir anlamı yorumlaması (örn. "döşeme" → flooring)
- **Anchored output**: Kaynaklara zorunlu bağlı olduğu için hallüsinasyona uğramayan çıktı
