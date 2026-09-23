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
const modulesStateSource = read("inc/core/modules_state.php");
const debugHelperSource = read("inc/helpers/helper.debug.php");
const printrHelperSource = read("inc/helpers/helper.printr.php");
const shortcodeYearSource = read("inc/shortcodes/shortcode.year.php");
const shortcodeReservedSource = read("inc/shortcodes/shortcode.reserved.php");

function assertNoBareIdentifier(source, identifier, label = identifier) {
    assert.doesNotMatch(source, new RegExp(`(?<!LONESTAR_)\\b${identifier}\\b`), `${label} must not appear without the LONESTAR_ prefix`);
}

test("only LONESTAR_ constants are defined and consumed", () => {
    for (const constant of [
        "TEMPLATE_PATH",
        "TEMPLATE_URI",
        "ACF_BLOCKS_PATH",
        "NATIVE_BLOCKS_PATH",
        "PHP_ONLY_BLOCKS_PATH",
        "DIST_REL_PATH",
        "DIST_DEF",
        "DIST_URI",
        "DIST_PATH",
        "JS_DEPENDENCY",
        "JS_LOAD_IN_FOOTER",
        "VITE_SERVER",
        "VITE_ENTRY_POINT",
    ]) {
        for (const source of [functionsSource, viteSource, blocksAcfEnqueueSource]) {
            assertNoBareIdentifier(source, constant);
        }
    }

    for (const constant of [
        "MODULES_TOGGLE_OPTION",
        "BLOCKS_TOGGLE_OPTION",
        "MODULES_CATALOG_CACHE_TTL",
        "MODULES_DISABLE_SYSTEM",
        "MODULES_DISABLE_ALL",
        "MODULES_DISABLED",
        "IS_VITE_DEVELOPMENT",
    ]) {
        for (const source of [functionsSource, viteSource, blocksAcfEnqueueSource, modulesSource, modulesStateSource]) {
            assert.doesNotMatch(source, new RegExp(`\\b${constant}\\b`), `${constant} must not be supported`);
        }
    }

    for (const constant of ["LONESTAR_MODULE_TOGGLE_OPTION", "LONESTAR_BLOCK_TOGGLE_OPTION", "LONESTAR_MODULE_CATALOG_CACHE_TTL"]) {
        assert.match(modulesSource, new RegExp(`if \\(!defined\\('${constant}'\\)\\)`));
    }
});

test("vite.php no longer mentions Tailwind", () => {
    assert.doesNotMatch(viteSource, /Tailwind/i);
});

test("blocks-acf.php docblock references the LONESTAR_ constant names", () => {
    assert.doesNotMatch(blocksAcfSource, /\* - TEMPLATE_PATH:/);
    assert.match(blocksAcfSource, /LONESTAR_TEMPLATE_PATH/);
    assert.match(blocksAcfSource, /LONESTAR_ACF_BLOCKS_PATH/);
});

test("production Vite script/style handles are prefixed without unprefixed aliases", () => {
    assert.match(viteSource, /wp_enqueue_script\('lonestar-main',/);
    assert.match(viteSource, /wp_script_add_data\('lonestar-main', 'type', 'module'\)/);
    assert.doesNotMatch(viteSource, /wp_register_script\('main'/);

    assert.match(viteSource, /\$handle = '' !== \$filename \? 'lonestar-' \. \$filename : 'lonestar-main';/);
    assert.doesNotMatch(viteSource, /wp_register_style\(\$filename/);
});

test("debug helpers expose only prefixed names", () => {
    assert.match(debugHelperSource, /function lonestar_write_log\(\$log\)/);
    assert.match(debugHelperSource, /if \(defined\('WP_DEBUG'\) && WP_DEBUG\)/);
    assert.doesNotMatch(debugHelperSource, /true === WP_DEBUG/);
    assert.doesNotMatch(debugHelperSource, /function_exists\('write_log'\)|function\s+write_log\b/);

    assert.match(printrHelperSource, /function lonestar_printr\(\$arr, \$die = false\)/);
    assert.doesNotMatch(printrHelperSource, /function_exists\('printr'\)|function\s+printr\b/);
});

test("shortcodes keep their tags and expose only prefixed callbacks", () => {
    assert.match(shortcodeYearSource, /function lonestar_shortcode_year\(\)/);
    assert.match(shortcodeYearSource, /add_shortcode\('year', 'lonestar_shortcode_year'\)/);
    assert.match(shortcodeYearSource, /add_shortcode\('Y', 'lonestar_shortcode_year'\)/);
    assert.doesNotMatch(shortcodeYearSource, /function_exists\('theme_shortcode_year'\)|function\s+theme_shortcode_year\b/);

    assert.match(shortcodeReservedSource, /function lonestar_shortcode_reserved\(\)/);
    assert.match(shortcodeReservedSource, /add_shortcode\('R', 'lonestar_shortcode_reserved'\)/);
    assert.doesNotMatch(shortcodeReservedSource, /function_exists\('theme_shortcode_reserved'\)|function\s+theme_shortcode_reserved\b/);
});

test("module compatibility aliases and legacy behavior are absent", () => {
    assert.doesNotMatch(modulesStateSource, /lonestar_enabled_modules/);
    assert.doesNotMatch(modulesStateSource, /function\s+modules_get_enabled_module_slugs\b/);
    assert.doesNotMatch(modulesCatalogSource, /function\s+modules_get_module_php_files_for_scanning\b/);
    assert.doesNotMatch(modulesAdminSource, /function\s+modules_get_legacy_settings_page_slug\b|lonestar-theme-settings/);
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

test("modules.php defines only prefixed module options", () => {
    assert.match(modulesSource, /LONESTAR_MODULE_TOGGLE_OPTION/);
    assert.match(modulesSource, /LONESTAR_BLOCK_TOGGLE_OPTION/);
    assert.match(modulesSource, /LONESTAR_MODULE_CATALOG_CACHE_TTL/);
    assert.doesNotMatch(modulesSource, /Legacy namespace note|MODULES_TOGGLE_OPTION|BLOCKS_TOGGLE_OPTION|MODULES_CATALOG_CACHE_TTL/);
});
