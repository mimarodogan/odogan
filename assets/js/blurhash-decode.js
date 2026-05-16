// blurhash-decode.js — img[data-blurhash] elementleri için bulanık placeholder.
// Tier 5 feature 3.2. Canvas üzerinde decode → CSS background → img load sonrası fade.
//
// Çalışma akışı:
//   1. Her img.has-blurhash içeren <picture> wrapper'ında konumlandır
//   2. BlurHash string → 32x32 RGB pixel array decode
//   3. Canvas → toDataURL → wrapper background-image
//   4. img.load → wrapper.classList.add('blurhash-loaded') → CSS fade-out
//
// Decode algoritması: kornrunner/blurhash JS port'u — küçük, dependency-free.
// http://blurha.sh/ — Algoritma referansı.
(function () {
    'use strict';

    // ─── BlurHash Decode (kornrunner/blurhash JS portu) ──────────────────────
    const DIGIT_CHARSET =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';

    const decode83 = (str, from, to) => {
        let v = 0;
        for (let i = from; i < to; i++) {
            v = v * 83 + DIGIT_CHARSET.indexOf(str[i]);
            if (v < 0) return -1;
        }
        return v;
    };

    const srgbToLinear = v => {
        const s = v / 255;
        return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };

    const linearTosRGB = v => {
        const s = Math.max(0, Math.min(1, v));
        return s <= 0.0031308
            ? Math.round(s * 12.92 * 255 + 0.5)
            : Math.round((1.055 * Math.pow(s, 1 / 2.4) - 0.055) * 255 + 0.5);
    };

    const signPow = (v, e) => Math.sign(v) * Math.pow(Math.abs(v), e);

    const decodeAC = (value, maxVal) => {
        const quantR = Math.floor(value / (19 * 19));
        const quantG = Math.floor(value / 19) % 19;
        const quantB = value % 19;
        return [
            signPow((quantR - 9) / 9, 2.0) * maxVal,
            signPow((quantG - 9) / 9, 2.0) * maxVal,
            signPow((quantB - 9) / 9, 2.0) * maxVal,
        ];
    };

    const decodeDC = value => [
        srgbToLinear((value >> 16) & 0xff),
        srgbToLinear((value >> 8) & 0xff),
        srgbToLinear(value & 0xff),
    ];

    const decode = (blurhash, width, height, punch) => {
        punch = punch || 1;
        if (blurhash.length < 6) return null;
        const sizeFlag = decode83(blurhash, 0, 1);
        const numY = Math.floor(sizeFlag / 9) + 1;
        const numX = (sizeFlag % 9) + 1;
        if (blurhash.length !== 4 + 2 * numX * numY) return null;
        const quantMax = decode83(blurhash, 1, 2);
        const maxVal = (quantMax + 1) / 166;
        const colors = new Array(numX * numY);
        colors[0] = decodeDC(decode83(blurhash, 2, 6));
        for (let i = 1; i < colors.length; i++) {
            colors[i] = decodeAC(decode83(blurhash, 4 + 2 * i, 6 + 2 * i), maxVal * punch);
        }
        const bytesPerRow = width * 4;
        const pixels = new Uint8ClampedArray(bytesPerRow * height);
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                let r = 0,
                    g = 0,
                    b = 0;
                for (let j = 0; j < numY; j++) {
                    for (let ix = 0; ix < numX; ix++) {
                        const basis =
                            Math.cos((Math.PI * x * ix) / width) *
                            Math.cos((Math.PI * y * j) / height);
                        const c = colors[ix + j * numX];
                        r += c[0] * basis;
                        g += c[1] * basis;
                        b += c[2] * basis;
                    }
                }
                const idx = 4 * x + y * bytesPerRow;
                pixels[idx] = linearTosRGB(r);
                pixels[idx + 1] = linearTosRGB(g);
                pixels[idx + 2] = linearTosRGB(b);
                pixels[idx + 3] = 255;
            }
        }
        return pixels;
    };

    // ─── DOM Bağlantısı ──────────────────────────────────────────────────────
    const PLACEHOLDER_W = 32;
    const PLACEHOLDER_H = 32;

    const hashToDataUrl = hash => {
        try {
            const pixels = decode(hash, PLACEHOLDER_W, PLACEHOLDER_H);
            if (!pixels) return null;
            const canvas = document.createElement('canvas');
            canvas.width = PLACEHOLDER_W;
            canvas.height = PLACEHOLDER_H;
            const ctx = canvas.getContext('2d');
            const imageData = ctx.createImageData(PLACEHOLDER_W, PLACEHOLDER_H);
            imageData.data.set(pixels);
            ctx.putImageData(imageData, 0, 0);
            return canvas.toDataURL('image/png');
        } catch (e) {
            return null;
        }
    };

    const attach = img => {
        if (!img || img.__bhDone) return;
        img.__bhDone = true;
        const hash = img.getAttribute('data-blurhash');
        if (!hash) return;
        const dataUrl = hashToDataUrl(hash);
        if (!dataUrl) return;
        // Picture wrapper'a background bas
        const picture = img.closest('picture') || img.parentElement;
        if (!picture) return;
        picture.style.backgroundImage = `url(${dataUrl})`;
        picture.style.backgroundSize = 'cover';
        picture.style.backgroundPosition = 'center';
        picture.classList.add('blurhash-host');

        const markLoaded = () => picture.classList.add('blurhash-loaded');
        if (img.complete && img.naturalWidth > 0) {
            markLoaded();
        } else {
            img.addEventListener('load', markLoaded, { once: true });
            img.addEventListener('error', markLoaded, { once: true });
        }
    };

    const run = () => {
        const imgs = document.querySelectorAll('img[data-blurhash]');
        imgs.forEach(img => attach(img));
    };

    // Lazy-init: viewport-yakın görseller önce, observer ile yenileri yakala
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    // MutationObserver — sayfaya sonradan eklenen img'leri de yakala (örn. lightbox)
    if (typeof MutationObserver === 'function') {
        const obs = new MutationObserver(muts => {
            for (const mut of muts) {
                for (const node of mut.addedNodes) {
                    if (node.nodeType !== 1) continue;
                    if (node.matches && node.matches('img[data-blurhash]')) {
                        attach(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('img[data-blurhash]').forEach(img => attach(img));
                    }
                }
            }
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }
})();
