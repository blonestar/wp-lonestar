import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const updaterSource = readFileSync(
    resolve(import.meta.dirname, "../inc/core/theme-updates.php"),
    "utf8",
);

function validateRelease(release) {
    if (!release || release.draft || release.prerelease) return false;
    const match = /^lonestar-v(\d+\.\d+\.\d+)$/.exec(release.tag_name || "");
    if (!match) return false;
    const expected = `lonestar-${match[1]}.zip`;
    const asset = (release.assets || []).find(
        (candidate) => candidate.name === expected,
    );
    return Boolean(
        asset &&
        asset.size > 0 &&
        /^https:\/\//.test(asset.browser_download_url || "") &&
        /^sha256:[a-f0-9]{64}$/.test(asset.digest || ""),
    );
}

const digest = `sha256:${"a".repeat(64)}`;
const valid = {
    tag_name: "lonestar-v0.3.0",
    draft: false,
    prerelease: false,
    assets: [
        {
            name: "lonestar-0.3.0.zip",
            browser_download_url: "https://example.test/lonestar-0.3.0.zip",
            digest,
            size: 1024,
        },
    ],
};

test("accepts only the exact stable release contract", () => {
    assert.equal(validateRelease(valid), true);
    assert.equal(validateRelease({ ...valid, draft: true }), false);
    assert.equal(validateRelease({ ...valid, prerelease: true }), false);
    assert.equal(validateRelease({ ...valid, tag_name: "v0.3.0" }), false);
    assert.equal(
        validateRelease({ ...valid, tag_name: "lonestar-v0.3.0-beta.1" }),
        false,
    );
});

test("rejects missing, mismatched, or unverifiable assets", () => {
    assert.equal(validateRelease({ ...valid, assets: [] }), false);
    assert.equal(
        validateRelease({
            ...valid,
            assets: [{ ...valid.assets[0], name: "lonestar-latest.zip" }],
        }),
        false,
    );
    assert.equal(
        validateRelease({
            ...valid,
            assets: [{ ...valid.assets[0], digest: "" }],
        }),
        false,
    );
    assert.equal(
        validateRelease({
            ...valid,
            assets: [{ ...valid.assets[0], size: 0 }],
        }),
        false,
    );
});

test("locks Git checkouts unless updates are explicitly allowed", () => {
    assert.match(updaterSource, /defined\('LONESTAR_ALLOW_UPDATES'\)/);
    assert.match(
        updaterSource,
        /file_exists\(trailingslashit\(get_template_directory\(\)\) \. '\.git'\)/,
    );
    assert.match(
        updaterSource,
        /if \(!lonestar_parent_theme_updates_allowed\(\)\) \{\s*return false;/,
    );
});

test("blocks a stale update download while the Git lock is active", () => {
    assert.match(updaterSource, /lonestar_updates_disabled/);
    assert.match(updaterSource, /Disabled for Git checkout\./);
});
