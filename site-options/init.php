<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function ($hook) {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    if ($page !== 'site-settings') {
        return;
    }

    $base_url  = get_stylesheet_directory_uri() . '/site-options';
    $base_path = get_stylesheet_directory() . '/site-options';

    wp_enqueue_style(
        'ed-site-options-admin',
        $base_url . '/site-options.css',
        [],
        file_exists($base_path . '/site-options.css') ? filemtime($base_path . '/site-options.css') : '1.0.0'
    );

    wp_enqueue_script(
        'ed-site-options-admin',
        $base_url . '/site-options.js',
        ['jquery'],
        file_exists($base_path . '/site-options.js') ? filemtime($base_path . '/site-options.js') : '1.0.0',
        true
    );

    wp_localize_script('ed-site-options-admin', 'ED_SITE_OPTIONS', [
        'page'   => $page,
        'hook'   => $hook,
        'screen' => function_exists('get_current_screen') && get_current_screen() ? get_current_screen()->id : '',
    ]);
});