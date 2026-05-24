/* ════════════════════════════════════════════════════════════════════
 * Building Map (Tier 9) — Atelier estetiği
 *
 * #building-map[data-points] üzerinden JSON dizisini okur:
 *   - Leaflet marker'lar OpenStreetMap tile layer üzerinde
 *   - Atelier-stilli popup HTML (cover + yapı tipi + name + meta + CTA)
 *   - Filter chips (yapı tipi bazlı): tıklanan tip map ve listede vurgulanır
 *   - Scroll-jacking yok: tıkla → wheel zoom aktif, mouse out → kapanır
 * ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    let mapInstance = null;
    let markerLayer = null;
    let currentFilter = 'all';

    const escapeHtml = s =>
        String(s).replace(
            /[&<>"']/g,
            m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]
        );

    const buildPopup = p => {
        const { cover, name, location, year, buildingTypeLabel, roleLabel, url } = p;
        const coverHtml = cover
            ? `<img src="${escapeHtml(cover)}" alt="${escapeHtml(name)}" loading="lazy">`
            : '';
        const meta = (location || '') + (year ? ` · ${year}` : '');
        const typeLabel = buildingTypeLabel || roleLabel || '';
        return (
            `<div class="map-popup">${coverHtml}` +
            `<div class="map-popup-body">` +
            `<span class="map-popup-role">${escapeHtml(typeLabel)}</span>` +
            `<h3 class="map-popup-name">${escapeHtml(name)}</h3>` +
            (meta ? `<p class="map-popup-meta">${escapeHtml(meta)}</p>` : '') +
            `<a class="map-popup-cta" href="${escapeHtml(url)}">Projeyi gör →</a>` +
            `</div></div>`
        );
    };

    const isValidPoint = p =>
        typeof p.lat === 'number' && typeof p.lng === 'number' && !isNaN(p.lat) && !isNaN(p.lng);

    const addMarker = p => {
        const ll = [p.lat, p.lng];
        const marker = L.marker(ll, { title: p.name });
        marker._buildingType = p.buildingType;
        marker.bindPopup(buildPopup(p), {
            maxWidth: 260,
            minWidth: 240,
            closeButton: true,
            autoPan: true,
        });
        return marker;
    };

    const applyFilter = (allPoints, type) => {
        if (!mapInstance || !markerLayer) return;
        markerLayer.clearLayers();

        const visiblePoints =
            type === 'all' ? allPoints : allPoints.filter(p => p.buildingType === type);

        const bounds = L.latLngBounds([]);
        visiblePoints.forEach(p => {
            if (!isValidPoint(p)) return;
            bounds.extend([p.lat, p.lng]);
            const marker = L.marker([p.lat, p.lng], { title: p.name });
            marker.bindPopup(buildPopup(p), { maxWidth: 260, minWidth: 240 });
            markerLayer.addLayer(marker);
        });

        if (bounds.isValid()) {
            mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 10 });
        }
    };

    const bindFilters = points => {
        const chips = document.querySelectorAll('.map-chip');
        const listItems = document.querySelectorAll('.map-list-item');
        if (!chips.length) return;

        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                // 'data-type' birincil, eski 'data-role' geriye uyumluluk için
                const type =
                    chip.getAttribute('data-type') || chip.getAttribute('data-role') || 'all';
                currentFilter = type;

                // Chip durumu — class + aria-pressed senkron
                chips.forEach(c => {
                    c.classList.remove('is-active');
                    c.setAttribute('aria-pressed', 'false');
                });
                chip.classList.add('is-active');
                chip.setAttribute('aria-pressed', 'true');

                // Marker'ları filtrele
                applyFilter(points, type);

                // Liste kartlarını dim et
                listItems.forEach(item => {
                    const itemType =
                        item.getAttribute('data-type') || item.getAttribute('data-role') || '';
                    if (type === 'all' || itemType === type) {
                        item.classList.remove('is-dim');
                    } else {
                        item.classList.add('is-dim');
                    }
                });
            });
        });
    };

    const init = () => {
        const el = document.getElementById('building-map');
        if (!el) {
            console.warn('[map] #building-map elementi yok');
            return;
        }
        if (typeof L === 'undefined') {
            console.error('[map] Leaflet yüklenmedi (L undefined) — CSP/adblock?');
            return;
        }
        if (el.dataset.initialized === '1') return;
        el.dataset.initialized = '1';

        let points;
        try {
            points = JSON.parse(el.getAttribute('data-points') || '[]');
        } catch (e) {
            console.warn('[map] invalid data-points', e);
            return;
        }
        console.log(`[map] ${points.length} nokta alındı`, points);

        // Loading indicator'ı kaldır
        const loading = el.querySelector('.map-loading');
        if (loading) loading.remove();

        const fallback = [39.0, 35.0]; // Türkiye merkez
        mapInstance = L.map(el, {
            center: fallback,
            zoom: 6,
            scrollWheelZoom: false,
            zoomControl: true,
            attributionControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap',
        }).addTo(mapInstance);

        markerLayer = L.layerGroup().addTo(mapInstance);

        if (!points.length) return;

        const bounds = L.latLngBounds([]);
        let added = 0,
            skipped = 0;
        points.forEach(p => {
            if (!isValidPoint(p)) {
                console.warn('[map] geçersiz koordinat — atlandı:', p);
                skipped++;
                return;
            }
            bounds.extend([p.lat, p.lng]);
            added++;
            addMarker(p).addTo(markerLayer);
        });

        if (bounds.isValid()) {
            mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 9 });
        }
        console.log(`[map] ${added} marker eklendi, ${skipped} atlandı`);

        // Scroll-jacking engelle — sadece kullanıcı tıklarsa wheel zoom
        mapInstance.on('click focus', () => mapInstance.scrollWheelZoom.enable());
        mapInstance
            .getContainer()
            .addEventListener('mouseleave', () => mapInstance.scrollWheelZoom.disable());

        // Filter chip bağla
        bindFilters(points);
    };

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
