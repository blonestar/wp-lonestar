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
const emojiModuleSource = read("modules/module.disable-emoji.php");
const themeJson = JSON.parse(read("theme.json"));
const notFoundTemplate = read("templates/404.html");
const resetCss = read("assets/css/reset.css");

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

test("consolidates block discovery into a single runtime index transient", () => {
    assert.match(blocksAcfEnqueueSource, /function lonestar_get_block_runtime_index_transient_key\(\)\s*\{\s*return 'lonestar_block_runtime_v1';/);
    assert.match(blocksNativeSource, /delete_transient\(lonestar_get_block_runtime_index_transient_key\(\)\)/);
    assert.doesNotMatch(blocksNativeSource, /lonestar_(acf|native|php_only)_blocks_to_load|lonestar_blocks_to_scan|lonestar_block_asset_map/);
});

test("php-only example block closes its wrapper <section> tag", () => {
    assert.match(phpOnlyRenderSource, /<section <\?php echo \$lonestar_wrapper_attributes;[^?]*\?>>/);
    assert.doesNotMatch(phpOnlyRenderSource, /<section <\?php echo \$lonestar_wrapper_attributes;[^?]*\?>\s*\n\s*<h3>/);
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

test("emoji module removes all modern core emoji hooks, including wp_enqueue_emoji_styles", () => {
    assert.match(emojiModuleSource, /remove_action\('wp_head', 'print_emoji_detection_script', 7\)/);
    assert.match(emojiModuleSource, /remove_action\('wp_print_styles', 'print_emoji_styles'\)/);
    assert.match(emojiModuleSource, /remove_action\('wp_enqueue_scripts', 'wp_enqueue_emoji_styles'\)/);
    assert.match(emojiModuleSource, /remove_action\('admin_print_scripts', 'print_emoji_detection_script'\)/);
    assert.match(emojiModuleSource, /remove_action\('admin_print_styles', 'print_emoji_styles'\)/);
    assert.match(emojiModuleSource, /remove_action\('admin_enqueue_scripts', 'wp_enqueue_emoji_styles'\)/);
    assert.match(emojiModuleSource, /remove_action\('embed_head', 'print_emoji_detection_script'\)/);
    assert.match(emojiModuleSource, /remove_action\('enqueue_embed_scripts', 'wp_enqueue_emoji_styles'\)/);
});

test("emoji module also runs on admin_init so admin-only hooks (registered after init) are actually removed", () => {
    assert.match(emojiModuleSource, /add_action\('init', 'lonestar_module_disable_emojis'\)/);
    assert.match(emojiModuleSource, /add_action\('admin_init', 'lonestar_module_disable_emojis'\)/);
});

test("registers editor styles matching the frontend reset + Vite CSS", () => {
    assert.match(viteSource, /function lonestar_register_editor_styles\(/);
    assert.match(viteSource, /add_action\('after_setup_theme', 'lonestar_register_editor_styles', 20\)/);
    assert.match(viteSource, /add_editor_style\('assets\/css\/reset\.css'\)/);
    assert.match(viteSource, /if \(lonestar_is_vite_dev_mode\(\)\) \{\s*return;/);
});

test("theme.json uses a versioned schema and declares fluid typography", () => {
    assert.equal(themeJson.$schema, "https://schemas.wp.org/wp/7.1/theme.json");
    assert.equal(themeJson.settings.typography.fluid, true);
    for (const fontSize of themeJson.settings.typography.fontSizes) {
        if (fontSize.slug === "medium") {
            // Body text stays at a fixed readable size on small viewports.
            assert.equal(fontSize.fluid, false);
            continue;
        }
        assert.ok(fontSize.fluid, `expected fluid min/max on font size "${fontSize.slug}"`);
        assert.equal(fontSize.fluid.max, fontSize.size);
    }
});

test("theme.json declares link/heading element styles instead of relying solely on base CSS", () => {
    assert.ok(themeJson.styles.elements?.link, "expected styles.elements.link");
    assert.ok(themeJson.styles.elements?.heading, "expected styles.elements.heading");
    assert.equal(themeJson.styles.elements.link.typography.textDecoration, "underline");
});

test("404 template renders its content through a translatable pattern", () => {
    assert.match(notFoundTemplate, /<!-- wp:pattern \{"slug":"lonestar\/404-content"\} \/-->/);
    assert.doesNotMatch(notFoundTemplate, /404 - Page Not Found/);
});

test("reset.css no longer forces display:block on images (breaks inline/aligned images)", () => {
    assert.doesNotMatch(resetCss, /img,[\s\S]*?\{\s*display:\s*block/);
    assert.match(resetCss, /img,[\s\S]*?\{[\s\S]*?max-width:\s*100%/);
});

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
