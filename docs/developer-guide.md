# Developer Guide

This document covers the practical development workflow for the EVBlog project based on:
- parent framework theme: `wp-content/themes/lonestar/`
- child project theme: `wp-content/themes/lonestar-evblog/`

## 1) Requirements

- WordPress 6.6+
- PHP 8.1+ (project header currently requires PHP 8.2)
- Node.js 20+
- npm 10+
- ACF Pro (required by module options/fields such as GTM settings)

## 2) Architecture Model

- Parent (`lonestar`) owns reusable framework runtime:
  - core bootstrap, module catalog/bootstrapping, shared block and asset pipeline.
- Child (`lonestar-evblog`) owns project-specific concerns:
  - EVBlog styling, project integration code, child-level templates, child documentation.

Keep reusable logic in parent, EVBlog-only logic in child.

## 3) Initial Setup

### Parent theme setup

From `wp-content/themes/lonestar/`:

```bash
npm install
npm run build
```

### Child theme setup

From `wp-content/themes/lonestar-evblog/`:

```bash
npm install
npm run build
```

## 4) Daily Development Workflows

### A) Child CSS/UI change (fast path)

1. Edit `assets/css/evblog.css` in child.
2. Reload site.
3. Validate desktop/mobile.

Current child runtime enqueue is in `functions.php` and points to:
- `assets/css/evblog.css` when present
- fallback to child `style.css` otherwise

### B) Parent framework/block changes

1. Work in `wp-content/themes/lonestar/`.
2. Run `npm run dev` for Vite development or `npm run build` for production output.
3. Smoke test frontend and editor.

### C) Child Vite tooling changes

Child has local Vite config and helper files:
- `vite.config.mjs`
- `vite-entry-points.mjs`
- `vite-block-discovery.mjs`

Use:

```bash
npm run dev
npm run build
```

Note:
- child runtime is currently CSS-enqueue based;
- if you decide to serve child assets from Vite manifest in runtime, add that enqueue path explicitly in child bootstrap.

## 5) Command Reference

Run from child root (`wp-content/themes/lonestar-evblog/`):

```bash
npm run dev
npm run build
npm run format
```

PHP lint:

```powershell
Get-ChildItem -Recurse -File -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## 6) Versioning Practice

Recommended (not technically required):
- keep theme header version in `style.css` aligned with `package.json` version;
- start at `0.1.0` for pre-1.0 development and increment intentionally.

Vite helper sync markers:
- helper files include top-line comments: `// Version: x.y.z`
- `package.json` contains:
  - `lonestar_vite_helpers_version`
  - `lonestar_vite_helpers_source`

These markers are used for quick parent/child sync checks.

## 7) Validation Checklist Before Merge

1. `npm run build` in every changed theme (`lonestar`, `lonestar-evblog`).
2. PHP lint passes on touched files.
3. Manual smoke test:
   - frontend page load
   - WP admin load
   - affected template/module behavior
   - desktop + mobile view.

## 8) Deployment Checklist

1. Build changed theme(s).
2. Ensure `dist/manifest.json` exists where runtime expects it.
3. Deploy updated theme files and `dist/` artifacts.
4. Verify module settings pages and frontend rendering in production/staging.

## 9) Common Pitfalls

- Editing framework logic in child instead of parent can duplicate responsibilities.
- Assuming child `modules/` is auto-discovered by parent module catalog.
- Forgetting to include build artifacts in deployment.
- Changing helper files without bumping version markers.
