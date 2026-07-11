# Lonestar Theme - Developer Guide

## 0) Documentation Index

- Framework + project docs live in `docs/`:
    - `docs/README.md`
    - `docs/developer-guide.md`
    - `docs/block-types.md`
    - `docs/content-types.md`
    - `docs/child-theme-guide.md`
    - `docs/modules-anatomy.md`
    - `docs/parent-release-updates.md`
    - `docs/git-workflow.md`
- Contribution and PR rules:
    - `CONTRIBUTING.md`

## 1) Requirements

- WordPress 7.0+
- PHP 8.2+
- Node.js 22.12+
- npm 10+
- ACF Pro (optional; required only for ACF blocks/modules)

## 2) Initial setup

```bash
npm ci
```

### CSS toolchain

Parent and boilerplate child intentionally share one small CSS compatibility stack:

- `postcss-nesting` converts standards-based CSS nesting into broadly consumable CSS;
- Autoprefixer adds only the vendor prefixes required by `@wordpress/browserslist-config`;
- `postcss` is the plugin runner that Vite loads through `postcss.config.js`;
- Browserslist stores the explicit browser policy and can be inspected with `npm run browsers`.

Vite already resolves CSS `@import`, so `postcss-import` is not installed. Use standard nesting syntax, not the Sass-like behavior from `postcss-nested`. Tailwind is intentionally unsupported in both themes; use `theme.json`, native CSS, block styles, and the existing token layer instead.

Browserslist currently governs CSS prefixing. Vite's JavaScript transform target is a separate setting and must be reviewed explicitly if the JavaScript support matrix changes.

## 3) Development workflow

Run Vite dev server:

```bash
npm run dev
```

Theme auto-detects Vite in non-production environments by probing `http://localhost:3000/@vite/client`.

Optional explicit dev-mode switches:

- `define('IS_VITE_DEVELOPMENT', true);` in `wp-config.php`
- Environment variable: `LONESTAR_VITE_DEV=1`

## 4) Production build

Generate production assets:

```bash
npm run build
```

Important:

- `dist/manifest.json` and `dist/*` must be present in deployment artifact.
- Theme now falls back to source CSS/JS when manifest is missing, but this is only a safety net.
- Admin users will see a warning if build artifacts are missing.

## 5) Theme structure

- `functions.php` - theme bootstrap and constants
- `inc/core/` - core runtime, blocks, Vite integration
- `inc/inc.*.php` - project-specific customizations
- `modules/` - optional feature modules (flat file or folder modules)
- `blocks/acf/` - ACF blocks
- `blocks/native/` - native static and native dynamic blocks
- `blocks/php-only/` - WordPress 7 PHP-only blocks
- `inc/content-types/` - declarative parent/child post type and taxonomy definitions
- `assets/css/`, `assets/js/` - source assets
- `dist/` - built assets and Vite manifest

## 5.1) Modules

- Module types:
    - Flat: `modules/module.<slug>.php`
    - Folder: `modules/<slug>/module.<slug>.php`
- Module discovery sources:
    - parent theme: `modules/`
    - child theme (when active): `<child-theme>/modules`
- Folder modules can include root-like sub-structure:
    - `blocks/acf/`, `blocks/native/`, `blocks/php-only/`
    - `assets/`
    - `inc/*.php`, `inc/helpers/`, `inc/shortcodes/`, `inc/walkers/`
    - `acf-json/`
- Enabled module block roots are scanned by the same block discovery/asset pipeline as theme roots.
- Manage module and block toggles in admin: `Appearance -> Theme Settings`.
- Toggle state is stored in option: `lonestar_module_toggles`.
- Block toggle state is stored in option: `lonestar_block_toggles`.
- Module metadata supports `Module`, `Description`, `Author`, `Version` headers in `module.<slug>.php` docblock.
- Module catalog is transient-cached in non-dev mode (`lonestar_mod_catalog_*`) and flushed on:
    - module toggle updates
    - block toggle updates
    - theme switch
    - upgrader completion
- Emergency disable controls (without opening admin):
    - set `MODULES_DISABLE_ALL` to `true` in `wp-config.php`
    - set `MODULES_DISABLED` as array or comma-separated string in `wp-config.php`
    - set `LONESTAR_DISABLE_ALL_MODULES` to `true` in `wp-config.php`
    - set `LONESTAR_DISABLED_MODULES` as array or comma-separated string in `wp-config.php`
    - create `.disable-modules` file in theme root
- Missing module toggles are reconciled on the explicit admin lifecycle; frontend reads never write module options.
- If the same module slug exists in both parent and child theme, child-source module takes runtime priority.
- Admin list shows module description resolved from:
    - `modules/<slug>/module.json` -> `description`
    - `module.<slug>.php` docblock header `Description`
    - `modules/<slug>/README.md` first non-heading line
    - `module.<slug>.php` docblock summary
- `module.json` may declare `requires` (`acf`, `gravity-forms`) and explicit `admin_links`; unavailable modules remain visible but cannot be enabled.

## 6) Creating a new block

1. Create block folder in:
    - `blocks/acf/<block-slug>/`, `blocks/native/<block-slug>/`, or `blocks/php-only/<block-slug>/`
    - the same three roots are supported inside folder modules and the active child theme
2. Add `block.json`.
3. Add optional assets:
    - `<block-slug>.js` or `index.js`
    - `<block-slug>.css` or `style.css`
4. Run `npm run build` before deployment.

Notes:

- Build entry keys are path-based to avoid slug collisions across block roots.
- Production registration resolves assets from Vite manifest by source path.
- See `docs/block-types.md` for the exact ACF, native static/dynamic, and PHP-only contracts.
- Block CSS may use standards-based nesting. The native static reference block demonstrates the supported syntax.

## 7) Caching and invalidation

Theme caches block discovery and block asset metadata via transients in non-dev mode.
Cache namespace is based on:

- theme version
- `dist/manifest.json` filemtime
- WP environment type

This means caches automatically refresh after a new build/deploy or version bump.

## 8) Basic verification commands

PHP syntax check:

```bash
Get-ChildItem -Recurse -File -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Build check:

```bash
npm run check
```

## 9) Deployment checklist

1. `npm run build`
2. Confirm `dist/manifest.json` exists
3. Confirm block assets exist in `dist/`
4. Deploy theme files including `dist/`
