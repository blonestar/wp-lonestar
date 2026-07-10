import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import autoprefixer from "autoprefixer";
import browserslist from "browserslist";
import postcss from "postcss";
import nesting from "postcss-nesting";

const root = resolve(import.meta.dirname, "..");
const packageJson = JSON.parse(
    readFileSync(resolve(root, "package.json"), "utf8"),
);
const postcssConfig = readFileSync(resolve(root, "postcss.config.js"), "utf8");

test("CSS tooling uses the shared WordPress browser policy", () => {
    assert.deepEqual(packageJson.browserslist, [
        "extends @wordpress/browserslist-config",
    ]);
    assert.ok(browserslist(undefined, { path: root }).length > 0);
    assert.match(postcssConfig, /postcss-nesting/);
    assert.match(postcssConfig, /autoprefixer/);
    assert.doesNotMatch(postcssConfig, /postcss-(?:import|nested)["']/);
});

test("standards-based nesting is flattened before prefixes are added", async () => {
    const result = await postcss([nesting(), autoprefixer()]).process(
        ".card { & .label { user-select: none; } }",
        { from: resolve(root, "tests/fixtures/css-pipeline.css") },
    );

    assert.match(result.css, /\.card \.label/);
    assert.doesNotMatch(result.css, /&/);
    assert.match(result.css, /-webkit-user-select:\s*none/);
});
