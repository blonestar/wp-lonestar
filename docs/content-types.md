# Content Types

Lonestar discovers direct PHP files in `inc/content-types/` from the parent theme and, when active, the child theme. No child `functions.php` change is required. `index.php` is reserved as a silent placeholder and is ignored.

## Definition contract

Each file returns one array. It may define one `post_type`, any number of `taxonomies`, or only taxonomies:

```php
<?php
return array(
    'post_type' => array(
        'slug' => 'project',
        'args' => array('public' => true, 'show_in_rest' => true, 'rewrite' => array('slug' => 'projects')),
    ),
    'taxonomies' => array(
        'project_type' => array(
            'object_types' => array('project'),
            'args' => array('hierarchical' => true, 'show_in_rest' => true),
        ),
    ),
);
```

Slugs must already be lowercase `sanitize_key` values: post types are at most 20 characters and taxonomies at most 32. `args` must be arrays. Every taxonomy must explicitly provide a non-empty array of valid post-type `object_types`. Lonestar preserves supplied WordPress arguments and does not add public, REST, capability, or rewrite defaults.

A taxonomy-only child definition is valid:

```php
<?php
return array(
    'taxonomies' => array(
        'audience' => array('object_types' => array('project'), 'args' => array('show_in_rest' => true)),
    ),
);
```

## Precedence and diagnostics

Files are naturally sorted, parent first and child second. Identity is the post type or taxonomy slug, not the filename. Within one source the first valid identity wins; a child identity replaces the parent identity. Invalid entities, malformed return values, and loading exceptions are skipped without blocking other files. In development, diagnostics are emitted through `lonestar_content_types_diagnostic` and `_doing_it_wrong()` when `WP_DEBUG` is enabled.

After resolution and before registration, `lonestar_content_type_definitions` filters the map with `post_types` and `taxonomies` keys. Filtered values are revalidated. On `init` priority 5, all post types are registered before all taxonomies; types already registered by WordPress or plugins are left untouched.

## Catalog and Theme Settings overview

`lonestar_get_content_type_catalog()` is the request-local source of truth for both runtime registration and the informational `Appearance -> Theme Settings -> Content Types` tab. Its structural result is built once per request, while WordPress existence status is refreshed when the catalog is read. Its `post_types` and `taxonomies` sections each expose `entries` (all valid file and filter entries) and `effective` maps (the exact filtered definitions passed to registration); `diagnostics` contains unique scan, validation, duplicate, and load messages from that catalog build.

Each entry records its stable key, entity type, slug, source (`template`, `stylesheet`, or `filter`), source file (empty for filter-only entries), effective/override/filter state, and current WordPress existence status. File-backed entries preserve `declared_args`/`declared_object_types` separately from `effective_args`/`effective_object_types`, so filter changes never erase the source declaration. The overview is strictly read-only: it has no toggles, form submission, option writes, rewrite action, or generic Theme Settings POST handling. A currently registered entity is shown as a WordPress runtime fact, not as a claim that Lonestar owns that registration.

## Rewrite lifecycle and recommendations

On `admin_init`, only users who can `manage_options` compare a stable path/content signature with `lonestar_content_types_signature`. A changed source (including removal after a prior signature) triggers one soft rewrite flush and stores the new signature with autoload disabled. Frontend requests never flush or write this option.

For public editor-driven content, normally set `show_in_rest => true`; choose `capability_type`/`map_meta_cap` deliberately for restricted content, and set explicit rewrite slugs if URLs are part of the project contract. Full-site-editing templates follow core names such as `single-project.html`, `archive-project.html`, and `taxonomy-project_type.html`.

## v1 boundaries

This runtime supports only parent/child post types and taxonomies. It does not discover modules, register post statuses or meta, create ACF fields, add Theme Settings controls, or persist frontend state.
