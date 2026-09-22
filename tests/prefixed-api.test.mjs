import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const read = (relativePath) => readFileSync(resolve(root, relativePath), "utf8");

const functionsSource = read("functions.php");
const viteSource = read("inc/core/vite.php");
const blocksAcfEnqueueSource = read("inc/core/blocks-acf-enqueue.php");
const blocksAcfSource = read("inc/core/blocks-acf.php");
const modulesCatalogSource = read("inc/core/modules_catalog.php");
const modulesAdminSource = read("inc/core/modules_admin.php");
const modulesSource = read("inc/core/modules.php");
const debugHelperSource = read("inc/helpers/helper.debug.php");
const printrHelperSource = read("inc/helpers/helper.printr.php");
const shortcodeYearSource = read("inc/shortcodes/shortcode.year.php");
const shortcodeReservedSource = read("inc/shortcodes/shortcode.reserved.php");

test("vite.php still defines legacy Vite/build constants alongside the LONESTAR_ primary names", () => {
    for (const legacy of ["DIST_DEF", "DIST_URI", "DIST_PATH", "JS_DEPENDENCY", "JS_LOAD_IN_FOOTER", "VITE_SERVER", "VITE_ENTRY_POINT"]) {
        assert.match(viteSource, new RegExp(`if \\(!defined\\('LONESTAR_${legacy}'\\)\\)`), `expected LONESTAR_${legacy} resolution block`);
        assert.match(viteSource, new RegExp(`if \\(!defined\\('${legacy}'\\)\\) \\{\\s*define\\('${legacy}', LONESTAR_${legacy}\\);`), `expected legacy ${legacy} to be (re)defined from LONESTAR_${legacy}`);
    }
});

test("functions.php still defines legacy TEMPLATE_PATH/TEMPLATE_URI/*_BLOCKS_PATH/DIST_REL_PATH aliases", () => {
    for (const [legacy, current] of [
        ["TEMPLATE_PATH", "LONESTAR_TEMPLATE_PATH"],
        ["TEMPLATE_URI", "LONESTAR_TEMPLATE_URI"],
        ["ACF_BLOCKS_PATH", "LONESTAR_ACF_BLOCKS_PATH"],
        ["NATIVE_BLOCKS_PATH", "LONESTAR_NATIVE_BLOCKS_PATH"],
        ["PHP_ONLY_BLOCKS_PATH", "LONESTAR_PHP_ONLY_BLOCKS_PATH"],
        ["DIST_REL_PATH", "LONESTAR_DIST_REL_PATH"],
    ]) {
        assert.match(functionsSource, new RegExp(`define\\('${legacy}', ${current}\\)`), `expected ${legacy} alias defined from ${current}`);
    }
});

test("internal theme code uses LONESTAR_ vite/build constants, not the legacy bare names, outside the alias-definition blocks", () => {
    // vite.php: strip the constant-definition header (up to the first add_action call)
    // before scanning for legacy-name usage, since that block intentionally
    // references the legacy names to resolve/backfill them.
    const viteBody = viteSource.slice(viteSource.indexOf("add_action("));
    for (const legacy of ["VITE_SERVER", "VITE_ENTRY_POINT", "DIST_PATH", "DIST_URI", "DIST_DEF", "JS_DEPENDENCY", "JS_LOAD_IN_FOOTER", "TEMPLATE_PATH", "TEMPLATE_URI"]) {
        const bareUsage = new RegExp(`(?<!LONESTAR_)\\b${legacy}\\b`, "g");
        assert.doesNotMatch(viteBody, bareUsage, `vite.php body should not reference bare ${legacy}`);
    }

    for (const legacy of ["VITE_SERVER", "TEMPLATE_PATH", "TEMPLATE_URI", "ACF_BLOCKS_PATH", "NATIVE_BLOCKS_PATH", "PHP_ONLY_BLOCKS_PATH", "DIST_REL_PATH", "DIST_PATH", "DIST_URI"]) {
        const bareUsage = new RegExp(`(?<!LONESTAR_)\\b${legacy}\\b`, "g");
        assert.doesNotMatch(blocksAcfEnqueueSource, bareUsage, `blocks-acf-enqueue.php should not reference bare ${legacy}`);
    }
});

test("lonestar_is_vite_dev_mode() honors LONESTAR_VITE_DEVELOPMENT ahead of the legacy IS_VITE_DEVELOPMENT constant", () => {
    assert.match(blocksAcfEnqueueSource, /if \(defined\('LONESTAR_VITE_DEVELOPMENT'\)\)\s*\{\s*return LONESTAR_VITE_DEVELOPMENT === true;/);
    assert.match(blocksAcfEnqueueSource, /if \(defined\('IS_VITE_DEVELOPMENT'\)\)\s*\{\s*return IS_VITE_DEVELOPMENT === true;/);
});

test("vite.php no longer mentions Tailwind", () => {
    assert.doesNotMatch(viteSource, /Tailwind/i);
});

test("blocks-acf.php docblock references the LONESTAR_ constant names", () => {
    assert.doesNotMatch(blocksAcfSource, /\* - TEMPLATE_PATH:/);
    assert.match(blocksAcfSource, /LONESTAR_TEMPLATE_PATH/);
    assert.match(blocksAcfSource, /LONESTAR_ACF_BLOCKS_PATH/);
});

test("production Vite script/style handles are prefixed with backward-compatible unprefixed aliases", () => {
    assert.match(viteSource, /wp_enqueue_script\('lonestar-main',/);
    assert.match(viteSource, /wp_script_add_data\('lonestar-main', 'type', 'module'\)/);
    assert.match(viteSource, /if \(!wp_script_is\('main', 'registered'\)\)\s*\{\s*wp_register_script\('main', false, array\('lonestar-main'\)\);/);

    assert.match(viteSource, /\$handle = '' !== \$filename \? 'lonestar-' \. \$filename : 'lonestar-main';/);
    assert.match(viteSource, /if \('' !== \$filename && !wp_style_is\(\$filename, 'registered'\)\)\s*\{\s*wp_register_style\(\$filename, false, array\(\$handle\)\);/);
});

test("lonestar_write_log()/lonestar_printr() are the primary names, with write_log()/printr() kept as guarded aliases", () => {
    assert.match(debugHelperSource, /function lonestar_write_log\(\$log\)/);
    assert.match(debugHelperSource, /if \(defined\('WP_DEBUG'\) && WP_DEBUG\)/);
    assert.doesNotMatch(debugHelperSource, /true === WP_DEBUG/);
    assert.match(debugHelperSource, /if \(!function_exists\('write_log'\)\)/);
    assert.match(debugHelperSource, /function write_log\(\$log\)\s*\{\s*lonestar_write_log\(\$log\);/);

    assert.match(printrHelperSource, /function lonestar_printr\(\$arr, \$die = false\)/);
    assert.match(printrHelperSource, /if \(!function_exists\('printr'\) && function_exists\('lonestar_printr'\)\)/);
    assert.match(printrHelperSource, /function printr\(\$arr, \$die = false\)\s*\{\s*lonestar_printr\(\$arr, \$die\);/);
});

test("shortcodes register the prefixed callbacks but keep the [R]/[Y]/[year] tags and legacy theme_shortcode_* aliases", () => {
    assert.match(shortcodeYearSource, /function lonestar_shortcode_year\(\)/);
    assert.match(shortcodeYearSource, /add_shortcode\('year', 'lonestar_shortcode_year'\)/);
    assert.match(shortcodeYearSource, /add_shortcode\('Y', 'lonestar_shortcode_year'\)/);
    assert.match(shortcodeYearSource, /if \(!function_exists\('theme_shortcode_year'\)\)/);
    assert.match(shortcodeYearSource, /function theme_shortcode_year\(\)\s*\{\s*return lonestar_shortcode_year\(\);/);

    assert.match(shortcodeReservedSource, /function lonestar_shortcode_reserved\(\)/);
    assert.match(shortcodeReservedSource, /add_shortcode\('R', 'lonestar_shortcode_reserved'\)/);
    assert.match(shortcodeReservedSource, /if \(!function_exists\('theme_shortcode_reserved'\)\)/);
    assert.match(shortcodeReservedSource, /function theme_shortcode_reserved\(\)\s*\{\s*return lonestar_shortcode_reserved\(\);/);
});

test("modules_get_module_admin_links() has no unreachable code after its final return", () => {
    const start = modulesCatalogSource.indexOf("function modules_get_module_admin_links(");
    assert.notEqual(start, -1, "expected to find modules_get_module_admin_links()");

    // Slice out the function body between its opening and closing braces by
    // tracking brace depth from the first '{' after the signature.
    const braceStart = modulesCatalogSource.indexOf("{", start);
    let depth = 0;
    let end = -1;
    for (let i = braceStart; i < modulesCatalogSource.length; i++) {
        if (modulesCatalogSource[i] === "{") depth++;
        if (modulesCatalogSource[i] === "}") {
            depth--;
            if (depth === 0) {
                end = i;
                break;
            }
        }
    }
    assert.notEqual(end, -1, "expected to find the closing brace of modules_get_module_admin_links()");

    const body = modulesCatalogSource.slice(braceStart, end);
    assert.doesNotMatch(body, /Deprecated unreachable compatibility code/);

    // The function's only return of $links should be immediately followed
    // (modulo whitespace/the closing brace) by the end of the function body.
    const lastReturnIndex = body.lastIndexOf("return $links;");
    assert.notEqual(lastReturnIndex, -1);
    const afterReturn = body.slice(lastReturnIndex + "return $links;".length).trim();
    assert.equal(afterReturn, "", "expected no statements after the final return $links;");
});

test("modules_handle_modules_admin_post() guards $_SERVER['REQUEST_METHOD'] with isset()", () => {
    assert.match(modulesAdminSource, /if \(!isset\(\$_SERVER\['REQUEST_METHOD'\]\) \|\| 'POST' !== strtoupper/);
});

test("modules.php documents the legacy modules_* namespace as scheduled for lonestar_module_* at 1.0", () => {
    assert.match(modulesSource, /Legacy namespace note/);
    assert.match(modulesSource, /lonestar_module_\*/);
});
