import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync, statSync } from "node:fs";
import { resolve, join } from "node:path";

const root = resolve(import.meta.dirname, "..");
const read = (relativePath) => readFileSync(resolve(root, relativePath), "utf8");

const viteSource = read("inc/core/vite.php");
const blocksAcfEnqueueSource = read("inc/core/blocks-acf-enqueue.php");
const blocksNativeSource = read("inc/core/blocks-native.php");
const modulesAdminSource = read("inc/core/modules_admin.php");
const phpOnlyRenderSource = read("blocks/php-only/example-php-only/render.php");

test("registers a wp_script_attributes filter so type=module actually renders", () => {
    assert.match(viteSource, /add_filter\('wp_script_attributes', 'lonestar_filter_module_script_attributes'\)/);
    assert.match(viteSource, /function lonestar_filter_module_script_attributes\(/);
    assert.match(viteSource, /wp_scripts\(\)->get_data\(\$handle, 'type'\)/);
    assert.match(viteSource, /\$attributes\['type'\] = 'module';/);
});

test("keeps recording module intent via wp_script_add_data for Vite client/entry/production assets", () => {
    const matches = viteSource.match(/wp_script_add_data\([^)]*'type',\s*'module'\)/g) || [];
    assert.equal(matches.length, 3, "expected the vite client, entry, and production main handles to record type=module");
});

test("does not mark classic block script/editorScript/viewScript handles as ES modules", () => {
    // Only viewScriptModule handles should ever become type=module; script/editorScript/
    // viewScript are built as classic non-ESM bundles and must not get type="module".
    assert.doesNotMatch(blocksAcfEnqueueSource, /wp_script_add_data\(\$js_handle, 'type', 'module'\)/);
});

test("registers viewScriptModule handles with the Script Modules API, not wp_register_script", () => {
    assert.match(
        blocksAcfEnqueueSource,
        /'js_handles'\s*=>\s*lonestar_collect_metadata_handles\(\$json_contents, array\('script', 'editorScript', 'viewScript'\), \$fallback_js_handle\)/,
    );
    assert.match(
        blocksAcfEnqueueSource,
        /'module_handles'\s*=>\s*lonestar_collect_metadata_handles\(\$json_contents, array\('viewScriptModule'\), ''\)/,
    );
    assert.match(blocksAcfEnqueueSource, /wp_register_script_module\(/);
});

test("bumps the block asset map transient cache key and flushes it on discovery reset", () => {
    assert.match(blocksAcfEnqueueSource, /lonestar_block_asset_map_v4_/);
    assert.doesNotMatch(blocksAcfEnqueueSource, /lonestar_block_asset_map_v3_/);
    assert.match(blocksNativeSource, /delete_transient\('lonestar_block_asset_map_v4_' \. \$cache_namespace\)/);
});

test("php-only example block closes its wrapper <section> tag", () => {
    assert.match(phpOnlyRenderSource, /<section <\?php echo \$wrapper_attributes;[^?]*\?>>/);
    assert.doesNotMatch(phpOnlyRenderSource, /<section <\?php echo \$wrapper_attributes;[^?]*\?>\s*\n\s*<h3>/);
});

test("Content Types admin screen uses the lonestar text domain only", () => {
    assert.doesNotMatch(modulesAdminSource, /__\([^)]*'lonestar-theme'\)/);
    assert.doesNotMatch(modulesAdminSource, /esc_html__\([^)]*'lonestar-theme'\)/);
});

const EXCLUDED_DIRS = new Set(["node_modules", ".git", "dist", "build", "coverage", ".claude"]);

function findPhpFiles(directory) {
    const results = [];
    for (const entry of readdirSync(directory)) {
        if (EXCLUDED_DIRS.has(entry)) continue;
        const fullPath = join(directory, entry);
        const stats = statSync(fullPath);
        if (stats.isDirectory()) {
            results.push(...findPhpFiles(fullPath));
        } else if (entry.endsWith(".php")) {
            results.push(fullPath);
        }
    }
    return results;
}

test("no PHP source uses the retired lonestar-theme text domain in translation calls", () => {
    const translationCallPattern = /\b(?:_e|__|_x|_ex|_n|_nx|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*[^)]*['"]lonestar-theme['"]/;
    const offenders = [];

    for (const filePath of findPhpFiles(root)) {
        const source = readFileSync(filePath, "utf8");
        if (translationCallPattern.test(source)) {
            offenders.push(filePath);
        }
    }

    assert.deepEqual(offenders, []);
});
