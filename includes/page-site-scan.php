<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$BLNOTIFIER_HELPERS = new BLNOTIFIER_HELPERS;
$BLNOTIFIER_LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;

// Discovery status (shared with Link Browser)
$blnotifier_last_discovery = get_option( 'BLNOTIFIER_LINK_BROWSER_last_scan', [] );
if ( !empty( $blnotifier_last_discovery[ 'time' ] ) ) {
    $blnotifier_discovery_user = get_userdata( absint( $blnotifier_last_discovery[ 'user_id' ] ) );
    $blnotifier_discovery_user_name = $blnotifier_discovery_user ? $blnotifier_discovery_user->display_name : __( 'Unknown', 'broken-link-notifier' );
    $blnotifier_discovery_text = sprintf(
        // translators: %1$s is the date, %2$s is the user's display name
        __( 'Links last discovered on %1$s by %2$s', 'broken-link-notifier' ),
        $BLNOTIFIER_HELPERS->convert_timezone( $blnotifier_last_discovery[ 'time' ] ),
        $blnotifier_discovery_user_name
    );
    $blnotifier_discovery_button_label = 'Rescan for New Links';
} else {
    $blnotifier_discovery_text = __( 'Links have not been discovered yet.', 'broken-link-notifier' );
    $blnotifier_discovery_button_label = 'Discover Links';
}

// Last check status
$blnotifier_last_check = get_option( 'blnotifier_site_scan_last_check', [] );
if ( !empty( $blnotifier_last_check[ 'time' ] ) ) {
    $blnotifier_check_user = get_userdata( absint( $blnotifier_last_check[ 'user_id' ] ) );
    $blnotifier_check_user_name = $blnotifier_check_user ? $blnotifier_check_user->display_name : __( 'Unknown', 'broken-link-notifier' );
    $blnotifier_last_check_text = sprintf(
        // translators: %1$s is the date, %2$s is the user's display name
        __( 'Links last checked on %1$s by %2$s', 'broken-link-notifier' ),
        $BLNOTIFIER_HELPERS->convert_timezone( $blnotifier_last_check[ 'time' ] ),
        $blnotifier_check_user_name
    );
    $blnotifier_has_check_run = true;
} else {
    $blnotifier_last_check_text = __( 'Links have not been checked yet.', 'broken-link-notifier' );
    $blnotifier_has_check_run = false;
}

// Current (live) counts of results that came from Site Scan — not a frozen snapshot from the last run
global $wpdb;
$blnotifier_results_table = $wpdb->prefix . 'blnotifier_results';
$blnotifier_current_broken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $blnotifier_results_table WHERE method = %s AND type = %s", 'site-scan', 'broken' ) ); // phpcs:ignore
$blnotifier_current_warning = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $blnotifier_results_table WHERE method = %s AND type = %s", 'site-scan', 'warning' ) ); // phpcs:ignore

$blnotifier_check_counts = [
    'checked' => isset( $blnotifier_last_check[ 'checked' ] ) ? absint( $blnotifier_last_check[ 'checked' ] ) : 0,
    'broken'  => $blnotifier_current_broken,
    'warning' => $blnotifier_current_warning,
];

$blnotifier_has_links = $BLNOTIFIER_LINK_BROWSER->table_exists();

// Step 1 discovery summary
$blnotifier_discovery_counts = $BLNOTIFIER_LINK_BROWSER->get_counts();
$blnotifier_has_discovery_run = !empty( $blnotifier_last_discovery[ 'time' ] );
?>

<p class="blnotifier-desc"><?php
printf(
    /* translators: %s is a link to the Results page. */
    esc_html__( 'Site Scan works in two steps. First it discovers every link on your site (reusing the same data as Link Browser, so if you\'ve already run that, you don\'t need to start over). Then it checks each one for broken links and warnings, adding any it finds to your %s page.', 'broken-link-notifier' ),
    '<a href="' . esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ) . '">' . esc_html__( 'Results', 'broken-link-notifier' ) . '</a>'
);
?></p>

<div class="blnotifier-box">
    <div class="blnotifier-box-header">
        <h2><?php esc_html_e( 'Step 1: Discover Links', 'broken-link-notifier' ); ?></h2>
    </div>
    <div class="blnotifier-box-body">
        <p id="bln-discovery-status"><?php echo esc_html( $blnotifier_discovery_text ); ?></p>
        <div class="bln-scan-action-row">
            <button type="button" id="bln-discover-links" class="blnotifier-button"><?php echo esc_html( $blnotifier_discovery_button_label ); ?></button>
            <span class="bln-spinner" id="bln-discover-spinner" style="display:none;"></span>
            <span id="bln-discover-progress" style="display:none;">
                <em><?php esc_html_e( 'Scanning', 'broken-link-notifier' ); ?> <span id="bln-discover-done">0</span>/<span id="bln-discover-total">0</span> <?php esc_html_e( 'pages...', 'broken-link-notifier' ); ?></em>
            </span>
        </div>

        <div id="bln-discover-summary" style="<?php echo $blnotifier_has_discovery_run ? '' : 'display:none;'; ?>">
            <div class="bln-site-scan-summary">
                <div class="bln-summary-item">
                    <span class="label"><?php esc_html_e( 'Links Found', 'broken-link-notifier' ); ?></span>
                    <span class="value" id="bln-discover-summary-total"><?php echo absint( $blnotifier_discovery_counts[ 'total' ] ); ?></span>
                </div>
                <div class="bln-summary-item">
                    <span class="label"><?php esc_html_e( 'Internal Links', 'broken-link-notifier' ); ?></span>
                    <span class="value" id="bln-discover-summary-internal"><?php echo absint( $blnotifier_discovery_counts[ 'internal' ] ); ?></span>
                </div>
                <div class="bln-summary-item">
                    <span class="label"><?php esc_html_e( 'External Links', 'broken-link-notifier' ); ?></span>
                    <span class="value" id="bln-discover-summary-external"><?php echo absint( $blnotifier_discovery_counts[ 'external' ] ); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'link-browser' ) ); ?>" class="blnotifier-button"><?php esc_html_e( 'View Links', 'broken-link-notifier' ); ?></a>
        </div>
    </div>
</div>

<div class="blnotifier-box">
    <div class="blnotifier-box-header">
        <h2><?php esc_html_e( 'Step 2: Check for Broken Links', 'broken-link-notifier' ); ?></h2>
    </div>
    <div class="blnotifier-box-body">
        <p id="bln-check-status"><?php echo esc_html( $blnotifier_last_check_text ); ?></p>
        <div class="bln-scan-action-row">
            <button type="button" id="bln-check-links" class="blnotifier-button" <?php echo !$blnotifier_has_links ? 'disabled' : ''; ?>><?php esc_html_e( 'Check for Broken Links', 'broken-link-notifier' ); ?></button>
            <span class="bln-spinner" id="bln-check-spinner" style="display:none;"></span>
            <span id="bln-check-progress-inline" style="display:none;">
                <em><span id="bln-check-done">0</span>/<span id="bln-check-total">0</span> <?php esc_html_e( 'links checked', 'broken-link-notifier' ); ?></em>
            </span>
        </div>

        <div id="bln-check-progress-wrap" style="display:none;">
            <div class="bln-progress-bar">
                <div class="bln-progress-bar-fill" id="bln-progress-bar-fill" style="width:0%;"></div>
            </div>
        </div>

        <div id="bln-check-summary" style="<?php echo $blnotifier_has_check_run ? '' : 'display:none;'; ?>">
            <div class="bln-site-scan-summary">
                <div class="bln-summary-item">
                    <span class="label"><?php esc_html_e( 'Links Checked', 'broken-link-notifier' ); ?></span>
                    <span class="value" id="bln-summary-checked"><?php echo absint( $blnotifier_check_counts[ 'checked' ] ); ?></span>
                </div>
                <div class="bln-summary-item broken">
                    <span class="label"><?php esc_html_e( 'Broken Found', 'broken-link-notifier' ); ?></span>
                    <span class="value" id="bln-summary-broken"><?php echo absint( $blnotifier_check_counts[ 'broken' ] ); ?></span>
                </div>
                <div class="bln-summary-item warning">
                    <span class="label"><?php esc_html_e( 'Warnings Found', 'broken-link-notifier' ); ?></span>
                    <span class="value" id="bln-summary-warning"><?php echo absint( $blnotifier_check_counts[ 'warning' ] ); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ); ?>" class="blnotifier-button"><?php esc_html_e( 'View Results', 'broken-link-notifier' ); ?></a>
        </div>
    </div>
</div>

<div class="blnotifier-box">
    <div class="blnotifier-box-header">
        <h2><?php esc_html_e( 'Prefer an External Crawl?', 'broken-link-notifier' ); ?></h2>
    </div>
    <div class="blnotifier-box-body">
        <p><?php esc_html_e( 'Site Scan covers everything linked from your own pages and menus. If you want a more exhaustive crawl (including pages not linked from anywhere on your site), here are a few off-site tools worth considering:', 'broken-link-notifier' ); ?></p>
        <ul>
            <?php
            foreach ( $BLNOTIFIER_HELPERS->get_suggested_offsite_checkers() as $blnotifier_name => $blnotifier_url ) {
                ?>
                <li><a href="<?php echo esc_url( $blnotifier_url ); ?>" target="_blank"><?php echo esc_html( $blnotifier_name ); ?></a></li>
                <?php
            }
            ?>
        </ul>
    </div>
</div>