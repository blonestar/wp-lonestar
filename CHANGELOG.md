# Changelog

All notable changes to the Lonestar parent theme are documented in this file.

## [Unreleased]

### Added

- Added dev-only PHP quality tooling (`composer.json`, `phpcs.xml.dist`, `phpstan.neon.dist`, `phpstan-baseline.neon`): a curated WordPress Coding Standards gate (security, i18n, discouraged/deprecated functions, PHP 8.2+ compatibility, `lonestar_`/`LONESTAR_` symbol prefixing) and PHPStan level 5 with a baseline for pre-existing findings. None of this ships in release ZIPs.
- Added a CI `quality` job running `composer run lint:phpcs` and `composer run lint:phpstan`.
- Added PHP 8.5 to the CI PHP lint matrix (alongside 8.2 and 8.4).
- Added a `.wp-env.json` and a CI `smoke` job (report-only, `continue-on-error: true`, unvalidated locally — no Docker in this environment) that starts `@wordpress/env`, activates the theme, and checks for PHP fatals/warnings and expected block registration on the homepage and a 404 page.

### Changed (Phase 4: prefixed public API & code hygiene)

- The public PHP API now uses `lonestar_`/`LONESTAR_` prefixes consistently, and production Vite assets use the `lonestar-main` and `lonestar-{filename}` handles.
- Shortcode tags `[R]`, `[Y]`, and `[year]` remain unchanged.
- Module functions use the `lonestar_*` namespace, the unused module PHP scanner was removed, and the module admin handler guards `$_SERVER['REQUEST_METHOD']` with `isset()` before reading it.

### Fixed

- Vite/HMR module scripts now actually load as `type="module"`: added a `wp_script_attributes` filter (`lonestar_filter_module_script_attributes`) since core silently ignores `wp_script_add_data($handle, 'type', 'module')`.
- Block `viewScriptModule` handles are now registered through the WordPress Script Modules API (`wp_register_script_module`) instead of being merged into classic `script`/`editorScript`/`viewScript` handles, which previously risked applying `type="module"` to non-ESM built block scripts.
- Fixed the WordPress 7 PHP-only example block's `render.php`, whose wrapper `<section>` tag was never closed.
- Content Types admin screen strings now use the `lonestar` text domain instead of the retired `lonestar-theme` domain.
- `lonestar_filter_parent_theme_update` no longer discards `$update` for non-Lonestar themes or when no newer release is available; it now returns the incoming `$update` unchanged, since `update_themes_github.com` can be shared by other GitHub-hosted theme updaters.
- Parent theme update metadata now resolves the theme via `get_template()` (`lonestar_get_parent_theme()`), instead of a hardcoded `wp_get_theme('lonestar')` lookup that resolves to nothing in the `wp-lonestar` development checkout folder.
- Parent theme update metadata no longer hardcodes `'tested' => '7.0'`; it now reads the parent's "Tested up to" `style.css` header (`lonestar_get_parent_theme_tested_wp_version()`), falling back to "Requires at least".
- `module.disable-emoji.php` now also removes `wp_enqueue_scripts`/`admin_enqueue_scripts` → `wp_enqueue_emoji_styles`, and the `embed_head`/`enqueue_embed_scripts` emoji hooks, which modern core registers but the module previously left active. The removal now also runs on `admin_init`, since core's admin-only emoji hooks (`wp-admin/includes/admin-filters.php`) are not attached until after the `init` action has already fired in `wp-admin`.
- `assets/css/reset.css` no longer forces `display: block` on `img`/`picture`/`video`/`canvas`/`svg`, which broke inline images and WordPress image-alignment classes; images now get `max-width: 100%; height: auto;` (plus `vertical-align: middle`) without a forced display change. The universal `margin: 0; padding: 0` reset no longer strips `ul`/`ol` padding, so post-content list bullet/number indentation is preserved.
- `inc/inc.filters.php`: removed the `upload_mimes` AVIF filter — WordPress core has supported AVIF uploads since 6.5 and no longer needs it.

### Added

- Added `templates/archive.html` and `templates/search.html`, and rebuilt `templates/index.html` as a generic Query Loop fallback (`core/query` with `inherit: true`, post title/date/excerpt/featured image, pagination, and no-results states).
- Added the `parts/comments.html` template part to `templates/single.html` and registered it in `theme.json` `templateParts`.
- Added `lonestar_register_editor_styles()` (`inc/core/vite.php`, hooked on `after_setup_theme` priority 20), so the block editor iframe loads `assets/css/reset.css` and the built Vite CSS via `add_editor_style()`, matching the frontend. In Vite dev mode the built `dist/` CSS is skipped since the editor already gets live styles from the Vite HMR client.
- Added `patterns/404-content.php`, `patterns/no-results.php`, and `patterns/no-search-results.php` — translatable (`lonestar` text domain) PHP block patterns referenced via `<!-- wp:pattern {"slug":"lonestar/..."} /-->` from `templates/404.html`, `templates/index.html`, `templates/archive.html`, and `templates/search.html`, replacing hardcoded English text in those static HTML templates (block templates are not scanned by `wp i18n make-pot`).
- `theme.json`: enabled fluid typography (`settings.typography.fluid: true`) with `fluid.min`/`fluid.max` on each existing font size preset (slugs and max sizes unchanged), and added `styles.elements.link` (brand color, underlined, brand-strong on hover) and `styles.elements.heading` (heading font family, weight 700, line-height 1.2), removing the now-duplicated rules from `assets/css/_base-styles.css`.

### Changed

- Bumped the block asset registration transient cache key from `lonestar_block_asset_map_v3_*` to `lonestar_block_asset_map_v4_*` to invalidate stale cached asset maps after the `viewScriptModule` handling change.
- `style.css` "Tested up to" header raised to 7.1.
- `theme.json` `$schema` now points at the versioned `https://schemas.wp.org/wp/7.1/theme.json` instead of `.../trunk/theme.json`.
- Renamed the `themes.php` submenu label from "Reusable Blocks" to "Patterns" (`inc/inc.filters.php`), matching core's WordPress 6.3+ terminology; the underlying `wp_block` post type and screen URL are unchanged.
- `blocks/acf/example-acf/block.json`: added `"blockVersion": 3` to the `acf` object to opt the example block into ACF 6.3+'s iframed block editor (block API v3).

### Performance

- The Vite dev-server auto-probe (`lonestar_is_vite_dev_mode()`) now only runs its `wp_remote_get()` HTTP check when `wp_get_environment_type()` is `local` or `development`; other environment types (e.g. `staging`) must opt in explicitly via `LONESTAR_VITE_DEVELOPMENT` or `LONESTAR_VITE_DEV`. Added filter `lonestar_vite_dev_probe_enabled` to override the gate per site. See `docs/developer-guide.md`.
- `lonestar_remove_query_string_from_static_files()` (`inc/core/blocks-acf-enqueue.php`) no longer runs `filemtime()` on every enqueued theme asset on every request. It now only rewrites the `ver` query arg when the asset carries WordPress's default core-version `ver` (i.e. was enqueued without an explicit version — theme-registered block assets already pass explicit filemtime versions and are skipped). Path resolution now maps the template/stylesheet directory URI to `get_template_directory()`/`get_stylesheet_directory()` instead of `ABSPATH`, which was wrong whenever `WP_CONTENT_DIR` lives outside `ABSPATH` (e.g. Bedrock-style installs). Resolved mtimes are memoized per request. The active resolver is `lonestar_theme_asset_url_to_path()`.
- Consolidated block discovery caching uses a single request-memoized + transient-backed block runtime index (`lonestar_get_block_runtime_index()`, fixed transient key `lonestar_block_runtime_v1`, payload carries its own `cache_namespace` so a namespace change on deploy is detected and rebuilt). Each block.json is now decoded once per request/cache build and reused by the ACF/native/PHP-only register functions and the JS/CSS asset map. `lonestar_flush_block_discovery_caches()` clears the current runtime index key.
- Fixed a module catalog i18n cache bug: `lonestar_get_module_catalog()` now caches module labels/descriptions/admin-link labels untranslated with their textdomain, and translates on every read (`lonestar_localize_module_catalog()`), after both cache hits and fresh builds. Bumped the catalog transient schema from `v4` to `v5`.
- `lonestar_get_module_source_fingerprint()` supports a fast production mode that hashes module-root mtimes plus the theme version; non-production environments keep the full per-entry fingerprint. Added filter `lonestar_module_fingerprint_mode` (`'full'|'fast'`).

## [0.5.0] - 2026-07-13

### Added

- Added a read-only Theme Settings `Content Types` overview backed by the shared runtime content-type catalog.
- Added gettext-ready parent-theme strings, `lonestar` block and module metadata domains, JavaScript script-translation registration, and a repeatable `npm run i18n:pot` workflow.

## [0.4.0] - 2026-07-11

### Added

- Added declarative parent/child content type discovery for post types and taxonomies, with slug-based child overrides, diagnostics, and admin-only rewrite signature refreshes.

## [0.3.0] - 2026-07-10

### Added

- Added a shared parent/child CSS compatibility contract with standards-based nesting, WordPress Browserslist targets, Autoprefixer, and automated pipeline tests.
- Added explicit ACF, native static/dynamic, and WordPress 7 PHP-only block contracts with reference blocks and child-over-parent discovery.
- Added a bundled local field group for the ACF reference block and dependency-aware block/module availability.
- Added verified GitHub Release updates through `Update URI`, strict SemVer/asset validation, SHA-256 pre-download checks, and Site Health/About diagnostics.
- Added Node contract tests, JSON/package checks, Node 22/24 and PHP 8.2/8.4 CI, Dependabot, release attestations, and pinned Actions.
- Added separate `lonestar-child` boilerplate repository scaffold with all three block roots and independent Vite build.

### Changed

- Git checkouts now skip parent release checks and block update downloads unless `LONESTAR_ALLOW_UPDATES` explicitly overrides the policy.
- Replaced redundant `postcss-import` and Sass-like `postcss-nested` processing with Vite import handling and standards-based `postcss-nesting`; Tailwind is explicitly outside the framework stack.
- Upgraded both parent and child tooling to Vite 8/Rolldown, raised the Node minimum to 22.12, and removed redundant `cross-env` usage.
- Raised the minimum WordPress version to 7.0 and Node.js development baseline to 22.12.
- Replaced glob-based core bootstrap with an explicit load order and introduced prefixed path constants with compatibility aliases.
- Hardened release packaging so tracked `dist/`, exact changelog/version metadata, ZIP layout, checksum, and GitHub asset digest must agree before publication.
- Module settings links and dependencies are now explicit `module.json` metadata; unavailable modules cannot boot.
- Module toggle reconciliation moved from frontend reads to the admin lifecycle.
- Full-site templates now use a `main` landmark, navigation uses its mobile overlay, and CSS aliases resolve theme.json tokens/system fonts.

### Fixed

- Draft publication now retries GitHub's eventually consistent release lookup before failing closed.
- Draft release digest verification now resolves the release by ID before publishing, avoiding GitHub's draft tag-endpoint limitation.
- Release checksum verification now runs from the artifact directory so the generated relative ZIP path resolves correctly.
- Native dynamic output now honors block wrapper attributes and preserves allowed RichText markup safely.
- Native block scripts now declare their WordPress editor dependencies.

### Removed

- Removed the empty duplicate ACF Theme Options page.
- Removed runtime ACF options-page source scanning from the module contract.

### Added

- Theme Settings now includes a dedicated `Changelog` tab.
- Theme Settings tabs are now structured for growth: `Modules`, `Blocks`, `Changelog`.
- Changelog tab shows `CHANGELOG.md` from active parent and child themes.
- Added `AGENTS.md` to parent theme root for clone-time AI instructions and local workflow rules.
- Added `About` tab in Theme Settings with active/parent theme info and runtime context.
- About tab now includes a link to the parent GitHub repository.
- Added parent-only release/update pipeline documentation in `docs/parent-release-updates.md`.
- Added parent-theme GitHub release update integration (`inc/core/theme-updates.php`) for WordPress update detection.
- Added a minimal Mermaid development flow diagram in `docs/developer-guide.md`.
- Added a parent-theme Git workflow runbook in `docs/git-workflow.md`.
- Added a Mermaid Git workflow diagram to `docs/git-workflow.md`.
- Added contribution policy in `CONTRIBUTING.md` for branch/PR/release discipline.
- Added GitHub PR template in `.github/PULL_REQUEST_TEMPLATE.md`.
- Added `.github/CODEOWNERS` with default repository ownership.
- Added Mermaid module diagrams (discovery/override and boot flow) in `docs/modules-anatomy.md`.
- Added GitHub Actions CI workflow in `.github/workflows/ci.yml` (`build`, `php-lint` on push/PR to `main`).
- Added GitHub Actions release workflow in `.github/workflows/release.yml` (manual `workflow_dispatch` release with build before packaging).
- Added native GTM settings integration in Theme Settings (`GTM` tab) with option storage in `lonestar_gtm_settings`.
- Added required semantic versioning rule to `AGENTS.md` with `style.css`/`package.json` (and `package-lock.json` when present) alignment.

### Changed

- Block conflict resolution now enforces child-theme override priority over parent blocks with the same identity.
- In Theme Settings > Blocks, overridden parent blocks are shown as disabled checkboxes with override note.
- In Theme Settings > Modules, overridden parent modules are now shown as disabled checkboxes with override note.
- Added parent theme thumbnail image.
- In Theme Settings > About, parent repository URL is now shown under `Parent Theme`.
- In Theme Settings > About, parent and child details are now fully separated into dedicated sections.
- In Theme Settings > About, parent/child metadata now includes style header fields (Author, Description, Theme URI, Text Domain, and WP/PHP requirements).
- Parent theme metadata was normalized across `style.css` and `package.json` (added URI/author/repository fields).
- Removed `Open Theme Settings` link from the About tab to keep it informational only.
- Parent release/update docs were simplified by removing explicit child-theme out-of-scope lines.
- Parent release docs were rewritten as a concise procedure-only runbook.
- Parent docs were generalized to child-theme terminology and no longer use project-specific naming.
- Parent documentation requirements were aligned with theme headers (`WordPress 6.9+`, `PHP 8.2+`).
- Development docs now support solo workflow with optional PR (direct main push or PR merge).
- Git workflow documentation is now explicitly parent-only and does not reference child-theme flow.
- Parent release runbook now explicitly documents controlled release triggering (CI on `main`, release by explicit trigger/tag) with a Mermaid flow diagram.
- Parent release runbook now provides a strict manual step-by-step procedure with exact commands, plus a packaging example and simplified release flow diagram.
- Parent `README.md` now links `CONTRIBUTING.md` for PR workflow and expectations.
- Parent docs and contribution files now use repository-root-relative paths instead of full `wp-content/themes/...` paths.
- Developer workflow diagram now reflects intentional release flow (not automatic on every `main` update).
- Git workflow release section now clarifies that releases are intentional from `main` (trigger/tag based).
- Parent release runbook now separates current manual flow from target automation blueprint, with dedicated diagrams for both.
- Git workflow and developer diagrams were aligned with PR-first collaboration and intentional release decision points.
- Docs index text for release runbook now reflects current/manual + target/automation split.
- `docs/developer-guide.md` is now parent-only; child-specific guidance remains in `docs/child-theme-guide.md`.
- Parent release runbook now documents implemented `workflow_dispatch` release flow and CI/release trigger split.
- Git workflow release section now points to `workflow_dispatch` release trigger.
- Contributing guide now includes concrete required CI status checks: `build` and `php-lint`.
- Release workflow now builds GitHub release description from `CHANGELOG.md` (version section first, `Unreleased` fallback).
- GTM module runtime now reads native settings, with fallback migration from legacy ACF option fields when native data is not yet saved.
- Parent theme version was bumped from `0.2.0` to `0.2.1` in `style.css`, `package.json`, and `package-lock.json`.

### Fixed

- ACF block registration now follows resolved enabled block directories (avoids double registration in parent+child duplicates).
- Block discovery and asset caches were version-bumped to prevent stale conflict results.
- Module settings save now forces overridden parent modules to `false` to match child-priority runtime behavior.
- Release workflow now correctly parses `Version` from WordPress-style `style.css` headers.
- Parent theme updater now calls GitHub Releases API with valid `owner/repo` path format (fixes remote payload detection).
- GTM module Settings link in Theme Settings > Modules now points to a stable native tab (`Theme Settings -> GTM`) instead of ACF subpage detection.
- Module catalog cache keys now include parent/child `modules/` filesystem fingerprint, so deleted child modules stop showing as overrides without waiting for transient expiry.

### Removed

- Removed GTM module dependency on ACF options subpage and local ACF field registration files.

## [0.1.0] - 2026-02-22

### Added

- Initial framework setup (modules system, blocks pipeline, Vite integration, docs baseline).
