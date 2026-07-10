import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { lonestarFindBlockDirectories, lonestarGetBlockRoots } from "../vite-block-discovery.mjs";
import { lonestarGetBlockEntryPoints } from "../vite-entry-points.mjs";

const root = resolve(import.meta.dirname, "..");

function readMetadata(directory) {
    return JSON.parse(readFileSync(resolve(directory, "block.json"), "utf8"));
}

test("discovers all three block families", () => {
    const roots = lonestarGetBlockRoots(root);
    assert.ok(roots.some((path) => path.endsWith("/blocks/acf")));
    assert.ok(roots.some((path) => path.endsWith("/blocks/native")));
    assert.ok(roots.some((path) => path.endsWith("/blocks/php-only")));

    const names = roots.flatMap(lonestarFindBlockDirectories).map((directory) => readMetadata(directory).name);
    assert.deepEqual(
        names.sort(),
        [
            "lonestar/example-acf",
            "lonestar/example-native",
            "lonestar/example-native-static",
            "lonestar/example-php-only",
        ].sort()
    );
});

test("reference blocks satisfy their implementation contracts", () => {
    const dynamicDirectory = resolve(root, "blocks/native/example-native");
    const dynamic = readMetadata(dynamicDirectory);
    assert.ok(dynamic.editorScript);
    assert.equal(dynamic.render, "file:./render.php");
    assert.ok(existsSync(resolve(dynamicDirectory, "render.php")));

    const staticDirectory = resolve(root, "blocks/native/example-native-static");
    const staticBlock = readMetadata(staticDirectory);
    assert.ok(staticBlock.editorScript);
    assert.equal("render" in staticBlock, false);

    const acfDirectory = resolve(root, "blocks/acf/example-acf");
    const acf = readMetadata(acfDirectory);
    assert.equal(acf.acf.renderTemplate, "render.php");
    assert.ok(existsSync(resolve(acfDirectory, "fields.php")));

    const phpOnlyDirectory = resolve(root, "blocks/php-only/example-php-only");
    const phpOnly = readMetadata(phpOnlyDirectory);
    assert.equal(phpOnly.supports.autoRegister, true);
    assert.equal(phpOnly.render, "file:./render.php");
    assert.equal("editorScript" in phpOnly, false);
});

test("Vite builds native and ACF assets but leaves file assets to WordPress", () => {
    const entries = lonestarGetBlockEntryPoints(root);
    assert.ok(entries["blocks-native-example-native-js"]);
    assert.ok(entries["blocks-native-example-native-static-js"]);
    assert.ok(entries["blocks-acf-example-acf-js"]);
    assert.equal(entries["blocks-php-only-example-php-only-css"], undefined);
});
