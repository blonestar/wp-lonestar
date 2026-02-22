# Changelog

All notable changes to the Lonestar parent theme are documented in this file.

## [Unreleased]

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

### Changed
- Block conflict resolution now enforces child-theme override priority over parent blocks with the same identity.
- In Theme Settings > Blocks, overridden parent blocks are shown as disabled checkboxes with override note.
- In Theme Settings > Modules, overridden parent modules are now shown as disabled checkboxes with override note.
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

### Fixed
- ACF block registration now follows resolved enabled block directories (avoids double registration in parent+child duplicates).
- Block discovery and asset caches were version-bumped to prevent stale conflict results.
- Module settings save now forces overridden parent modules to `false` to match child-priority runtime behavior.

## [0.1.0] - 2026-02-22

### Added
- Initial framework setup (modules system, blocks pipeline, Vite integration, docs baseline).
