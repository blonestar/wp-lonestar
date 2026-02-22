# Modules Anatomy

This document explains how Lonestar modules are structured and executed, and how that applies to EVBlog.

## 1) Where Module Discovery Happens

Module catalog and bootstrap are implemented in parent core:
- `wp-content/themes/lonestar/inc/core/modules.php`
- `wp-content/themes/lonestar/inc/core/modules_catalog.php`
- `wp-content/themes/lonestar/inc/core/modules_state.php`
- `wp-content/themes/lonestar/inc/core/modules_bootstrap.php`

Important default:
- `modules_get_modules_directory()` resolves to parent `get_template_directory() . '/modules'`.
- That means parent `lonestar/modules` is auto-discovered.
- Child `lonestar-evblog/modules` is not auto-discovered unless you add a custom bridge.

## 2) Module Types

### Flat module

File format:

```text
modules/module.<slug>.php
```

Best for very small, single-file features.

### Folder module

Directory format:

```text
modules/<slug>/
|-- module.<slug>.php
|-- inc/
|-- assets/
|-- blocks/acf/
|-- blocks/native/
`-- acf-json/
```

Best for medium and large features.

## 3) Folder Module Load Anatomy

Typical flow for an enabled folder module:

1. Catalog discovers module entry file `module.<slug>.php`.
2. Module toggle state is resolved from option `lonestar_module_toggles`.
3. `modules_boot_theme_modules()` boots enabled modules.
4. `modules_boot_single_module()` includes entry file and support convention files.
5. Module hooks/actions/filters become active at runtime.

Parent module state also supports:
- forced disable constants:
  - `MODULES_DISABLE_ALL`
  - `MODULES_DISABLED`
  - `LONESTAR_DISABLE_ALL_MODULES`
  - `LONESTAR_DISABLED_MODULES`
- sentinel file: `.disable-modules`

## 4) Convention Paths Used By Module Runtime

Depending on module shape and code, common module internals include:
- `inc/inc.*.php`
- `inc/helpers/*.php`
- `inc/shortcodes/*.php`
- `inc/walkers/*.php`
- `assets/*`
- `blocks/acf/*`
- `blocks/native/*`
- `acf-json/*`

For block-related assets:
- enabled module block roots are merged into the block asset/discovery pipeline.

## 5) EVBlog GTM Module Anatomy (Concrete Example)

Current EVBlog module structure:

```text
modules/gtm/
|-- module.gtm.php
`-- inc/
    |-- inc.main.php
    |-- inc.fields.php
    |-- inc.options-subpage.php
    `-- inc.gtm-script.php
```

Responsibility split:
- `module.gtm.php`  
  Bootstrap: requires `inc/inc.*.php`.
- `inc.main.php`  
  Hooks output into `wp_head` and `wp_body_open`.
- `inc.fields.php`  
  Registers ACF options fields (`gtm_enabled`, `gtm_id`).
- `inc.options-subpage.php`  
  Adds ACF options subpage under Theme Options.
- `inc.gtm-script.php`  
  Normalizes GTM ID and renders script/noscript markup.

## 6) Using Modules In Child Theme

Because parent discovery targets parent `/modules`, you have two options for EVBlog-only modules:

1. Preferred with current runtime: place active modules in parent `lonestar/modules` and keep them project-safe via toggles/config.
2. Custom extension: implement a child-aware discovery bridge so parent runtime can discover child modules too.

If you choose option 2, keep behavior explicit and documented in both parent and child docs.

## 7) Creating a New Module (Recommended Process)

1. Pick module type (flat vs folder).
2. Create `module.<slug>.php` with metadata docblock.
3. Add logic in `inc/` files and register hooks.
4. If needed, add ACF options/fields and `acf-json`.
5. If needed, add `blocks/acf` or `blocks/native` assets.
6. Enable via `Appearance -> Theme Modules`.
7. Validate frontend/admin and build artifacts.

## 8) Troubleshooting

- Module does not appear in admin list:
  - verify filename pattern `module.<slug>.php`,
  - verify module is under parent `lonestar/modules` unless custom bridge exists.
- Module appears but has no effect:
  - verify toggle is enabled,
  - verify hooks fire on expected action timing.
- ACF options page missing:
  - verify ACF Pro is active,
  - verify `acf/init` callbacks execute and no fatal errors occur.
