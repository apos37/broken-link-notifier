<?php
/**
 * Uninstall script for Broken Link Notifier
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Run uninstall cleanup
 *
 * @return void
 */
function blnotifier_run_uninstall() {
    $uninstall_cleanup = get_option( 'blnotifier_uninstall_cleanup', false );
    if ( ! $uninstall_cleanup ) {
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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- $table_name is built from a hardcoded prefix + a fixed list of table names, not user input; uninstall-time schema teardown is an intended direct query.
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        // phpcs:enable
    } // End foreach()

    // Remove options
    $options = [
        'has_updated_settings',
        'pause_frontend_scanning',
        'remote_fetch_links',
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
        'scan_delay_ms',
        'site_scan_last_check',
        'settings_backup_tools',
        'whats_new_seen',
        'test_mode',
    ];

    foreach ( $options as $option ) {
        delete_option( 'blnotifier_' . $option );
    } // End foreach()

    // Remove user meta (per-user dismissal flags)
    delete_metadata( 'user', 0, 'blnotifier_verify_notice_dismissed', '', true );
} // End blnotifier_run_uninstall()

blnotifier_run_uninstall();