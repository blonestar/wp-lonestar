<?php
/** Standalone behavior test for the Lonestar content types runtime. */
define('ABSPATH', __DIR__ . '/');
define('WP_DEBUG', false);

$hooks = array(); $filters = array(); $registered_post_types = array(); $registered_taxonomies = array();
$options = array(); $option_updates = array(); $flushes = 0; $admin = false; $can_manage = false; $diagnostics = array();
function add_action($hook, $callback, $priority = 10) { global $hooks; $hooks[$hook][$priority][] = $callback; }
function do_action($hook) { global $hooks; $args = array_slice(func_get_args(), 1); if (empty($hooks[$hook])) return; ksort($hooks[$hook]); foreach ($hooks[$hook] as $callbacks) foreach ($callbacks as $callback) call_user_func_array($callback, $args); }
function add_filter($hook, $callback, $priority = 10) { global $filters; $filters[$hook][$priority][] = $callback; }
function apply_filters($hook, $value) { global $filters; if (empty($filters[$hook])) return $value; ksort($filters[$hook]); $args = array_slice(func_get_args(), 1); foreach ($filters[$hook] as $callbacks) foreach ($callbacks as $callback) { $args[0] = call_user_func_array($callback, $args); } return $args[0]; }
function get_template_directory() { global $template; return $template; } function get_stylesheet_directory() { global $stylesheet; return $stylesheet; }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); } function untrailingslashit($path) { return rtrim($path, '/\\'); }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key)); }
function post_type_exists($slug) { global $registered_post_types; return isset($registered_post_types[$slug]); } function taxonomy_exists($slug) { global $registered_taxonomies; return isset($registered_taxonomies[$slug]); }
function register_post_type($slug, $args) { global $registered_post_types, $registration_order; $registered_post_types[$slug] = $args; $registration_order[] = 'post:' . $slug; }
function register_taxonomy($slug, $types, $args) { global $registered_taxonomies, $registration_order; $registered_taxonomies[$slug] = array($types, $args); $registration_order[] = 'tax:' . $slug; }
function is_admin() { global $admin; return $admin; } function current_user_can($cap) { global $can_manage; return $can_manage; }
function get_option($name, $default = false) { global $options; return array_key_exists($name, $options) ? $options[$name] : $default; }
function update_option($name, $value, $autoload = null) { global $options, $option_updates; $options[$name] = $value; $option_updates[] = array($name, $value, $autoload); return true; } function flush_rewrite_rules($hard = true) { global $flushes; ++$flushes; }
function _doing_it_wrong() {} function assert_true($value, $message) { if (!$value) throw new Exception($message); }

require dirname(__DIR__) . '/inc/core/content-types.php';
add_action('lonestar_content_types_diagnostic', function ($message) { global $diagnostics; $diagnostics[] = $message; });
$base = sys_get_temp_dir() . '/lonestar-content-types-' . uniqid(); $template = $base . '/parent'; $stylesheet = $template;
mkdir($template . '/inc/content-types', 0777, true);
function fixture($root, $name, $content) { file_put_contents($root . '/inc/content-types/' . $name . '.php', $content); }
function reset_runtime() { global $registered_post_types, $registered_taxonomies, $registration_order, $diagnostics, $filters; $registered_post_types = array(); $registered_taxonomies = array(); $registration_order = array(); $diagnostics = array(); $filters = array(); }
function cleanup_fixture_tree($base) { foreach (glob($base . '/*/inc/content-types/*.php') ?: array() as $file) unlink($file); foreach (glob($base . '/*/inc/content-types') ?: array() as $directory) rmdir($directory); foreach (glob($base . '/*/inc') ?: array() as $directory) rmdir($directory); foreach (glob($base . '/*') ?: array() as $directory) rmdir($directory); rmdir($base); }

fixture($template, '10-parent', "<?php return array('post_type'=>array('slug'=>'project','args'=>array('public'=>true)),'taxonomies'=>array('topic'=>array('object_types'=>array('project'),'args'=>array('show_ui'=>true))));");
fixture($template, '20-taxonomy', "<?php return array('taxonomies'=>array('audience'=>array('object_types'=>array('project'),'args'=>array())));");
file_put_contents($template . '/inc/content-types/index.php', "<?php return array('post_type'=>array('slug'=>'ignored','args'=>array()));");
fixture($template, '30-Case', "<?php return array('post_type'=>array('slug'=>'case_sort','args'=>array('label'=>'upper')));");
fixture($template, '30-case', "<?php return array('post_type'=>array('slug'=>'case_sort','args'=>array('label'=>'lower')));");
reset_runtime(); lonestar_register_content_types();
assert_true(isset($registered_post_types['project']) && !isset($registered_post_types['ignored']), 'parent-only and index exclusion failed');
assert_true($registration_order === array('post:project', 'post:case_sort', 'tax:topic', 'tax:audience'), 'post types must register before taxonomies');
assert_true($registered_post_types['case_sort']['label'] === 'upper', 'case-colliding filenames must resolve with binary deterministic first-valid-wins order');

$stylesheet = $base . '/child'; mkdir($stylesheet . '/inc/content-types', 0777, true);
fixture($stylesheet, 'child', "<?php return array('post_type'=>array('slug'=>'project','args'=>array('public'=>false)),'taxonomies'=>array('topic'=>array('object_types'=>array('project'),'args'=>array('hierarchical'=>true)), 'only_child'=>array('object_types'=>array('project'),'args'=>array())));");
reset_runtime(); lonestar_register_content_types();
assert_true($registered_post_types['project']['public'] === false && $registered_taxonomies['topic'][1]['hierarchical'], 'child entity override failed');
assert_true(isset($registered_taxonomies['only_child']), 'child-only definition failed');
fixture($stylesheet, 'duplicate', "<?php return array('post_type'=>array('slug'=>'project','args'=>array('label'=>'later')));");
reset_runtime(); lonestar_register_content_types(); assert_true(!isset($registered_post_types['project']['label']), 'first source duplicate must win');

fixture($stylesheet, 'bad', "<?php return 'bad';"); fixture($stylesheet, 'throwing', "<?php throw new Exception('fixture');");
fixture($stylesheet, 'invalid', "<?php return array('post_type'=>array('slug'=>'Bad Slug','args'=>'bad'),'taxonomies'=>array('bad slug'=>array('object_types'=>array(),'args'=>array())));");
fixture($stylesheet, 'malformed-types', "<?php return array('post_type'=>array('slug'=>new stdClass(),'args'=>array()),'taxonomies'=>array('object_type'=>array('object_types'=>array('project',new stdClass()),'args'=>array()),'array_type'=>array('object_types'=>array(array('project')),'args'=>array())));");
fixture($stylesheet, 'malformed-array-slug', "<?php return array('post_type'=>array('slug'=>array('project'),'args'=>array()));");
fixture($stylesheet, 'valid-after-bad', "<?php return array('taxonomies'=>array('valid_tax'=>array('object_types'=>array('project','project'),'args'=>array())));");
fixture($stylesheet, 'partial-object-types', "<?php return array('taxonomies'=>array('partial_tax'=>array('object_types'=>array('project','Bad Slug'),'args'=>array())));");
reset_runtime(); lonestar_register_content_types(); assert_true(isset($registered_taxonomies['valid_tax']), 'bad files must not block valid files');
assert_true($registered_taxonomies['valid_tax'][0] === array('project'), 'valid object types must deduplicate while preserving order');
assert_true(!isset($registered_post_types['object_type']) && !isset($registered_taxonomies['object_type']) && !isset($registered_taxonomies['array_type']), 'non-string slugs and object types must skip safely');
assert_true(!isset($registered_taxonomies['partial_tax']), 'partially invalid object types must reject the whole taxonomy');

$stylesheet = $template; assert_true(count(lonestar_get_content_type_definition_files()) === 1, 'no-child roots must not duplicate');
reset_runtime(); $registered_post_types['project'] = array('existing' => true); $registered_taxonomies['topic'] = array('existing' => true); lonestar_register_content_types();
assert_true(true === $registered_post_types['project']['existing'] && true === $registered_taxonomies['topic']['existing'], 'already registered entities must be skipped');
assert_true(isset($registered_post_types['case_sort']) && isset($registered_taxonomies['audience']), 'skipped existing entities must not block other registrations');
add_filter('lonestar_content_type_definitions', function ($definitions) { $definitions['post_types']['BAD SLUG'] = array(); $definitions['taxonomies']['filtered'] = array('object_types'=>array('Bad Slug'), 'args'=>array()); return $definitions; });
$definitions = lonestar_get_content_type_definitions(true); assert_true(!isset($definitions['post_types']['BAD SLUG']) && !isset($definitions['taxonomies']['filtered']), 'filter output revalidation failed');
$stylesheet = $base . '/child';
reset_runtime();
$catalog_filter_calls = 0;
add_filter('lonestar_content_type_definitions', function ($definitions) use (&$catalog_filter_calls) { ++$catalog_filter_calls; unset($definitions['post_types']['case_sort']); $definitions['post_types']['project']['show_in_rest'] = true; $definitions['post_types']['filter_type'] = array('public' => true); return $definitions; });
$catalog = lonestar_get_content_type_catalog(true);
assert_true(isset($catalog['post_types']['entries']['template:project']) && isset($catalog['post_types']['entries']['stylesheet:project']), 'catalog must retain parent and child entries');
assert_true($catalog['post_types']['entries']['template:project']['overridden'] && 'stylesheet' === $catalog['post_types']['entries']['template:project']['overriding_source'], 'catalog child override metadata failed');
assert_true($catalog['post_types']['entries']['stylesheet:project']['filtered'] && !isset($catalog['post_types']['entries']['stylesheet:project']['declared_args']['show_in_rest']) && true === $catalog['post_types']['entries']['stylesheet:project']['effective_args']['show_in_rest'], 'catalog must preserve declared and effective filter values');
assert_true($catalog['post_types']['entries']['template:case_sort']['filtered_out'] && !$catalog['post_types']['entries']['template:case_sort']['effective'], 'catalog filter-removed entry failed');
assert_true(isset($catalog['post_types']['entries']['filter:filter_type']) && '' === $catalog['post_types']['entries']['filter:filter_type']['file'], 'catalog filter-only entry failed');
assert_true(isset($catalog['post_types']['effective']['project']) && true === $catalog['post_types']['effective']['project']['show_in_rest'] && isset($catalog['post_types']['effective']['filter_type']) && !isset($catalog['post_types']['effective']['case_sort']), 'catalog effective map must equal filtered resolution');
assert_true(!empty($catalog['diagnostics']), 'catalog must expose request-local diagnostics');
$cached_catalog = lonestar_get_content_type_catalog(); assert_true(1 === $catalog_filter_calls && $cached_catalog['post_types']['effective'] === $catalog['post_types']['effective'], 'catalog must be built once and shared within the request');
$definitions = lonestar_get_content_type_definitions(); assert_true(isset($definitions['post_types']['filter_type']) && !isset($definitions['post_types']['case_sort']), 'compatibility definitions API must wrap catalog effective maps');

reset_runtime();
add_filter('lonestar_content_type_definitions', function ($definitions) { unset($definitions['post_types']['project']); return $definitions; });
add_filter('lonestar_content_type_definitions', function ($definitions) { $definitions['post_types']['project'] = array('public' => true, 'label' => 'Re-added'); return $definitions; });
$readded_catalog = lonestar_get_content_type_catalog(true);
assert_true(isset($readded_catalog['post_types']['effective']['project']) && 'Re-added' === $readded_catalog['post_types']['effective']['project']['label'], 'final filter output must retain a removed-then-readded file slug');

reset_runtime();
add_filter('lonestar_content_type_definitions', function ($definitions) { throw new Exception('filter fixture'); });
$filter_threw = false;
try { lonestar_get_content_type_catalog(true); } catch (Throwable $throwable) { $filter_threw = true; }
assert_true($filter_threw && !isset($GLOBALS['lonestar_content_type_catalog_diagnostics']), 'catalog must restore the diagnostic collector after a throwing filter');
reset_runtime(); lonestar_get_content_type_catalog(true);

$admin = false; $can_manage = true; $options = array(); $option_updates = array(); $flushes = 0; lonestar_maybe_refresh_content_type_rewrites(); assert_true(0 === $flushes && empty($options), 'frontend must not write options');
$empty_base = sys_get_temp_dir() . '/lonestar-content-types-empty-' . uniqid(); $empty_template = $empty_base . '/parent'; mkdir($empty_template . '/inc/content-types', 0777, true); $template = $empty_template; $stylesheet = $template;
$admin = true; $can_manage = true; $options = array(); $option_updates = array(); $flushes = 0; lonestar_maybe_refresh_content_type_rewrites(); assert_true(0 === $flushes && empty($options) && empty($option_updates), 'empty definition directory must not initialize rewrites or options');
$template = $base . '/parent'; $stylesheet = $template; $can_manage = false; $options = array(); $option_updates = array(); $flushes = 0; lonestar_maybe_refresh_content_type_rewrites(); assert_true(0 === $flushes && empty($options) && empty($option_updates), 'admins without manage_options must not flush or write options');
$can_manage = true; lonestar_maybe_refresh_content_type_rewrites(); assert_true(1 === $flushes && isset($options[LONESTAR_CONTENT_TYPES_SIGNATURE_OPTION]), 'admin signature initialization failed');
assert_true(false === $option_updates[0][2], 'content type signature option must disable autoload');
lonestar_maybe_refresh_content_type_rewrites(); assert_true(1 === $flushes, 'unchanged signature must not flush twice');
foreach (glob($template . '/inc/content-types/*.php') as $file) unlink($file); lonestar_maybe_refresh_content_type_rewrites(); assert_true(2 === $flushes, 'removed definitions must refresh rewrites');
cleanup_fixture_tree($base); cleanup_fixture_tree($empty_base);
echo "content-types runtime tests passed\n";
