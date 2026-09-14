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
    'blnotifier_links',
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
    'enable_slack',
    'slack',
    'enable_msteams',
    'msteams',
    'enable_rest_api',
    'api_key',
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
    'per_page',
    'link_browser_last_scan',
    'verify_link_on_page',
    'scan_delay_ms',
    'site_scan_last_check',
    'whats_new_seen',
    'test_mode',
];

foreach ( $options as $option ) {
    delete_option( 'blnotifier_' . $option );
}

// Remove user meta (per-user dismissal flags)
delete_metadata( 'user', 0, 'blnotifier_verify_notice_dismissed', '', true );