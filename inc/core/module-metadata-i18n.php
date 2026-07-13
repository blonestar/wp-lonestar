<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extractable source strings for the parent module.json metadata catalog.
 *
 * Runtime metadata translation deliberately uses translate() because module
 * JSON is declarative. Keep each parent-owned metadata string here so POT
 * extraction has literal gettext calls; child metadata owns its own domain.
 *
 * @return array<string,string>
 */
function lonestar_get_parent_module_metadata_i18n_catalog()
{
    return array(
        'ACF Number with Unit' => __('ACF Number with Unit', 'lonestar'),
        'Adds a reusable ACF number field with an associated CSS unit.' => __('Adds a reusable ACF number field with an associated CSS unit.', 'lonestar'),
        'ACF Menu Selector' => __('ACF Menu Selector', 'lonestar'),
        'Adds an ACF field for selecting a registered WordPress menu.' => __('Adds an ACF field for selecting a registered WordPress menu.', 'lonestar'),
        'ACF Gravity Forms Selector' => __('ACF Gravity Forms Selector', 'lonestar'),
        'Adds an ACF field for selecting an active Gravity Forms form.' => __('Adds an ACF field for selecting an active Gravity Forms form.', 'lonestar'),
        'GTM' => __('GTM', 'lonestar'),
        'Outputs Google Tag Manager snippets with native Theme Settings configuration.' => __('Outputs Google Tag Manager snippets with native Theme Settings configuration.', 'lonestar'),
        'GTM Settings' => __('GTM Settings', 'lonestar'),
    );
}
