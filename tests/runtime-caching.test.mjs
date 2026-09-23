import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const read = (relativePath) => readFileSync(resolve(root, relativePath), "utf8");

const blocksAcfEnqueueSource = read("inc/core/blocks-acf-enqueue.php");
const blocksAcfSource = read("inc/core/blocks-acf.php");
const blocksNativeSource = read("inc/core/blocks-native.php");
const blocksPhpOnlySource = read("inc/core/blocks-php-only.php");
const modulesCatalogSource = read("inc/core/modules_catalog.php");

test("Vite dev auto-probe is restricted to local/development environment types and gated by a filter", () => {
    // Explicit opt-ins (LONESTAR_VITE_DEVELOPMENT / LONESTAR_VITE_DEV) still take
    // precedence and are handled before this gate.
    assert.match(blocksAcfEnqueueSource, /defined\('LONESTAR_VITE_DEVELOPMENT'\)/);
    assert.doesNotMatch(blocksAcfEnqueueSource, /IS_VITE_DEVELOPMENT/);
    assert.match(blocksAcfEnqueueSource, /getenv\('LONESTAR_VITE_DEV'\)/);

    // The automatic HTTP probe only runs for local/development environment types.
    assert.match(
        blocksAcfEnqueueSource,
        /\$probe_enabled = in_array\(\$probe_environment, array\('local', 'development'\), true\);/,
    );

    // A filter gates the automatic probe so integrators can override per environment.
    assert.match(
        blocksAcfEnqueueSource,
        /apply_filters\('lonestar_vite_dev_probe_enabled', \$probe_enabled, \$probe_environment\)/,
    );

    // The filter gate must run before any wp_remote_get probe call.
    const filterIndex = blocksAcfEnqueueSource.indexOf("lonestar_vite_dev_probe_enabled");
    const probeCallIndex = blocksAcfEnqueueSource.indexOf("wp_remote_get(");
    assert.ok(filterIndex > -1 && probeCallIndex > -1 && filterIndex < probeCallIndex);
});

test("query-string cache-busting only rewrites assets carrying the WordPress core version and never touches ABSPATH", () => {
    const fn = blocksAcfEnqueueSource.slice(
        blocksAcfEnqueueSource.indexOf("function lonestar_remove_query_string_from_static_files"),
        blocksAcfEnqueueSource.indexOf("add_filter('style_loader_src'"),
    );

    // Must compare the asset's `ver` query value against the WP core version
    // (i.e. only rewrite assets enqueued without an explicit version).
    assert.match(fn, /get_bloginfo\('version'\)/);
    assert.match(fn, /\$ver_args\['ver'\]/);

    // Must not resolve the filesystem path via ABSPATH (wrong when
    // WP_CONTENT_DIR lives outside ABSPATH, e.g. Bedrock-style installs).
    assert.doesNotMatch(fn, /ABSPATH/);

    // Must resolve the path via the template/stylesheet directory instead.
    assert.match(blocksAcfEnqueueSource, /function lonestar_theme_asset_url_to_path\(\$url\)/);
    assert.match(fn, /lonestar_theme_asset_url_to_path\(\$src\)/);

    // Must memoize resolved mtimes per request instead of calling filemtime()
    // unconditionally for every enqueued theme asset.
    assert.match(fn, /static \$mtime_memo = array\(\);/);
});

test("consolidates block discovery + metadata + asset map into a single runtime index", () => {
    assert.match(blocksAcfEnqueueSource, /function lonestar_get_block_runtime_index\(\)/);
    assert.match(blocksAcfEnqueueSource, /function lonestar_build_block_runtime_index\(\)/);
    assert.match(
        blocksAcfEnqueueSource,
        /function lonestar_get_block_runtime_index_transient_key\(\)\s*\{\s*return 'lonestar_block_runtime_v1';/,
    );

    // Only one set_transient call should persist the runtime index (fixed key).
    const setTransientMatches = blocksAcfEnqueueSource.match(/set_transient\(\$transient_key, \$index,/g) || [];
    assert.equal(setTransientMatches.length, 1);

    // Dev mode still bypasses the transient but is request-memoized via a static var.
    assert.match(blocksAcfEnqueueSource, /static \$index = null;/);
    assert.match(blocksAcfEnqueueSource, /if \(lonestar_is_vite_dev_mode\(\)\) \{\s*\$index = lonestar_build_block_runtime_index\(\);/);

    // Each block family register function reads from the shared index instead
    // of maintaining its own transient + its own file_get_contents/json_decode pass.
    for (const [label, source] of [
        ["ACF", blocksAcfSource],
        ["native", blocksNativeSource],
        ["php-only", blocksPhpOnlySource],
    ]) {
        assert.match(source, /lonestar_get_block_runtime_index\(\)/, `${label} register function must read the runtime index`);
        assert.doesNotMatch(source, /set_transient\(/, `${label} register function must not manage its own transient`);
        assert.doesNotMatch(
            source,
            /file_get_contents\(\$(block|metadata_path)\)/,
            `${label} register function must reuse cached decoded metadata instead of re-reading block.json`,
        );
    }
});

test("module catalog bumps its schema to v5 and defers translation to read time", () => {
    assert.match(modulesCatalogSource, /\$catalog_schema_version = 'v5';/);

    // Source values + textdomain are cached; translation happens in a
    // dedicated localize step applied after both cache hit and cache miss.
    assert.match(modulesCatalogSource, /function lonestar_localize_module_catalog\(\$catalog\)/);
    assert.match(modulesCatalogSource, /'label_textdomain'\s*=>/);
    assert.match(modulesCatalogSource, /'description_textdomain'\s*=>/);

    const catalogFn = modulesCatalogSource.slice(
        modulesCatalogSource.indexOf("function lonestar_get_module_catalog()"),
        modulesCatalogSource.indexOf("function lonestar_module_slug_from_entry_file"),
    );

    // Cache hit path: refresh availability, then localize, before returning.
    assert.match(
        catalogFn,
        /lonestar_refresh_module_catalog_availability\(\$cached_catalog\);\s*\$catalog = lonestar_localize_module_catalog\(\$catalog\);\s*return \$catalog;/,
    );

    // Cache miss / fresh-build path: cache the raw catalog, then localize before returning.
    assert.match(catalogFn, /set_transient\(\$cache_key, \$catalog, LONESTAR_MODULE_CATALOG_CACHE_TTL\);\s*\}\s*\$catalog = lonestar_localize_module_catalog\(\$catalog\);/);
});

test("module admin link labels also defer translation to read time", () => {
    assert.match(modulesCatalogSource, /function lonestar_add_module_admin_page_link\(&\$links, &\$seen_pages, \$page_slug, \$label, \$textdomain = ''\)/);
    assert.doesNotMatch(
        modulesCatalogSource.slice(
            modulesCatalogSource.indexOf("function lonestar_get_module_admin_links"),
            modulesCatalogSource.indexOf("function lonestar_add_module_admin_page_link"),
        ),
        /lonestar_translate_module_metadata_value/,
    );
});

test("module source fingerprint supports a fast mode gated by a filter", () => {
    assert.match(modulesCatalogSource, /function lonestar_get_module_fingerprint_mode\(\)/);
    assert.match(modulesCatalogSource, /apply_filters\('lonestar_module_fingerprint_mode', \$default_mode\)/);
    assert.match(modulesCatalogSource, /\$default_mode = \('production' === \$environment\) \? 'fast' : 'full';/);
    assert.match(modulesCatalogSource, /if \('fast' === lonestar_get_module_fingerprint_mode\(\)\) \{/);
});

test("block runtime index cache is invalidated when ACF availability changes", () => {
    const source = readFileSync(resolve(import.meta.dirname, "../inc/core/blocks-acf-enqueue.php"), "utf8");
    assert.match(source, /lonestar_is_acf_block_runtime_available\(\) \? '\|acf' : '\|no-acf'/);
});
