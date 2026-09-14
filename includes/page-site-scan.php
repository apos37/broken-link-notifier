<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$HELPERS = new BLNOTIFIER_HELPERS;
$LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;

// Discovery status (shared with Link Browser)
$last_discovery = get_option( 'blnotifier_link_browser_last_scan', [] );
if ( !empty( $last_discovery[ 'time' ] ) ) {
    $discovery_user = get_userdata( absint( $last_discovery[ 'user_id' ] ) );
    $discovery_user_name = $discovery_user ? $discovery_user->display_name : __( 'Unknown', 'broken-link-notifier' );
    $discovery_text = sprintf(
        // translators: %1$s is the date, %2$s is the user's display name
        __( 'Links last discovered on %1$s by %2$s', 'broken-link-notifier' ),
        $HELPERS->convert_timezone( $last_discovery[ 'time' ] ),
        $discovery_user_name
    );
    $discovery_button_label = 'Rescan for New Links';
} else {
    $discovery_text = __( 'Links have not been discovered yet.', 'broken-link-notifier' );
    $discovery_button_label = 'Discover Links';
}

// Last check status
$last_check = get_option( 'blnotifier_site_scan_last_check', [] );
if ( !empty( $last_check[ 'time' ] ) ) {
    $check_user = get_userdata( absint( $last_check[ 'user_id' ] ) );
    $check_user_name = $check_user ? $check_user->display_name : __( 'Unknown', 'broken-link-notifier' );
    $last_check_text = sprintf(
        // translators: %1$s is the date, %2$s is the user's display name
        __( 'Links last checked on %1$s by %2$s', 'broken-link-notifier' ),
        $HELPERS->convert_timezone( $last_check[ 'time' ] ),
        $check_user_name
    );
    $has_check_run = true;
} else {
    $last_check_text = __( 'Links have not been checked yet.', 'broken-link-notifier' );
    $has_check_run = false;
}

// Current (live) counts of results that came from Site Scan — not a frozen snapshot from the last run
global $wpdb;
$results_table = $wpdb->prefix . 'blnotifier_results';
$current_broken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $results_table WHERE method = %s AND type = %s", 'site-scan', 'broken' ) ); // phpcs:ignore
$current_warning = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $results_table WHERE method = %s AND type = %s", 'site-scan', 'warning' ) ); // phpcs:ignore

$check_counts = [
    'checked' => isset( $last_check[ 'checked' ] ) ? absint( $last_check[ 'checked' ] ) : 0,
    'broken'  => $current_broken,
    'warning' => $current_warning,
];

$has_links = $LINK_BROWSER->table_exists();

// Step 1 discovery summary
$discovery_counts = $LINK_BROWSER->get_counts();
$has_discovery_run = !empty( $last_discovery[ 'time' ] );
?>

<p class="blnotifier-desc">Site Scan works in two steps. First it discovers every link on your site (reusing the same data as Link Browser, so if you've already run that, you don't need to start over). Then it checks each one for broken links and warnings, adding any it finds to your <a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ); ?>">Results</a> page.</p>

<div class="blnotifier-box">
    <div class="blnotifier-box-header">
        <h2>Step 1: Discover Links</h2>
    </div>
    <div class="blnotifier-box-body">
        <p id="bln-discovery-status"><?php echo esc_html( $discovery_text ); ?></p>
        <div class="bln-scan-action-row">
            <button type="button" id="bln-discover-links" class="blnotifier-button"><?php echo esc_html( $discovery_button_label ); ?></button>
            <span class="bln-spinner" id="bln-discover-spinner" style="display:none;"></span>
            <span id="bln-discover-progress" style="display:none;">
                <em>Scanning <span id="bln-discover-done">0</span>/<span id="bln-discover-total">0</span> pages...</em>
            </span>
        </div>

        <div id="bln-discover-summary" style="<?php echo $has_discovery_run ? '' : 'display:none;'; ?>">
            <div class="bln-site-scan-summary">
                <div class="bln-summary-item">
                    <span class="label">Links Found</span>
                    <span class="value" id="bln-discover-summary-total"><?php echo absint( $discovery_counts[ 'total' ] ); ?></span>
                </div>
                <div class="bln-summary-item">
                    <span class="label">Internal Links</span>
                    <span class="value" id="bln-discover-summary-internal"><?php echo absint( $discovery_counts[ 'internal' ] ); ?></span>
                </div>
                <div class="bln-summary-item">
                    <span class="label">External Links</span>
                    <span class="value" id="bln-discover-summary-external"><?php echo absint( $discovery_counts[ 'external' ] ); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'link-browser' ) ); ?>" class="blnotifier-button">View Links</a>
        </div>
    </div>
</div>

<div class="blnotifier-box">
    <div class="blnotifier-box-header">
        <h2>Step 2: Check for Broken Links</h2>
    </div>
    <div class="blnotifier-box-body">
        <p id="bln-check-status"><?php echo esc_html( $last_check_text ); ?></p>
        <div class="bln-scan-action-row">
            <button type="button" id="bln-check-links" class="blnotifier-button" <?php echo !$has_links ? 'disabled' : ''; ?>>Check for Broken Links</button>
            <span class="bln-spinner" id="bln-check-spinner" style="display:none;"></span>
            <span id="bln-check-progress-inline" style="display:none;">
                <em><span id="bln-check-done">0</span>/<span id="bln-check-total">0</span> links checked</em>
            </span>
        </div>

        <div id="bln-check-progress-wrap" style="display:none;">
            <div class="bln-progress-bar">
                <div class="bln-progress-bar-fill" id="bln-progress-bar-fill" style="width:0%;"></div>
            </div>
        </div>

        <div id="bln-check-summary" style="<?php echo $has_check_run ? '' : 'display:none;'; ?>">
            <div class="bln-site-scan-summary">
                <div class="bln-summary-item">
                    <span class="label">Links Checked</span>
                    <span class="value" id="bln-summary-checked"><?php echo absint( $check_counts[ 'checked' ] ); ?></span>
                </div>
                <div class="bln-summary-item broken">
                    <span class="label">Broken Found</span>
                    <span class="value" id="bln-summary-broken"><?php echo absint( $check_counts[ 'broken' ] ); ?></span>
                </div>
                <div class="bln-summary-item warning">
                    <span class="label">Warnings Found</span>
                    <span class="value" id="bln-summary-warning"><?php echo absint( $check_counts[ 'warning' ] ); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ); ?>" class="blnotifier-button">View Results</a>
        </div>
    </div>
</div>

<div class="blnotifier-box">
    <div class="blnotifier-box-header">
        <h2>Prefer an External Crawl?</h2>
    </div>
    <div class="blnotifier-box-body">
        <p>Site Scan covers everything linked from your own pages and menus. If you want a more exhaustive crawl (including pages not linked from anywhere on your site), here are a few off-site tools worth considering:</p>
        <ul>
            <?php
            foreach ( $HELPERS->get_suggested_offsite_checkers() as $name => $url ) {
                ?>
                <li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $name ); ?></a></li>
                <?php
            }
            ?>
        </ul>
    </div>
</div>