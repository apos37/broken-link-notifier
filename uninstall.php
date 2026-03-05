<?php
/**
 * Uninstall script for Developer Debug Tools
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$blnotifier_uninstall_clearnup = get_option( 'blnotifier_uninstall_cleanup', false );
if ( ! $blnotifier_uninstall_clearnup ) {
    return;
}

global $wpdb;

// Remove tables
$tables = [
    'blnotifier_results',
    'blnotifier_cache',
];

foreach ( $tables as $table ) {
    $table_name = $wpdb->prefix . $table;
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}

// Remove options
$options = [
    'has_updated_settings',
    'pause_frontend_scanning',
    'pause_results_verification',
    'enable_emailing',
    'emails',
    'enable_discord',
    'discord',
    'enable_msteams',
    'msteams',
    'user_agent',
    'timeout',
    'max_redirects',
    'max_links_per_page',
    'allow_redirects',
    'documents_use_head',
    'enable_warnings',
    'enable_good_links',
    'enable_delete_source',
    'include_images',
    'ssl_verify',
    'scan_header',
    'scan_footer',
    'show_in_console',
    'cache',
    'editable_roles',
    'post_types',
    'status_codes',
    'uninstall_cleanup',
    'mark_code_zero_broken',
    'per_page'
];

foreach ( $options as $option ) {
    delete_option( 'blnotifier_' . $option );
}