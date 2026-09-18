<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$blnotifier_table_name = $wpdb->prefix . 'blnotifier_results';

$BLNOTIFIER_RESULTS = new BLNOTIFIER_RESULTS;
$BLNOTIFIER_HELPERS = new BLNOTIFIER_HELPERS;

$blnotifier_per_page = $BLNOTIFIER_RESULTS->sanitize_per_page( get_option( 'blnotifier_per_page', 25 ) );
$blnotifier_counts = $BLNOTIFIER_RESULTS->get_counts();
$blnotifier_warnings_enabled = filter_var( get_option( 'blnotifier_enable_warnings' ), FILTER_VALIDATE_BOOLEAN );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name is a hardcoded prefix + fixed name, not user input; $per_page is bound via prepare().
$blnotifier_initial_rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $blnotifier_table_name ORDER BY created_at ASC LIMIT %d OFFSET 0",
    $blnotifier_per_page
) );
// phpcs:enable
?>

<p class="blnotifier-desc"><?php echo esc_html__( 'This page shows the results of your scans. Click "Verify Link Statuses" above to recheck whatever links are currently visible on the page — it does not remove the links from your pages or rescan them for new ones. After fixing a broken link, you will need to clear the result below. Then when you rescan the page it should not show up here again. Note that the plugin will still find broken links if you simply hide them on the page.', 'broken-link-notifier' ); ?></p>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">

        <?php $BLNOTIFIER_RESULTS->render_status_counts( $blnotifier_counts, 'all', $blnotifier_warnings_enabled ); ?>

        <?php $BLNOTIFIER_RESULTS->render_tablenav( 'top', $blnotifier_per_page ); ?>

        <table class="results wp-list-table widefat fixed striped table-view-list" id="bln-results-table">
            <thead>
                <tr>
                    <th id="cb" class="manage-column column-cb check-column">
                        <input class="bln-select-all" id="cb-select-all-1" type="checkbox">
                        <label for="cb-select-all-1"><span class="screen-reader-text"><?php echo esc_html__( 'Select All', 'broken-link-notifier' ); ?></span></label>
                    </th>
                    <th class="type"><?php echo esc_html__( 'Type', 'broken-link-notifier' ); ?></th>
                    <th class="link"><?php echo esc_html__( 'Link', 'broken-link-notifier' ); ?></th>
                    <th class="source"><?php echo esc_html__( 'Source', 'broken-link-notifier' ); ?></th>
                    <th class="source_pt"><?php echo esc_html__( 'Source Post Type', 'broken-link-notifier' ); ?></th>
                    <th class="method"><?php echo esc_html__( 'Method', 'broken-link-notifier' ); ?></th>
                    <th class="date"><?php echo esc_html__( 'Date', 'broken-link-notifier' ); ?></th>
                    <th class="verify"><?php echo esc_html__( 'Verify', 'broken-link-notifier' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $BLNOTIFIER_RESULTS->render_rows( $blnotifier_initial_rows ); ?>
            </tbody>
        </table>

        <?php $BLNOTIFIER_RESULTS->render_tablenav( 'bottom', $blnotifier_per_page ); ?>

    </div>
</div>