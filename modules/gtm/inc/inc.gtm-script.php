<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize and validate GTM container id.
 *
 * @param mixed $raw_id Raw container id.
 * @return string
 */
function lonestar_module_normalize_gtm_container_id($raw_id)
{
    $id = strtoupper(trim((string) $raw_id));
    $id = preg_replace('/\s+/', '', $id);

    if ('' === $id) {
        return '';
    }

    if (0 !== strpos($id, 'GTM-')) {
        $id = 'GTM-' . $id;
    }

    if (!preg_match('/^GTM-[A-Z0-9]+$/', $id)) {
        return '';
    }

    return $id;
}

/**
 * Build GTM script markup for <head>.
 *
 * @param string $container_id GTM container id.
 * @return string
 */
function lonestar_module_get_gtm_head_markup($container_id)
{
    $container_id = lonestar_module_normalize_gtm_container_id($container_id);
    if ('' === $container_id) {
        return '';
    }

    $id_for_js = esc_js($container_id);
    return sprintf(
        "<!-- Google Tag Manager -->\n" .
        "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n" .
        "<!-- End Google Tag Manager -->\n",
        $id_for_js
    );
}

/**
 * Build GTM noscript markup for <body>.
 *
 * @param string $container_id GTM container id.
 * @return string
 */
function lonestar_module_get_gtm_body_markup($container_id)
{
    $container_id = lonestar_module_normalize_gtm_container_id($container_id);
    if ('' === $container_id) {
        return '';
    }

    $iframe_src = sprintf(
        'https://www.googletagmanager.com/ns.html?id=%s',
        rawurlencode($container_id)
    );

    return sprintf(
        "<!-- Google Tag Manager (noscript) -->\n" .
        "<noscript><iframe src=\"%s\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n" .
        "<!-- End Google Tag Manager (noscript) -->\n",
        esc_url($iframe_src)
    );
}
