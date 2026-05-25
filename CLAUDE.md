# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Odogan CMS — a framework-less PHP 8.2+ blog & portfolio CMS for [odogan.com.tr](https://odogan.com.tr) (architecture / construction / restoration content). Turkish-language UI and URLs; PHP code is in English. Targets cPanel / shared LiteSpeed-Apache hosting.

## Common commands

```bash
# Setup
composer install
npm install
cp .env.example .env                # fill DB, APP_URL, SMTP
php database/migrate.php            # apply pending SQL migrations

# Dev server (PHP built-in; router.php replicates .htaccess denylist)
php -S localhost:8000 router.php

# Asset build pipeline (writes assets/**/*.min.{css,js} + .map)
npm run build                       # full (css + js)
npm run css:lint        npm run css:build
npm run js:lint         npm run js:format       npm run js:build
npm run js:lint:fix     npm run css:lint:fix

# PHP quality
composer analyse                    # PHPStan level 5 against app/ (baseline mode)
composer lint                       # php -l on every app/**/*.php
composer test                       # PLACEHOLDER — no PHPUnit installed yet

# Operational scripts
php bin/maintenance.php on|off|status     # toggles storage/.maintenance → site-wide 503
php bin/backup-db.php                     # see docs/BACKUP_RESTORE.md
php bin/backup-uploads.php
php bin/backfill-blurhash.php
php bin/purge-old-ips.php
php bin/encrypt-smtp-password.php
```

There are no unit tests in the repo. `composer test` is an echo placeholder; CI (`.github/workflows/ci.yml`) runs only `php -l`, `composer analyse`, and the placeholder. Don't claim tests passed unless real coverage exists.

## Hosting layout duality (important)

The code runs in two layouts and `Config::publicRoot()` (`app/Core/Config.php`) auto-detects which:

- **Flat / cPanel default**: `index.php`, `bootstrap.php`, `.env`, `vendor/`, `app/`, `uploads/` all sit at the project root.
- **Stock**: `public/index.php`, with `app/` one level up.

`router.php` exists because PHP's built-in server ignores `.htaccess` — it manually 403s the denylist (`.env`, `app/`, `bootstrap.php`, `vendor/`, `storage/`, …) that the production `.htaccess` blocks. Any new top-level secret directory must be added to **both** `.htaccess` and `router.php`'s `$blockedPrefixes`.

## Architecture

Custom thin framework — no Laravel / Symfony. Read these in order to onboard:

- `index.php` → `bootstrap.php` → `app/routes.php` → `App\Core\Router::dispatch()`.
- `bootstrap.php` boots Composer autoload (PSR-4 `App\` → `app/`), loads `app/Helpers/{functions,media,seo}.php` (registered as composer `files`), boots `Config` from `config/*.php`, optionally initializes Sentry, hardens session cookies (`odogan_sid`, `gc_maxlifetime=14400`), short-circuits to a 503 view when `storage/.maintenance` exists, and opportunistically triggers `PostScheduler::publishDue()` (rate-limited to once per 60s via a stamp file in `storage/cache`).
- Controllers (`app/Controllers/`) are organized by UI area: top level = public; `Admin/` = `/admin/*` (RBAC `admin`); `Editor/` = `/editor/*` (RBAC `admin,editor`); `Panel/` = `/panel/*` (author panel, `AuthMiddleware`).
- Services (`app/Services/`) hold business logic. Notable sub-namespaces:
  - `Schema/` — JSON-LD `@graph` builders (Article, Person, Project, FaqPage, …) rendered via `Renderer.php`.
  - `Rag/` — RAG glossary pipeline (Wikipedia → Librarian → Writer → Judge). See `docs/GLOSSARY_AI_REDESIGN.md` for the design.
  - `Glossary/` — URL verification for glossary references.
- Models (`app/Models/`) are thin static-method query objects on top of `App\Core\Database` (PDO singleton, buffered queries enforced — DDL+INSERT migration ordering depends on this).
- Views (`app/Views/`) use the home-grown `App\Core\View` with `section`/`yield`/`layout` over plain PHP templates (`layouts/base.php` is the single shared shell).

## Routing conventions (frequent footgun)

`Router` is **first-match-wins** and patterns compile via a single `preg_replace_callback` over `{name}` / `{name:regex}`. Multiple files explicitly warn about this — keep the pattern:

- Register **literal** paths before `{id}` catch-alls. e.g. inside `/panel/yazilar/*` the order in `routes.php` is `/yazilar/analiz`, `/yazilar/ai-analiz`, `/yazilar/onerile`, `/yazilar/toplu` (all literal POSTs) **before** `/yazilar/{id}` and `/yazilar/{id}/sil`. Same applies to `/admin/sozluk/*` (literal `/check-dup`, `/toplu`, `/toplu/isle`, `/toplu-denetle` before `/{id}`).
- Use numeric constraints to disambiguate paging from slugs: `/{category}/{page:\d+}` is registered **before** `/{category}/{slug}` at the bottom of the file.
- `GET` matches both `GET` and `HEAD`. Trailing slashes on non-root GET/HEAD are 301'd to the slashless form automatically.

Middleware syntax in route definitions:

```php
$router->group('/admin', $fn, [AuthMiddleware::class, RbacMiddleware::class . ':admin']);
```

The `:args` suffix is parsed by the router and passed to the middleware's constructor (`RbacMiddleware::__construct(string ...$roles)`).

## Cross-cutting middleware & helpers

- **CSRF** (`App\Middleware\CsrfMiddleware`) is `$router->use(...)`-registered globally and enforced on `POST/PUT/PATCH/DELETE`. Tokens come from `_csrf` body field or `X-CSRF-Token` header; failures return **419**. Use `csrf_token()` / `csrf_field()` in views; AJAX must round-trip the token (JSON requests get a JSON 419 instead of HTML).
- **Auth** roles in DB `users.role`: at minimum `admin`, `editor`, plus regular author users.
- **Feature flags** — `feature('flag_name')` reads `App\Models\Setting::get($name, $default, 'features')` (default `false`). Admin manages flags at `/admin/ozellikler`. Several routes/services gate on `feature(...)` (e.g. `redirect_manager_enabled`, `not_found_logger_enabled`).
- **`Config::get($key, $default)`** reads env first (`$_ENV` / `$_SERVER` / `getenv`), then dotted `config/<file>.<key>` from `config/*.php`. Booleans/numerics are auto-cast.
- View helpers from `app/Helpers/functions.php`: `esc()` / `e()`, `url()`, `asset()` (auto-appends `?v=<mtime>` cache-buster), `redirect()`, `csrf_*`, `old()`, `flash()`, `feature()`. Don't add new global functions outside the three `Helpers/*.php` files autoloaded by Composer.

## Database & migrations

- MySQL 8.0+, `utf8mb4`, `ATTR_EMULATE_PREPARES=false`, buffered queries enabled.
- Migrations: `database/migrations/NNN_description.sql`, executed in filename order. `000_migrations.sql` bootstraps the tracking table. The repo is currently at ~069.
- Two runners:
  - CLI: `php database/migrate.php` (single batch, fail-fast).
  - Web/admin: `App\Services\MigrationRunner` (statement-by-statement, also drives `/admin/bakim/migrasyonlar`).
- DDL can't roll back — write migrations idempotently (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` via `INFORMATION_SCHEMA` guards) and never mix DDL with data backfills in a single SQL statement.

## Asset pipeline

Two pipelines coexist by design:

1. **Node** (preferred — `scripts/build-css.js`, `scripts/build-js.js`): PurgeCSS + cssnano for CSS bundles defined in `BUNDLES` (must mirror the load order in `app/Views/partials/layout/head-meta.php`); Terser + source maps for JS. Reserved global names (`initBuildingMap`, `initGalleryLightbox`, `initTeamBuilder`, `openMediaPickerImpl`) are excluded from mangling because PHP-rendered inline scripts call them.
2. **PHP fallback** (`App\Services\AssetMinifier`): JIT-minifies a source when its `.min.<ext>` sibling is missing or stale. Conservative — comment strip + whitespace collapse only.

`.min.css` / `.min.js` **and their `.map` files are committed** (see `.gitignore` comments) — production deploys are `git pull` on cPanel, and source maps stay so Sentry/devtools can symbolize. When editing under `assets/css/` or `assets/js/`, run `npm run build` and commit the rebuilt mins alongside the source.

ESLint runs as `sourceType: 'script'` (the JS files are IIFEs, not modules). Don't convert them to ES modules without updating `eslint.config.js` and the build script. `assets/js/*.min.js` and `assets/vendor/**` are ignored by lint.

## Static analysis

PHPStan level 5 against `app/` (excluding `Views/` and `Helpers/`). `phpstan-baseline.neon` suppresses known legacy debt — **new** errors fail CI. When you fix something in the baseline, regenerate it with `vendor/bin/phpstan analyse --memory-limit=512M --generate-baseline` rather than ignoring.

## Conventions

- `declare(strict_types=1);` at the top of every PHP file. Classes are `final` by default.
- All Turkish UI/URL strings live in routes + view files. Don't translate route paths (they're public canonical URLs with SEO history).
- Settings live in `App\Models\Setting` keyed by group (e.g. `features`, `seo`, `mail`); prefer adding a setting + admin UI over hardcoding tunables.
- Trusted-proxy / real-IP logic goes through `App\Services\RealIpService` and is configured in `config/security.php` — don't read `X-Forwarded-For` directly.
- Schema.org JSON-LD: extend or compose `App\Services\Schema\*` rather than emitting JSON-LD strings inline in views.
- Glossary auto-linking (`AutoGlossaryLink`, `AutoLinkService`) runs at render time over post HTML; new linkable entities should plug into those services, not regex-patch the view.

## Operational notes

- `.env` keys: see `.env.example`, `.env.hosting.example`, `.env.production.example`. `APP_DEBUG=true` exposes errors; in production keep it false and rely on Sentry (`SENTRY_DSN`).
- Sessions are 4h server-side (`session.gc_maxlifetime=14400`) so long admin write sessions don't lose the CSRF token mid-draft. Don't shorten this without coordinating with the editor UI.
- Production CDN/cache: `.htaccess` ships 30-day `max-age` for assets — the mtime `?v=` query string is the rebuild signal. If you change cache headers, also revisit `asset()` in `app/Helpers/functions.php`.
- Deployment guides: `docs/DNS_SETUP.md`, `docs/CRON_SETUP.md`, `docs/BACKUP_RESTORE.md`, `docs/UPTIME_MONITORING.md`.
