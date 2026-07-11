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

/**
 * Load and validate every definition file, with child identities overriding parent.
 *
 * @return array{post_types:array<string,array<string,mixed>>,taxonomies:array<string,array<string,mixed>>}
 */
function lonestar_get_content_type_definitions()
{
    $resolved = array('post_types' => array(), 'taxonomies' => array());
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
                    $resolved[$entity_type][$slug] = $entity;
                }
            }
        }
    }

    /**
     * Filters resolved content type definitions before registration.
     *
     * @param array $resolved Array with post_types and taxonomies maps.
     */
    $resolved = apply_filters('lonestar_content_type_definitions', $resolved);
    return lonestar_validate_content_type_resolution($resolved);
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
    $definitions = lonestar_get_content_type_definitions();
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
