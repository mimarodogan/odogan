#!/usr/bin/env node
/**
 * Odogan CMS — Otomatik Ekran Görüntüsü Pipeline'ı
 *
 * Playwright ile odogan.com.tr'den 5 sayfayı 2 viewport'ta çeker
 * (desktop 1440x900 @2x + mobile 390x844 @3x), Sharp ile WebP'ye
 * optimize edip docs/screenshots/ altına yazar.
 *
 * Kullanım: node scripts/screenshots.js  (veya:  npm run screenshots)
 *
 * Cookie banner otomatik gizlenir, screenshot temiz çıkar.
 */

import { chromium } from 'playwright';
import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const OUT_DIR = path.join(ROOT, 'docs/screenshots');
const BASE = process.env.SCREENSHOT_BASE || 'https://odogan.com.tr';

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 900, dpr: 2 },
    { name: 'mobile',  width: 390,  height: 844, dpr: 3 },
];

// ─── Sitemap'ten ilk yayınlanan post URL'sini çek ──────────────────────────
async function findFirstPostUrl() {
    try {
        const resp = await fetch(`${BASE}/sitemap-posts.xml`);
        const xml = await resp.text();
        const m = xml.match(/<loc>([^<]+)<\/loc>/);
        return m ? m[1] : null;
    } catch {
        return null;
    }
}

// ─── Sitemap'ten ilk proje URL'sini çek ────────────────────────────────────
async function findFirstProjectUrl() {
    try {
        const resp = await fetch(`${BASE}/sitemap-projects.xml`);
        const xml = await resp.text();
        const m = xml.match(/<loc>([^<]+)<\/loc>/);
        return m ? m[1] : null;
    } catch {
        return null;
    }
}

// ─── Cookie banner + diğer overlay'leri gizle ──────────────────────────────
const HIDE_OVERLAYS_SCRIPT = `
    // Cookie consent banner
    const banner = document.getElementById('cookie-consent');
    if (banner) banner.remove();
    document.body.classList.remove('cookie-consent-open');
    // Flash mesajlar
    document.querySelectorAll('.flash').forEach(el => el.remove());
    // Skip-link
    document.querySelectorAll('.skip-link').forEach(el => el.remove());
    // LocalStorage'a consent yazılı say (tekrar açılmasın)
    try {
        localStorage.setItem('odogan:cookie-consent', JSON.stringify({
            granted: true,
            timestamp: Date.now()
        }));
    } catch {}
`;

// ─── Tek bir sayfa screenshot'ı çek + WebP optimize ────────────────────────
async function captureOne(browser, slug, url, viewport, fullPage = false) {
    const ctx = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        deviceScaleFactor: viewport.dpr,
        locale: 'tr-TR',
        timezoneId: 'Europe/Istanbul',
        userAgent:
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 ' +
            '(KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    });

    // Cookie consent'i pre-set et (banner açılmasın)
    await ctx.addInitScript(() => {
        try {
            localStorage.setItem('odogan:cookie-consent', JSON.stringify({
                granted: true,
                timestamp: Date.now(),
            }));
        } catch {}
    });

    const page = await ctx.newPage();
    await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    await page.evaluate(HIDE_OVERLAYS_SCRIPT);

    // Web font + lazy image yüklensin
    await page.waitForTimeout(1500);

    // Tüm lazy resimlerin yüklenmesini tetikle
    await page.evaluate(() => {
        document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
            img.loading = 'eager';
        });
        window.scrollTo(0, 0);
    });
    await page.waitForTimeout(1000);

    const pngBuffer = await page.screenshot({
        type: 'png',
        fullPage,
        omitBackground: false,
    });

    const outPath = path.join(OUT_DIR, `${slug}-${viewport.name}.webp`);
    const outInfo = await sharp(pngBuffer)
        .webp({ quality: 85, effort: 6 })
        .toFile(outPath);

    const sizeKb = (outInfo.size / 1024).toFixed(1);
    console.log(
        `  ✓ ${slug.padEnd(10)} ${viewport.name.padEnd(8)} ` +
            `${viewport.width}x${viewport.height} @${viewport.dpr}x → ${sizeKb} KB`
    );

    await ctx.close();
}

// ─── Ana akış ──────────────────────────────────────────────────────────────
async function main() {
    if (!fs.existsSync(OUT_DIR)) {
        fs.mkdirSync(OUT_DIR, { recursive: true });
    }

    console.log(`Odogan Screenshot Pipeline`);
    console.log('='.repeat(60));
    console.log(`Base URL: ${BASE}`);
    console.log(`Output  : ${path.relative(ROOT, OUT_DIR)}/`);
    console.log('');
    console.log('Sitemap\'ten post + proje URL\'leri çekiliyor...');

    const [postUrl, projectUrl] = await Promise.all([
        findFirstPostUrl(),
        findFirstProjectUrl(),
    ]);

    const pages = [
        { slug: 'home',     url: `${BASE}/` },
        ...(postUrl    ? [{ slug: 'post',     url: postUrl }]    : []),
        { slug: 'projects', url: `${BASE}/projeler` },
        ...(projectUrl ? [{ slug: 'project',  url: projectUrl }] : []),
        { slug: 'map',      url: `${BASE}/harita` },
        { slug: 'glossary', url: `${BASE}/sozluk` },
    ];

    console.log(`\nÇekilecek sayfalar (${pages.length}):`);
    pages.forEach((p) => console.log(`  • ${p.slug.padEnd(10)} ${p.url}`));
    console.log('');

    const browser = await chromium.launch({ headless: true });

    for (const page of pages) {
        console.log(`▸ ${page.slug}`);
        for (const vp of VIEWPORTS) {
            try {
                await captureOne(browser, page.slug, page.url, vp);
            } catch (err) {
                console.error(`  ✖ ${page.slug} (${vp.name}): ${err.message}`);
            }
        }
    }

    await browser.close();

    // Sonuç özeti
    const files = fs.readdirSync(OUT_DIR).filter((f) => f.endsWith('.webp'));
    const totalKb = files.reduce(
        (acc, f) => acc + fs.statSync(path.join(OUT_DIR, f)).size,
        0
    ) / 1024;

    console.log('');
    console.log('─'.repeat(60));
    console.log(`✓ ${files.length} dosya · toplam ${totalKb.toFixed(1)} KB`);
    console.log(`  Dizin: ${OUT_DIR}`);
    console.log('');
}

main().catch((err) => {
    console.error('Pipeline hatası:', err);
    process.exit(1);
});
