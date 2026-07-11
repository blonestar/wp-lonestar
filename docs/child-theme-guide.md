# Child Theme Guide

This guide documents the reusable `lonestar-child` boilerplate and recommended implementation patterns. The boilerplate lives in a separate `blonestar/lonestar-child` repository.

## 1) Core Files

- `style.css`  
  WordPress child theme header (`Template: lonestar`) and public metadata.
- `functions.php`  
  Child bootstrap and enqueue logic.
- `assets/css/styles.css`
  Child source CSS loaded through the child Vite entry.
- `main.js`  
  Child Vite entry point (typically imports a child CSS asset).

## 2) Asset Runtime

The child owns an independent Vite build. Production reads `dist/manifest.json`; source CSS is a safe fallback only when the manifest is missing. Development uses port `3001` when `LONESTAR_CHILD_VITE_DEV=true`.

Parent and child intentionally use the same CSS contract. The child has its own `postcss.config.js`, dependencies, and WordPress Browserslist declaration so that it can build independently without reaching into the parent repository.

## 3) Child Vite Setup

Child includes `vite.config.mjs`, `package.json`, and tracked `dist/` output.

Use cases:

- production asset builds,
- local watch/dev,
- parity with parent tooling patterns.

The supported CSS stack is native CSS plus standards-based nesting, `postcss-nesting`, and Autoprefixer. Vite handles CSS imports. Tailwind, `postcss-import`, and Sass-like `postcss-nested` syntax are not part of the boilerplate.

Parent block discovery reads all three block roots from the active child. Child block assets are resolved against the child source context and child manifest.

## 4) Parent vs Child Responsibility

Put code in child when it is project-specific:

- custom site styles and layout overrides,
- project-only template changes,
- project integrations that should not leak to other projects.
- project-only modules under `modules/` (auto-discovered by framework when child is active).
- ACF, native, or PHP-only blocks under their matching `blocks/` root.

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

The boilerplate intentionally uses `Update URI: false`; project forks must not receive boilerplate updates.

## 7) Safe Extension Points

Common child extension paths:

- `functions.php` for enqueue and project hooks.
- `inc/helpers/helper.*.php` for child helper overrides (parent loader supports child overrides by basename).
- `inc/shortcodes/shortcode.*.php` for child shortcode overrides (same basename override strategy).
- `templates/`, `parts/`, `patterns/` for project-level rendering overrides where needed.
- `blocks/acf/`, `blocks/native/`, and `blocks/php-only/` for the three supported block families.
- `inc/content-types/*.php` for declarative project post types and taxonomies; discovery is automatic, so no child `functions.php` bootstrap edit is needed. See `docs/content-types.md`.

## 8) Quality Baseline

- Run build after tooling changes.
- Run `npm run browsers` when reviewing or changing browser support.
- Keep the CSS pipeline contract test passing.
- Run PHP lint on touched files.
- Smoke test in WP admin + frontend.
- Validate desktop and mobile rendering for changed templates/CSS.
- Activate the child and verify `Template: lonestar`, parent updater visibility, block discovery, and child-over-parent override priority.
