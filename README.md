# Lonestar Theme - Developer Guide

## 0) Documentation Index
- Framework + project docs live in `docs/`:
  - `docs/README.md`
  - `docs/developer-guide.md`
  - `docs/child-theme-guide.md`
  - `docs/modules-anatomy.md`

## 1) Requirements
- WordPress 6.6+ (block theme support)
- PHP 8.1+
- Node.js 20+
- npm 10+
- ACF Pro (required for ACF blocks and Theme Options page)

## 2) Initial setup
```bash
npm install
```

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
- `blocks/native/` - native/dynamic blocks
- `assets/css/`, `assets/js/` - source assets
- `dist/` - built assets and Vite manifest

## 5.1) Modules
- Module types:
  - Flat: `modules/module.<slug>.php`
  - Folder: `modules/<slug>/module.<slug>.php`
- Folder modules can include root-like sub-structure:
  - `blocks/acf/`, `blocks/native/`
  - `assets/`
  - `inc/*.php`, `inc/helpers/`, `inc/shortcodes/`, `inc/walkers/`
  - `acf-json/`
- Enabled module block roots are scanned by the same block discovery/asset pipeline as theme roots.
- Manage module toggles in admin: `Appearance -> Theme Modules`.
- Toggle state is stored in option: `lonestar_module_toggles`.
- Module catalog is transient-cached in non-dev mode (`lonestar_mod_catalog_*`) and flushed on:
  - module toggle updates
  - theme switch
  - upgrader completion
- Emergency disable controls (without opening admin):
  - set `MODULES_DISABLE_ALL` to `true` in `wp-config.php`
  - set `MODULES_DISABLED` as array or comma-separated string in `wp-config.php`
  - set `LONESTAR_DISABLE_ALL_MODULES` to `true` in `wp-config.php`
  - set `LONESTAR_DISABLED_MODULES` as array or comma-separated string in `wp-config.php`
  - create `.disable-modules` file in theme root
- If a module was enabled in options but disappears from filesystem, it is auto-marked disabled in options (plugin-like behavior).
- Admin list shows module description resolved from:
  - `modules/<slug>/module.json` -> `description`
  - `modules/<slug>/README.md` first non-heading line
  - `module.<slug>.php` docblock summary
- Admin list can show module Settings links (when module is enabled):
  - explicit from `module.json` -> `admin_links`
  - auto-detected from ACF options page/subpage registrations inside module PHP

## 6) Creating a new block
1. Create block folder in:
   - `blocks/acf/<block-slug>/` or `blocks/native/<block-slug>/`
   - or inside a module: `modules/<module-slug>/blocks/acf/<block-slug>/` / `modules/<module-slug>/blocks/native/<block-slug>/`
2. Add `block.json`.
3. Add optional assets:
   - `<block-slug>.js` or `index.js`
   - `<block-slug>.css` or `style.css`
4. Run `npm run build` before deployment.

Notes:
- Build entry keys are path-based to avoid slug collisions across block roots.
- Production registration resolves assets from Vite manifest by source path.

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
npm run build
```

## 9) Deployment checklist
1. `npm run build`
2. Confirm `dist/manifest.json` exists
3. Confirm block assets exist in `dist/`
4. Deploy theme files including `dist/`
