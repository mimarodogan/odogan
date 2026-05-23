#!/usr/bin/env node
/**
 * Odogan CMS — CSS Build Pipeline
 * 1. Concat source files (same order as PHP AssetMinifier::bundle)
 * 2. PurgeCSS — unused selector removal (PHP views + JS dosyaları taranır)
 * 3. cssnano  — minification
 * 4. Write to *.min.css — PHP AssetMinifier bu dosyayı bulduğunda rebuild etmez
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import postcss from 'postcss';
import cssnano from 'cssnano';
import { PurgeCSS } from 'purgecss';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');

// ─── Bundle tanımları (PHP head-meta.php ile aynı sıra) ──────────────────────
const BUNDLES = [
  {
    name: 'app',
    out: 'assets/css/app.min.css',
    sources: [
      'assets/css/app/tokens.css',
      'assets/css/app/header-nav.css',
      'assets/css/app/buttons.css',
      'assets/css/app/forms.css',
      'assets/css/app/flash.css',
      'assets/css/app/badges.css',
      'assets/css/app/hero.css',
      'assets/css/app/cat-pill.css',
      'assets/css/app/mag-grid.css',
      'assets/css/app/blocks.css',
      'assets/css/app/share-buttons.css',
      'assets/css/app/lightbox.css',
      'assets/css/app/pagination.css',
      'assets/css/app/cookie-consent.css',
      'assets/css/app/responsive.css',
      'assets/css/app/authors-grid.css',
      'assets/css/app/error-pages.css',
      'assets/css/app/tier9.css',
      'assets/css/app/print.css',
    ],
    // app.min.css tüm genel sayfalarda kullanılır — geniş content taraması
    content: [
      'app/Views/**/*.php',
      'assets/js/app.js',
      'assets/js/save-post.js',
      'assets/js/engagement.js',
    ],
  },
  {
    name: 'post',
    out: 'assets/css/post.min.css',
    sources: ['assets/css/post.css'],
    content: [
      'app/Views/pages/post.php',
      'app/Views/partials/author-bio-card.php',
      'app/Views/partials/prev-next-nav.php',
      'app/Views/partials/series-nav.php',
      'app/Views/partials/post-footer.php',
      'app/Views/partials/comments.php',
      'app/Views/partials/share-buttons.php',
      'app/Views/partials/trending.php',
      'assets/js/toc.js',
      'assets/js/progress.js',
      'assets/js/lightbox.js',
      'assets/js/footnotes.js',
      'assets/js/reactions.js',
      'assets/js/quote-share.js',
      'assets/js/before-after.js',
      'assets/js/engagement.js',
    ],
  },
  {
    name: 'admin',
    out: 'assets/css/admin.min.css',
    sources: [
      'assets/css/admin/tables-base.css',
      'assets/css/admin/wysiwyg.css',
      'assets/css/admin/media.css',
      'assets/css/admin/dashboard.css',
      'assets/css/admin/settings-base.css',
      'assets/css/admin/polish.css',
      'assets/css/admin/settings-extra.css',
      'assets/css/admin/mail-debug.css',
      'assets/css/admin/post-editor.css',
      'assets/css/admin/media-input.css',
      'assets/css/admin/tier9.css',
      'assets/css/admin/responsive.css',
    ],
    // Admin sayfaları için PurgeCSS yapma — dinamik JS widget'ları var
    skipPurge: true,
  },
  {
    name: 'panel',
    out: 'assets/css/panel.min.css',
    sources: ['assets/css/panel.css'],
    skipPurge: true,
  },
  {
    name: 'projects',
    out: 'assets/css/app/projects.min.css',
    sources: ['assets/css/app/projects.css'],
    content: [
      'app/Views/pages/projects.php',
      'app/Views/pages/project.php',
      'assets/js/gallery-lightbox.js',
    ],
  },
  {
    name: 'building-map',
    out: 'assets/css/app/building-map.min.css',
    sources: ['assets/css/app/building-map.css'],
    content: ['app/Views/pages/map.php', 'assets/js/building-map.js'],
  },
];

// ─── PurgeCSS safelist — JS'in dinamik olarak eklediği sınıflar ──────────────
const SAFELIST = {
  standard: [
    // Layout & state
    'is-active',
    'is-open',
    'is-closed',
    'is-loading',
    'is-visible',
    'is-hidden',
    'is-sticky',
    'is-fixed',
    'is-expanded',
    'is-collapsed',
    // Lightbox
    'lightbox-open',
    'lightbox-zoom',
    // BlurHash
    'has-blurhash',
    'lcp-faded',
    // Map chips
    'map-chip',
    // Flash types
    'flash-success',
    'flash-error',
    'flash-warning',
    'flash-info',
    // Save-post
    'is-saved',
    // TOC
    'toc-active',
    'toc-link',
    // Theme
    'dark',
    'light',
    // Misc utilities
    'muted',
    'sr-only',
    'visually-hidden',
    'skip-link',
    // Series
    'series-nav',
    'series-prev',
    'series-next',
    // Yazı gövdesinde dinamik üretilen tablo etiketleri — şablonlarda
    // doğrudan geçmedikleri için PurgeCSS'in stripslemesini engelle.
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    // Collapsible (FAQ, trending widget) için details/summary tag selector'leri.
    'details', 'summary',
  ],
  greedy: [/^js-/, /^is-/, /^has-/, /^was-/, /^will-/, /^no-/],
  deep: [/:global/, /\[data-/, /\[aria-/],
  keyframes: ['fn-pulse', 'spin', 'fadeIn', 'fadeOut', 'slideIn', 'slideOut'],
};

// ─── Yardımcılar ──────────────────────────────────────────────────────────────
function abs(rel) {
  return path.join(ROOT, rel);
}

function readSource(rel) {
  const p = abs(rel);
  if (!fs.existsSync(p)) {
    console.warn(`  ⚠  Missing source: ${rel}`);
    return '';
  }
  return fs.readFileSync(p, 'utf8');
}

// ─── Ana build ────────────────────────────────────────────────────────────────
async function buildBundle(bundle) {
  console.log(`\n── Building: ${bundle.name} ──────────────────────────`);

  // 1. Concat
  const raw = bundle.sources.map(readSource).join('\n');
  console.log(`  concat  : ${bundle.sources.length} files → ${(raw.length / 1024).toFixed(1)} KB raw`);

  let css = raw;

  // 2. PurgeCSS
  if (!bundle.skipPurge && bundle.content) {
    const [result] = await new PurgeCSS().purge({
      content: bundle.content.map(c => abs(c)),
      css: [{ raw: css }],
      safelist: SAFELIST,
      variables: true,
    });
    const before = css.length;
    css = result.css;
    const saved = before - css.length;
    console.log(`  purge   : −${(saved / 1024).toFixed(1)} KB (${Math.round((saved / before) * 100)}% removed)`);
  } else if (bundle.skipPurge) {
    console.log('  purge   : skipped (admin/dynamic)');
  }

  // 3. cssnano
  const result = await postcss([
    cssnano({
      preset: [
        'default',
        {
          discardComments: { removeAll: true },
          normalizeWhitespace: true,
          minifyFontValues: true,
          minifySelectors: true,
          mergeRules: true,
          reduceIdents: false, // @keyframe adlarını koruyoruz
          zindex: false, // z-index yeniden sıralama yapma
        },
      ],
    }),
  ]).process(css, { from: undefined });

  const minified = result.css;
  const outAbs = abs(bundle.out);
  fs.writeFileSync(outAbs, minified, 'utf8');
  console.log(`  output  : ${bundle.out} → ${(minified.length / 1024).toFixed(1)} KB minified`);
}

async function main() {
  console.log('Odogan CSS Build Pipeline');
  console.log('='.repeat(50));
  for (const bundle of BUNDLES) {
    await buildBundle(bundle);
  }
  console.log('\n✓ Build complete\n');
}

main().catch(err => {
  console.error('Build failed:', err);
  process.exit(1);
});
