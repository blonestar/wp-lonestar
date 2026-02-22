# Lonestar Parent Theme Agent Guide

## Scope
- This is the framework parent theme.
- Prioritize reusable framework behavior in `inc/core/`, `modules/`, `blocks/`, and `docs/`.
- Do not edit WordPress core (`wp-admin/`, `wp-includes/`).

## Build and Validation
- Run Node commands from this directory:
  - `npm install`
  - `npm run dev`
  - `npm run build`
  - `npm run format`
- Run PHP lint for touched files:
  - `Get-ChildItem -Recurse -File -Filter *.php | ForEach-Object { php -l $_.FullName }`

## Coding Rules
- Use 4-space indentation in PHP and WordPress-safe guards (`if (!defined('ABSPATH'))`).
- Prefix new parent functions with `lonestar_`.
- Keep module/block slugs in kebab-case.

## Parent/Child Override Rules
- Child source has higher runtime priority when module/block identity collides with parent.
- Admin UI must clearly show overridden parent entries as non-editable where applicable.

## Changelog Discipline (Required)
- For every meaningful change, update `CHANGELOG.md` in this theme in the same turn.
- Add entries under `## [Unreleased]` with Keep a Changelog headings (`Added`, `Changed`, `Fixed`, `Removed`, `Security`).
- Keep entries concise and developer-focused.

## Versioning Discipline (Required)
- Use semantic versioning format `major.minor.patch`.
- Before commit/push for meaningful parent-theme changes, bump version in:
  - `style.css` (`Version` header)
  - `package.json` (`version`)
- If `package-lock.json` exists, keep root `version` values aligned with `package.json`.

## Documentation Discipline
- If behavior changes, update docs in `docs/` in the same change set.
