# Changelog

All notable changes to the Lonestar parent theme are documented in this file.

## [Unreleased]

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
