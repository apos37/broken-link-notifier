<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Initiate
$HELPERS = new BLNOTIFIER_HELPERS;
$LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
$counts = $LINK_BROWSER->get_counts();
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">

        <?php $LINK_BROWSER->render_status_counts( $counts, 'all' ); ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="bln-link-browser-kind" class="screen-reader-text"><?php echo esc_html__( 'Kind', 'broken-link-notifier' ); ?></label>
                <select id="bln-link-browser-kind">
                    <option value="all"><?php echo esc_html__( 'All Kinds', 'broken-link-notifier' ); ?></option>
                    <option value="link"><?php echo esc_html__( 'Links Only', 'broken-link-notifier' ); ?></option>
                    <option value="image"><?php echo esc_html__( 'Images Only', 'broken-link-notifier' ); ?></option>
                    <option value="file"><?php echo esc_html__( 'Files Only', 'broken-link-notifier' ); ?></option>
                </select>
            </div>
            <div class="alignleft actions">
                <input type="search" id="bln-link-browser-search" placeholder="<?php echo esc_attr__( 'Search links...', 'broken-link-notifier' ); ?>">
            </div>
            <div class="alignleft actions">
                <label for="bln-link-browser-per-page" class="screen-reader-text"><?php echo esc_html__( 'Per page', 'broken-link-notifier' ); ?></label>
                <select id="bln-link-browser-per-page">
                    <option value="10"><?php echo esc_html__( '10 per page', 'broken-link-notifier' ); ?></option>
                    <option value="25" selected><?php echo esc_html__( '25 per page', 'broken-link-notifier' ); ?></option>
                    <option value="50"><?php echo esc_html__( '50 per page', 'broken-link-notifier' ); ?></option>
                    <option value="100"><?php echo esc_html__( '100 per page', 'broken-link-notifier' ); ?></option>
                </select>
            </div>
            <div class="tablenav-pages bln-link-browser-pagination"></div>
            <br class="clear">
        </div>

        <table class="link-browser wp-list-table widefat fixed striped table-view-list" id="bln-link-browser-table">
            <thead>
                <tr>
                    <th class="link"><?php echo esc_html__( 'Link', 'broken-link-notifier' ); ?></th>
                    <th class="page-count" style="width: 90px;"><?php echo esc_html__( '# of Pages', 'broken-link-notifier' ); ?></th>
                    <th class="pages"><?php echo esc_html__( 'Pages Found On', 'broken-link-notifier' ); ?></th>
                    <th class="type" style="width: 120px;"><?php echo esc_html__( 'Type', 'broken-link-notifier' ); ?></th>
                    <th class="actions" style="width: 220px;"><?php echo esc_html__( 'Actions', 'broken-link-notifier' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="5"><em><?php echo esc_html__( 'Run a scan or adjust your filters to see results.', 'broken-link-notifier' ); ?></em></td></tr>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages bln-link-browser-pagination"></div>
            <br class="clear">
        </div>

    </div>
</div>