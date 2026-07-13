<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('LONESTAR_CONTENT_TYPES_SIGNATURE_OPTION')) {
    define('LONESTAR_CONTENT_TYPES_SIGNATURE_OPTION', 'lonestar_content_types_signature');
}

/**
 * Return parent and, when active, child content type definition roots.
 *
 * @return array<string,string>
 */
function lonestar_get_content_type_definition_roots()
{
    $template = untrailingslashit(wp_normalize_path((string) get_template_directory()));
    $roots = array('template' => $template . '/inc/content-types');
    $stylesheet = untrailingslashit(wp_normalize_path((string) get_stylesheet_directory()));

    if ($stylesheet !== $template) {
        $roots['stylesheet'] = $stylesheet . '/inc/content-types';
    }

    return $roots;
}

/**
 * Discover direct definition files in deterministic source order.
 *
 * @return array<string,array<int,string>>
 */
function lonestar_get_content_type_definition_files()
{
    $files_by_source = array();
    foreach (lonestar_get_content_type_definition_roots() as $source => $root) {
        $files = glob($root . '/*.php');
        $files = is_array($files) ? $files : array();
        $files = array_values(array_filter($files, static function ($file) {
            return 'index.php' !== basename((string) $file) && is_file($file);
        }));
        usort($files, static function ($left, $right) {
            $comparison = strnatcasecmp($left, $right);
            return 0 !== $comparison ? $comparison : strcmp($left, $right);
        });
        $files_by_source[$source] = $files;
    }

    return $files_by_source;
}

/**
 * Emit a development-safe diagnostic without persistent notices.
 *
 * @param string $message Diagnostic message.
 * @param array<string,mixed> $context Optional context.
 * @return void
 */
function lonestar_content_types_diagnostic($message, $context = array())
{
    $message = (string) $message;
    $context = is_array($context) ? $context : array();
    if (isset($GLOBALS['lonestar_content_type_catalog_diagnostics']) && is_array($GLOBALS['lonestar_content_type_catalog_diagnostics'])) {
        $signature = md5($message . "\0" . serialize($context));
        if (!isset($GLOBALS['lonestar_content_type_catalog_diagnostics'][$signature])) {
            $GLOBALS['lonestar_content_type_catalog_diagnostics'][$signature] = array(
                'message' => $message,
                'context' => $context,
            );
        }
    }
    do_action('lonestar_content_types_diagnostic', $message, $context);

    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('_doing_it_wrong')) {
        _doing_it_wrong('lonestar_content_types', $message, '0.4.0');
    }
}

/**
 * Validate a slug without changing developer input.
 *
 * @param mixed $slug Candidate slug.
 * @param int $maximum_length WordPress maximum length.
 * @return string Empty string when invalid.
 */
function lonestar_validate_content_type_slug($slug, $maximum_length)
{
    if (!is_string($slug) || '' === $slug || strlen($slug) > $maximum_length) {
        return '';
    }

    return sanitize_key($slug) === $slug ? $slug : '';
}

/**
 * Validate a single definition array and return valid entities.
 *
 * @param mixed $definition Definition returned by a file or filter.
 * @param string $origin Diagnostic origin.
 * @return array{post_types:array<string,array<string,mixed>>,taxonomies:array<string,array<string,mixed>>}
 */
function lonestar_validate_content_type_definition($definition, $origin)
{
    $valid = array('post_types' => array(), 'taxonomies' => array());
    if (!is_array($definition)) {
        lonestar_content_types_diagnostic('Content type definition must return an array.', array('origin' => $origin));
        return $valid;
    }

    if (isset($definition['post_type'])) {
        $post_type = $definition['post_type'];
        $slug = is_array($post_type) && isset($post_type['slug']) ? lonestar_validate_content_type_slug($post_type['slug'], 20) : '';
        $args = is_array($post_type) && isset($post_type['args']) ? $post_type['args'] : array();
        if ('' === $slug || !is_array($args)) {
            lonestar_content_types_diagnostic('Invalid post type definition was skipped.', array('origin' => $origin));
        } else {
            $valid['post_types'][$slug] = $args;
        }
    }

    if (isset($definition['taxonomies'])) {
        if (!is_array($definition['taxonomies'])) {
            lonestar_content_types_diagnostic('Taxonomies must be an associative array.', array('origin' => $origin));
        } else {
            foreach ($definition['taxonomies'] as $slug => $taxonomy) {
                $valid_slug = lonestar_validate_content_type_slug($slug, 32);
                $object_types = is_array($taxonomy) && isset($taxonomy['object_types']) && is_array($taxonomy['object_types'])
                    ? $taxonomy['object_types']
                    : array();
                $args = is_array($taxonomy) && isset($taxonomy['args']) ? $taxonomy['args'] : array();
                $valid_object_types = array();
                $invalid_object_type = false;
                foreach ($object_types as $object_type) {
                    $object_type = lonestar_validate_content_type_slug($object_type, 20);
                    if ('' === $object_type) {
                        $invalid_object_type = true;
                        break;
                    }
                    if (!in_array($object_type, $valid_object_types, true)) {
                        $valid_object_types[] = $object_type;
                    }
                }

                if ('' === $valid_slug || $invalid_object_type || empty($valid_object_types) || !is_array($args)) {
                    lonestar_content_types_diagnostic('Invalid taxonomy definition was skipped.', array('origin' => $origin, 'taxonomy' => $slug));
                    continue;
                }
                $valid['taxonomies'][$valid_slug] = array('object_types' => $valid_object_types, 'args' => $args);
            }
        }
    }

    if (empty($valid['post_types']) && empty($valid['taxonomies'])) {
        lonestar_content_types_diagnostic('Content type definition contains no valid entities.', array('origin' => $origin));
    }

    return $valid;
}

/** Return an empty content-type catalog with the stable public shape. */
function lonestar_get_empty_content_type_catalog()
{
    return array(
        'post_types' => array('entries' => array(), 'effective' => array(), 'effective_entry_keys' => array()),
        'taxonomies' => array('entries' => array(), 'effective' => array(), 'effective_entry_keys' => array()),
        'diagnostics' => array(),
    );
}

/** Refresh dynamic WordPress existence facts on a catalog copy. */
function lonestar_apply_content_type_catalog_runtime_status($catalog)
{
    foreach (array('post_types', 'taxonomies') as $entity_type) {
        if (!isset($catalog[$entity_type]['entries']) || !is_array($catalog[$entity_type]['entries'])) {
            continue;
        }
        foreach ($catalog[$entity_type]['entries'] as &$entry) {
            $slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
            $entry['exists'] = '' !== $slug && ('post_types' === $entity_type ? post_type_exists($slug) : taxonomy_exists($slug));
            $entry['registered'] = $entry['exists'];
        }
        unset($entry);
    }

    return $catalog;
}

/**
 * Build the authoritative, request-local content type catalog.
 *
 * The structural catalog is computed once per request and shared by runtime
 * registration and Theme Settings. Pass true only when an intentional refresh
 * is required before registration or in isolated tests.
 *
 * @param bool $refresh Force a fresh structural catalog build.
 * @return array{post_types:array{entries:array<string,array<string,mixed>>,effective:array<string,array<string,mixed>>},taxonomies:array{entries:array<string,array<string,mixed>>,effective:array<string,array<string,mixed>>},diagnostics:array<int,array<string,mixed>>}
 */
function lonestar_get_content_type_catalog($refresh = false)
{
    static $cached_catalog = null;
    static $is_building = false;

    if (!$refresh && is_array($cached_catalog)) {
        return lonestar_apply_content_type_catalog_runtime_status($cached_catalog);
    }
    if ($is_building) {
        return lonestar_apply_content_type_catalog_runtime_status(is_array($cached_catalog) ? $cached_catalog : lonestar_get_empty_content_type_catalog());
    }

    $is_building = true;
    $had_previous_diagnostics = array_key_exists('lonestar_content_type_catalog_diagnostics', $GLOBALS);
    $previous_diagnostics = $had_previous_diagnostics ? $GLOBALS['lonestar_content_type_catalog_diagnostics'] : null;
    $GLOBALS['lonestar_content_type_catalog_diagnostics'] = array();
    $catalog = lonestar_get_empty_content_type_catalog();

    try {
        foreach (lonestar_get_content_type_definition_files() as $source => $files) {
            $seen = array('post_types' => array(), 'taxonomies' => array());
            foreach ($files as $file) {
                try {
                    $definition = require $file;
                } catch (Throwable $throwable) {
                    lonestar_content_types_diagnostic('Content type definition could not be loaded.', array('file' => $file, 'error' => $throwable->getMessage()));
                    continue;
                }

                $valid = lonestar_validate_content_type_definition($definition, $file);
                foreach (array('post_types', 'taxonomies') as $entity_type) {
                    foreach ($valid[$entity_type] as $slug => $entity) {
                        if (isset($seen[$entity_type][$slug])) {
                            lonestar_content_types_diagnostic('Duplicate content type identity in one source was skipped.', array('file' => $file, 'source' => $source, 'slug' => $slug));
                            continue;
                        }
                        $seen[$entity_type][$slug] = true;
                        $key = $source . ':' . $slug;
                        $declared_args = 'post_types' === $entity_type ? $entity : $entity['args'];
                        $declared_object_types = 'taxonomies' === $entity_type ? $entity['object_types'] : array();
                        $catalog[$entity_type]['entries'][$key] = array(
                            'key' => $key,
                            'entity_type' => $entity_type,
                            'slug' => $slug,
                            'args' => $declared_args,
                            'object_types' => $declared_object_types,
                            'declared_args' => $declared_args,
                            'declared_object_types' => $declared_object_types,
                            'effective_args' => $declared_args,
                            'effective_object_types' => $declared_object_types,
                            'source' => $source,
                            'file' => wp_normalize_path($file),
                            'effective' => true,
                            'overridden' => false,
                            'overriding_entry_key' => '',
                            'overriding_source' => '',
                            'filtered' => false,
                            'filtered_out' => false,
                            'exists' => false,
                            'registered' => false,
                        );
                        if (isset($catalog[$entity_type]['effective_entry_keys'][$slug])) {
                            $old_key = $catalog[$entity_type]['effective_entry_keys'][$slug];
                            $catalog[$entity_type]['entries'][$old_key]['effective'] = false;
                            $catalog[$entity_type]['entries'][$old_key]['overridden'] = true;
                            $catalog[$entity_type]['entries'][$old_key]['overriding_entry_key'] = $key;
                            $catalog[$entity_type]['entries'][$old_key]['overriding_source'] = $source;
                        }
                        $catalog[$entity_type]['effective'][$slug] = 'post_types' === $entity_type
                            ? $declared_args
                            : array('object_types' => $declared_object_types, 'args' => $declared_args);
                        $catalog[$entity_type]['effective_entry_keys'][$slug] = $key;
                    }
                }
            }
        }

        /**
         * Filters resolved content type definitions before registration.
         *
         * @param array $resolved Array with post_types and taxonomies maps.
         */
        $unfiltered = array(
            'post_types' => $catalog['post_types']['effective'],
            'taxonomies' => $catalog['taxonomies']['effective'],
        );
        $filtered = lonestar_validate_content_type_resolution(apply_filters('lonestar_content_type_definitions', $unfiltered));

        foreach (array('post_types', 'taxonomies') as $entity_type) {
            foreach ($catalog[$entity_type]['effective'] as $slug => $definition) {
                if (isset($filtered[$entity_type][$slug])) {
                    $entry_key = $catalog[$entity_type]['effective_entry_keys'][$slug];
                    $entry = &$catalog[$entity_type]['entries'][$entry_key];
                    $new_args = 'post_types' === $entity_type ? $filtered[$entity_type][$slug] : $filtered[$entity_type][$slug]['args'];
                    $new_object_types = 'taxonomies' === $entity_type ? $filtered[$entity_type][$slug]['object_types'] : array();
                    $entry['filtered'] = ($entry['declared_args'] !== $new_args || $entry['declared_object_types'] !== $new_object_types);
                    $entry['effective_args'] = $new_args;
                    $entry['effective_object_types'] = $new_object_types;
                    $catalog[$entity_type]['effective'][$slug] = 'post_types' === $entity_type
                        ? $new_args
                        : array('object_types' => $new_object_types, 'args' => $new_args);
                    unset($entry);
                    continue;
                }

                $entry_key = $catalog[$entity_type]['effective_entry_keys'][$slug];
                $catalog[$entity_type]['entries'][$entry_key]['effective'] = false;
                $catalog[$entity_type]['entries'][$entry_key]['filtered'] = true;
                $catalog[$entity_type]['entries'][$entry_key]['filtered_out'] = true;
                unset($catalog[$entity_type]['effective'][$slug], $catalog[$entity_type]['effective_entry_keys'][$slug]);
            }

            foreach ($filtered[$entity_type] as $slug => $definition) {
                if (isset($catalog[$entity_type]['effective'][$slug])) {
                    continue;
                }
                $key = 'filter:' . $slug;
                $filter_args = 'post_types' === $entity_type ? $definition : $definition['args'];
                $filter_object_types = 'taxonomies' === $entity_type ? $definition['object_types'] : array();
                $catalog[$entity_type]['entries'][$key] = array(
                    'key' => $key,
                    'entity_type' => $entity_type,
                    'slug' => $slug,
                    'args' => $filter_args,
                    'object_types' => $filter_object_types,
                    'declared_args' => $filter_args,
                    'declared_object_types' => $filter_object_types,
                    'effective_args' => $filter_args,
                    'effective_object_types' => $filter_object_types,
                    'source' => 'filter',
                    'file' => '',
                    'effective' => true,
                    'overridden' => false,
                    'overriding_entry_key' => '',
                    'overriding_source' => '',
                    'filtered' => true,
                    'filtered_out' => false,
                    'exists' => false,
                    'registered' => false,
                );
                $catalog[$entity_type]['effective'][$slug] = 'post_types' === $entity_type
                    ? $filter_args
                    : array('object_types' => $filter_object_types, 'args' => $filter_args);
                $catalog[$entity_type]['effective_entry_keys'][$slug] = $key;
            }
        }

        $catalog['diagnostics'] = array_values($GLOBALS['lonestar_content_type_catalog_diagnostics']);
        $cached_catalog = $catalog;
    } finally {
        if ($had_previous_diagnostics) {
            $GLOBALS['lonestar_content_type_catalog_diagnostics'] = $previous_diagnostics;
        } else {
            unset($GLOBALS['lonestar_content_type_catalog_diagnostics']);
        }
        $is_building = false;
    }

    return lonestar_apply_content_type_catalog_runtime_status($cached_catalog);
}

/**
 * Return filtered effective definitions for compatibility with the v1 API.
 *
 * @param bool $refresh Force a fresh request-local catalog build.
 * @return array{post_types:array<string,array<string,mixed>>,taxonomies:array<string,array<string,mixed>>}
 */
function lonestar_get_content_type_definitions($refresh = false)
{
    $catalog = lonestar_get_content_type_catalog($refresh);
    $definitions = array('post_types' => array(), 'taxonomies' => array());
    foreach ($catalog['post_types']['effective'] as $slug => $definition) {
        $definitions['post_types'][$slug] = $definition;
    }
    foreach ($catalog['taxonomies']['effective'] as $slug => $definition) {
        $definitions['taxonomies'][$slug] = $definition;
    }
    return $definitions;
}

/**
 * Revalidate a resolved definition map, including data supplied by filters.
 *
 * @param mixed $definitions Resolved definitions.
 * @return array{post_types:array<string,array<string,mixed>>,taxonomies:array<string,array<string,mixed>>}
 */
function lonestar_validate_content_type_resolution($definitions)
{
    $valid = array('post_types' => array(), 'taxonomies' => array());
    if (!is_array($definitions)) {
        lonestar_content_types_diagnostic('Filtered content type definitions must be an array.');
        return $valid;
    }
    foreach ((isset($definitions['post_types']) && is_array($definitions['post_types'])) ? $definitions['post_types'] : array() as $slug => $args) {
        $slug = lonestar_validate_content_type_slug($slug, 20);
        if ('' !== $slug && is_array($args)) {
            $valid['post_types'][$slug] = $args;
        } else {
            lonestar_content_types_diagnostic('Invalid filtered post type was skipped.');
        }
    }
    foreach ((isset($definitions['taxonomies']) && is_array($definitions['taxonomies'])) ? $definitions['taxonomies'] : array() as $slug => $taxonomy) {
        $definition = lonestar_validate_content_type_definition(array('taxonomies' => array($slug => $taxonomy)), 'filter');
        foreach ($definition['taxonomies'] as $valid_slug => $valid_taxonomy) {
            $valid['taxonomies'][$valid_slug] = $valid_taxonomy;
        }
    }
    return $valid;
}

/** Register resolved definitions after WordPress has loaded its core types. */
function lonestar_register_content_types()
{
    $catalog = lonestar_get_content_type_catalog(true);
    $definitions = array('post_types' => array(), 'taxonomies' => array());
    foreach ($catalog['post_types']['effective'] as $slug => $definition) {
        $definitions['post_types'][$slug] = $definition;
    }
    foreach ($catalog['taxonomies']['effective'] as $slug => $definition) {
        $definitions['taxonomies'][$slug] = $definition;
    }
    foreach ($definitions['post_types'] as $slug => $args) {
        if (post_type_exists($slug)) {
            lonestar_content_types_diagnostic('Post type already registered; definition skipped.', array('post_type' => $slug));
            continue;
        }
        register_post_type($slug, $args);
    }
    foreach ($definitions['taxonomies'] as $slug => $taxonomy) {
        if (taxonomy_exists($slug)) {
            lonestar_content_types_diagnostic('Taxonomy already registered; definition skipped.', array('taxonomy' => $slug));
            continue;
        }
        register_taxonomy($slug, $taxonomy['object_types'], $taxonomy['args']);
    }
}

/** Build a stable source-path/content signature for rewrite lifecycle checks. */
function lonestar_get_content_types_signature()
{
    $parts = array();
    foreach (lonestar_get_content_type_definition_files() as $source => $files) {
        foreach ($files as $file) {
            $parts[] = $source . "\0" . wp_normalize_path($file) . "\0" . (is_readable($file) ? sha1_file($file) : 'unreadable');
        }
    }
    return hash('sha256', implode("\n", $parts));
}

/** Refresh rewrites once after an admin-visible definition source change. */
function lonestar_maybe_refresh_content_type_rewrites()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    $files = lonestar_get_content_type_definition_files();
    $has_files = !empty($files['template']) || !empty($files['stylesheet']);
    $previous = get_option(LONESTAR_CONTENT_TYPES_SIGNATURE_OPTION, false);
    if (false === $previous && !$has_files) {
        return;
    }
    $signature = lonestar_get_content_types_signature();
    if ($signature === $previous) {
        return;
    }
    flush_rewrite_rules(false);
    update_option(LONESTAR_CONTENT_TYPES_SIGNATURE_OPTION, $signature, false);
}

add_action('init', 'lonestar_register_content_types', 5);
add_action('admin_init', 'lonestar_maybe_refresh_content_type_rewrites');
