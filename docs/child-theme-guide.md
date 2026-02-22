# Child Theme Guide

This guide documents child-theme internals and recommended implementation patterns.

## 1) Core Files

- `style.css`  
  WordPress child theme header (`Template: lonestar`) and public metadata.
- `functions.php`  
  Child bootstrap and enqueue logic.
- `theme.json`  
  Child design tokens/settings layered over parent theme behavior.
- `assets/css/<child>.css`  
  Child CSS loaded by runtime enqueue.
- `main.js`  
  Child Vite entry point (typically imports a child CSS asset).

## 2) Current Asset Runtime

In `functions.php`, child enqueue function should:
- first try child CSS asset (for example `assets/css/<child>.css`) with `filemtime` cache-busting;
- falls back to child `style.css` with theme version if the file is missing.

Implication:
- CSS changes in the child asset are immediately effective without Vite runtime integration.

## 3) Child Vite Setup

Child includes dedicated build tooling:
- `vite.config.mjs`
- `vite-entry-points.mjs`
- `vite-block-discovery.mjs`
- `package.json`

Use cases:
- production asset builds,
- local watch/dev,
- parity with parent tooling patterns.

Current behavior note:
- runtime enqueue points to child source CSS asset;
- if you want dist assets at runtime, add manifest-based enqueue logic in child bootstrap.

## 4) Parent vs Child Responsibility

Put code in child when it is project-specific:
- custom site styles and layout overrides,
- project-only template changes,
- project integrations that should not leak to other projects.
- project-only modules under `modules/` (auto-discovered by framework when child is active).

Put code in parent when it is framework-worthy:
- reusable module runtime behavior,
- generic helper utilities,
- reusable block infrastructure.

## 5) Recommended Override Strategy

1. Prefer hooks/filters.
2. Prefer child `theme.json` and CSS layering.
3. Copy parent templates only when hook-based customization is insufficient.
4. Keep copied templates minimal and documented.

## 6) Versioning

Suggested practice:
- keep `style.css` theme `Version` and `package.json` `version` aligned for clarity.
- use semver and start from `0.1.0` while project remains pre-1.0.

Vite helper sync:
- helper files use top-line version comment (`// Version: x.y.z`);
- `package.json` stores helper sync metadata.

## 7) Safe Extension Points

Common child extension paths:
- `functions.php` for enqueue and project hooks.
- `inc/helpers/helper.*.php` for child helper overrides (parent loader supports child overrides by basename).
- `inc/shortcodes/shortcode.*.php` for child shortcode overrides (same basename override strategy).
- `templates/`, `parts/`, `patterns/` for project-level rendering overrides where needed.

## 8) Quality Baseline

- Run build after tooling changes.
- Run PHP lint on touched files.
- Smoke test in WP admin + frontend.
- Validate desktop and mobile rendering for changed templates/CSS.
