# Parent Release and Update Procedure

The parent theme uses a manually started, verified GitHub Actions release. Normal pushes never publish a release.

## Public contract

- Installed folder and stylesheet: `lonestar`
- Update URI: `https://github.com/blonestar/wp-lonestar`
- Stable tag: `lonestar-vX.Y.Z`
- ZIP asset: `lonestar-X.Y.Z.zip`
- Checksum asset: `lonestar-X.Y.Z.zip.sha256`
- ZIP root: `lonestar/`

WordPress checks releases through `update_themes_github.com`. Drafts, prereleases, non-semver tags, mismatched asset names, empty assets, and releases without a GitHub `sha256:` digest are rejected.

Before an update is unpacked, `upgrader_pre_download` downloads the exact asset and compares its SHA-256 with the verified GitHub API digest. A mismatch fails closed. WordPress always detects a newer valid release; installation remains governed by the normal per-theme auto-update setting.

The self-hosted updater is loaded when `lonestar` is active or is the parent of the active child theme. Cached status is visible under Theme Settings -> About and Site Health -> Info. `LONESTAR_GITHUB_TOKEN` may be defined privately to increase API rate limits; it is never displayed.

A development checkout containing `.git` skips the GitHub check and blocks package downloads, preventing WordPress from replacing repository files. Release ZIPs do not contain `.git`, so their updater remains enabled. `LONESTAR_ALLOW_UPDATES` may be defined as `true` or `false` in `wp-config.php` when an explicit override is required.

## Release preparation

1. Create `chore/release-X.Y.Z` from current `main`.
2. Move release notes into an exact `## [X.Y.Z]` changelog section.
3. Align `style.css`, `package.json`, and root `package-lock.json` versions.
4. Run `npm ci`, `npm run check`, PHP lint, and WordPress smoke tests.
5. Merge the reviewed release PR to `main`.
6. In GitHub Actions run `Release` from `main` with input `X.Y.Z`.

## Workflow guarantees

The workflow uses Node 24 and PHP 8.2, pinned action SHAs, least-privilege job permissions, a protected `release` environment, and a concurrency lock. It validates JSON, formatting, contracts, npm audit, PHP syntax, the tracked Vite build, package contents, exact changelog section, and version parity.

It then creates the ZIP and checksum, verifies the archive root/exclusions, generates a build-provenance attestation, creates an annotated tag, uploads a draft release, compares GitHub's asset digest with the local digest, and publishes the draft only after every check passes.

If a post-tag step fails, no published release is visible to WordPress. Resolve the draft/tag deliberately; do not reuse or overwrite a published version.
