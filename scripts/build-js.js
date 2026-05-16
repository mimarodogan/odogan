#!/usr/bin/env node
/**
 * Odogan CMS — JS Build Pipeline
 * 1. Her assets/js/*.js dosyasını (*.min.js hariç) Terser ile minify et
 * 2. assets/js/*.min.js + *.min.js.map yaz
 *    PHP AssetMinifier *.min.js bulunca kendi rebuild'ini atlar
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { minify } from 'terser';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const JS_DIR = path.join(ROOT, 'assets/js');

// Source map URL prefix — production server'daki public path
const SOURCE_MAP_URL_PREFIX = '/assets/js/';

const TERSER_OPTIONS = (srcName) => ({
    compress: {
        drop_console: false, // console.warn/log bırakılsın (debug)
        drop_debugger: true,
        dead_code: true,
        collapse_vars: true,
        reduce_vars: true,
        passes: 2,
    },
    mangle: {
        toplevel: false, // global değişkenler güvenli olsun
        reserved: [
            // PHP tarafından çağrılan global fonksiyon adları
            'initBuildingMap',
            'initGalleryLightbox',
            'initTeamBuilder',
            'openMediaPickerImpl',
        ],
    },
    format: {
        comments: false, // tüm yorumlar kaldırılsın
        ascii_only: true, // Türkçe karakter sorunlarını önle
    },
    sourceMap: {
        filename: srcName, // kaynak dosya adı (.js)
        url: srcName.replace(/\.js$/, '.min.js.map'), // .min.js dosyasının sonuna eklenen comment
        includeSources: true, // kaynak kodu map'e göm
    },
});

async function buildFile(srcPath) {
    const name = path.basename(srcPath);
    const minName = name.replace(/\.js$/, '.min.js');
    const mapName = name.replace(/\.js$/, '.min.js.map');
    const outPath = path.join(JS_DIR, minName);
    const mapPath = path.join(JS_DIR, mapName);

    const src = fs.readFileSync(srcPath, 'utf8');
    const sizeBefore = src.length;

    let result;
    try {
        result = await minify({ [name]: src }, TERSER_OPTIONS(name));
    } catch (err) {
        console.error(`  ✖  ${name}: ${err.message}`);
        return { name, ok: false };
    }

    const minified = result.code ?? '';
    fs.writeFileSync(outPath, minified, 'utf8');

    // Source map'i ayrı dosyaya yaz
    if (result.map) {
        // map JSON'unu parse edip sourceRoot'u ekle
        const mapObj = JSON.parse(result.map);
        mapObj.sourceRoot = SOURCE_MAP_URL_PREFIX;
        fs.writeFileSync(mapPath, JSON.stringify(mapObj), 'utf8');
    }

    const sizeAfter = minified.length;
    const saved = sizeBefore - sizeAfter;
    const pct = Math.round((saved / sizeBefore) * 100);
    const mapSize = result.map ? ` + ${(result.map.length / 1024).toFixed(1)}KB map` : '';
    console.log(
        `  ${name.padEnd(32)} ${(sizeBefore / 1024).toFixed(1).padStart(6)} KB` +
            ` → ${(sizeAfter / 1024).toFixed(1).padStart(5)} KB  (−${pct}%)${mapSize}`
    );
    return { name, ok: true, sizeBefore, sizeAfter };
}

async function main() {
    console.log('Odogan JS Build Pipeline (Terser + Source Maps)');
    console.log('='.repeat(50));

    const files = fs
        .readdirSync(JS_DIR)
        .filter((f) => f.endsWith('.js') && !f.endsWith('.min.js'))
        .sort()
        .map((f) => path.join(JS_DIR, f));

    console.log(`\nMinifying ${files.length} files...\n`);

    let totalBefore = 0;
    let totalAfter = 0;
    let errors = 0;

    for (const f of files) {
        const r = await buildFile(f);
        if (r.ok) {
            totalBefore += r.sizeBefore;
            totalAfter += r.sizeAfter;
        } else {
            errors++;
        }
    }

    const saved = totalBefore - totalAfter;
    const pct = Math.round((saved / totalBefore) * 100);
    console.log('\n' + '─'.repeat(50));
    console.log(
        `  Toplam  ${(totalBefore / 1024).toFixed(1)} KB` +
            ` → ${(totalAfter / 1024).toFixed(1)} KB` +
            `  (−${(saved / 1024).toFixed(1)} KB, −${pct}%)`
    );
    if (errors > 0) {
        console.error(`\n  ⚠  ${errors} dosyada hata — yukarıyı kontrol et`);
        process.exit(1);
    }
    console.log('\n✓ JS build complete\n');
}

main().catch((err) => {
    console.error('Build failed:', err);
    process.exit(1);
});
