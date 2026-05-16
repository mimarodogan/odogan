// Otorite Yayin — Post editör auto-save
// Sidebar'daki .pe-autosave kutusundan URL + CSRF token okur,
// editor.js'in textarea#rich-body'sini ve title/excerpt input'larını izler.
// 30 saniyede bir POST JSON ile sunucuya gönderir. localStorage fallback'i var.

(function () {
    'use strict';

    const card = document.querySelector('.pe-autosave');
    if (!card) return;

    const url = card.getAttribute('data-autosave-url');
    const csrf = card.getAttribute('data-autosave-csrf');
    if (!url || !csrf) return;

    const statusEl = document.getElementById('pe-autosave-status');
    const labelEl = statusEl ? statusEl.querySelector('.label') : null;
    const dotEl = statusEl ? statusEl.querySelector('.dot') : null;

    const titleEl = document.querySelector('input[name="title"]');
    const bodyEl = document.getElementById('rich-body');
    const excerptEl = document.querySelector('textarea[name="excerpt"], input[name="excerpt"]');

    if (!titleEl || !bodyEl) return;

    const lsKey = `autosave:${url}`;
    let lastSentHash = '';
    let pending = null;
    const INTERVAL = 30000; // 30 saniye

    const setStatus = (text, glyph) => {
        if (labelEl) labelEl.textContent = text;
        if (dotEl && glyph) dotEl.textContent = glyph;
    };

    const payload = () => ({
        title: (titleEl.value || '').trim(),
        body: (bodyEl.value || '').trim(),
        excerpt: excerptEl ? (excerptEl.value || '').trim() : '',
    });

    const fingerprint = p => {
        // basit hash: length + 32-char head/tail; performant
        return `${p.title.length}:${p.body.length}:${p.excerpt.length}|${p.title.slice(0, 32)}|${p.body.slice(0, 32)}|${p.body.slice(-32)}`;
    };

    const fmtTime = d => {
        const pad = n => (n < 10 ? `0${n}` : `${n}`);
        return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };

    const persistLocal = p => {
        try {
            localStorage.setItem(lsKey, JSON.stringify({ p, t: Date.now() }));
        } catch (e) {
            /* quota or disabled */
        }
    };

    const restoreLocal = () => {
        try {
            const raw = localStorage.getItem(lsKey);
            if (!raw) return;
            const data = JSON.parse(raw);
            if (!data || !data.p) return;
            // Çok eski (24h+) ise gösterme
            if (Date.now() - (data.t || 0) > 86400000) return;
            // Sayfada zaten içerik varsa overwrite etme — sadece bildir
            if ((bodyEl.value || '').trim() !== '' || (titleEl.value || '').trim() !== '') return;
            // Kullanıcıya sor
            if (
                confirm(
                    'Bu yazı için 24 saat içinde kaydedilmiş otomatik bir taslak var. Yüklemek ister misiniz?'
                )
            ) {
                const { title, body, excerpt } = data.p;
                titleEl.value = title || '';
                bodyEl.value = body || '';
                if (excerptEl) excerptEl.value = excerpt || '';
                // Editor.js textarea değişikliğini görmüyor olabilir — event tetikle
                bodyEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } catch (e) {
            /* parse error */
        }
    };

    const tick = async () => {
        const p = payload();
        // Boş ise gönderme
        if (p.title === '' && p.body === '') {
            setStatus('Boş — kayıt yapılmadı', '◌');
            return;
        }
        const fp = fingerprint(p);
        if (fp === lastSentHash) {
            // Değişmemiş
            return;
        }

        // localStorage hep güncelle (offline güvencesi)
        persistLocal(p);

        setStatus('Kaydediliyor…', '⌛');

        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('title', p.title);
        fd.append('body', p.body);
        fd.append('excerpt', p.excerpt);

        if (pending) pending.aborted = true;
        const ticket = { aborted: false };
        pending = ticket;

        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                credentials: 'same-origin',
                body: fd,
            });
            let j;
            try {
                j = await r.json();
            } catch (_) {
                j = { ok: false };
            }
            if (ticket.aborted) return;
            if (j && j.ok) {
                lastSentHash = fp;
                if (j.throttled) {
                    setStatus('Throttle (son kayıt < 30sn)', '⏳');
                } else {
                    setStatus(`Kaydedildi · ${fmtTime(new Date())}`, '✓');
                }
            } else {
                setStatus(`Kayıt başarısız (${(j && j.error) || 'hata'})`, '⚠');
            }
        } catch (_) {
            if (ticket.aborted) return;
            setStatus('Ağ hatası · lokalde tutuldu', '⚠');
        }
    };

    // ─── Başlangıç ───────────────────────────────────────────────
    restoreLocal();
    setStatus('Bekleniyor…', '◷');

    // Her 30sn'de bir kontrol et
    setInterval(tick, INTERVAL);

    // Sayfa kapanırken son durumu lokale yaz (kayıp önle)
    window.addEventListener('beforeunload', () => {
        persistLocal(payload());
    });

    // Açıkça manuel "Taslak Olarak Kaydet" submit edilirse, lokali temizle
    const form = document.getElementById('post-form');
    if (form) {
        form.addEventListener('submit', () => {
            try {
                localStorage.removeItem(lsKey);
            } catch (e) {}
        });
    }
})();
