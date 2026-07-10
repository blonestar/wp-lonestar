# Contributing to Lonestar Parent Theme

This file defines contribution rules for this repository (Lonestar parent framework theme).

## Scope

- Contribute framework code only (parent theme).
- Do not change WordPress core folders (`wp-admin/`, `wp-includes/`).

## Branch and PR Model

- Base branch: `main`
- Use topic branches:
    - `feat/<short-slug>`
    - `fix/<short-slug>`
    - `chore/<short-slug>`
    - `refactor/<short-slug>`
- Open a PR into `main` for shared work.
- Keep PRs focused; avoid unrelated file changes.

## Commit and PR Titles

- Use Conventional Commit style:
    - `feat: ...`
    - `fix: ...`
    - `chore: ...`
    - `refactor: ...`
- Prefer the same style for PR titles to keep release notes clean.

## Validation Before PR

Run from repository root:

```bash
npm ci
npm run check
npm run browsers
```

```powershell
Get-ChildItem -Recurse -File -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Also do a manual smoke test for affected admin and frontend paths.

## CSS Tooling Contract

- Use standards-based CSS nesting supported by `postcss-nesting`.
- Keep Autoprefixer and the WordPress Browserslist profile in both parent and boilerplate child.
- Do not add `postcss-import`; Vite handles CSS imports.
- Do not add `postcss-nested`; its Sass-like syntax is outside the framework contract.
- Do not add Tailwind packages, configuration, directives, or generated utility output.

## Changelog and Docs

- If behavior changes, update docs in `docs/`.
- Update `CHANGELOG.md` under `## [Unreleased]` in the same PR.

## Release and Version Rules

- Regular feature/fix PRs should not bump:
    - `style.css` -> `Version`
    - `package.json` -> `version`
- Version bumps are for release preparation only.
- WordPress update availability depends on GitHub Release/tag+asset contract.

## Recommended GitHub Branch Protection (main)

- Require a pull request before merging.
- Require 1 approval.
- Dismiss stale approvals when new commits are pushed.
- Require conversation resolution before merging.
- Require status checks to pass before merging:
    - `Node 22`
    - `Node 24`
    - `PHP 8.2 lint`
    - `PHP 8.4 lint`
- Prefer squash merge for a clean history.
- Optional: require review from Code Owners.
