<?php
/**
 * Site Scan class file.
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function() {
    (new BLNOTIFIER_SITE_SCAN)->init();
} );

class BLNOTIFIER_SITE_SCAN {

    /**
     * Nonce used for all Site Scan ajax calls
     *
     * @var string
     */
    private $nonce = 'blnotifier_site_scan';


    /**
     * Load on init
     */
    public function init() {

        add_action( 'blnotifier_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        add_action( 'wp_ajax_blnotifier_site_scan_store_menus', [ $this, 'ajax_store_menus' ] );
        add_action( 'wp_ajax_blnotifier_site_scan_get_link_ids', [ $this, 'ajax_get_link_ids' ] );
        add_action( 'wp_ajax_blnotifier_site_scan_check_link', [ $this, 'ajax_check_link' ] );
        add_action( 'wp_ajax_blnotifier_site_scan_finish', [ $this, 'ajax_finish' ] );

    } // End init()


    /**
     * Does the current user have access to this feature?
     *
     * @return boolean
     */
    public function has_access() {
        return (new BLNOTIFIER_HELPERS)->user_can_manage_broken_links();
    } // End has_access()


    /**
     * Render the "stay on this page" note in the left subheader
     *
     * @param string $active_tab
     * @return void
     */
    public function render_subheader_left( $active_tab ) {
        if ( $active_tab !== 'site-scan' ) {
            return;
        }
        ?>
        <span id="bln-site-scan-stay-note" class="blnotifier-scan-reminder" style="display:none;"><?php echo esc_html__( 'Please stay on this page while the scan runs.', 'broken-link-notifier' ); ?></span>
        <?php
    } // End render_subheader_left()


    /**
     * Enqueue script
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();

        if ( $screen !== $options_page || $tab !== 'site-scan' ) {
            return;
        }

        wp_enqueue_style( 'blnotifier-site-scan', BLNOTIFIER_PLUGIN_CSS_PATH.'site-scan.css', [], BLNOTIFIER_SCRIPT_VERSION );

        $handle = 'blnotifier_site_scan_script';
        wp_enqueue_script( 'jquery' );
        wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'site-scan.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
        wp_localize_script( $handle, 'blnotifier_site_scan', [
            'nonce'                => wp_create_nonce( $this->nonce ),
            'link_browser_nonce'   => wp_create_nonce( 'blnotifier_link_browser' ),
            'results_url'          => (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ),
            'ajaxurl'              => admin_url( 'admin-ajax.php' ),
            'scan_delay_ms'        => absint( get_option( 'blnotifier_scan_delay_ms', 0 ) ),
            'text'                 => [
                'stay_note'                 => __( 'A scan is still running. Are you sure you want to leave?', 'broken-link-notifier' ),
                'scan_completed'            => __( 'Scan complete!', 'broken-link-notifier' ),
                'rescan'                    => __( 'Rescan for New Links', 'broken-link-notifier' ),
                'discovering'               => __( 'Discovering...', 'broken-link-notifier' ),
                'discover_links'            => __( 'Discover Links', 'broken-link-notifier' ),
                'could_not_start_discovery' => __( 'Could not start discovery.', 'broken-link-notifier' ),
                'could_not_start_check'     => __( 'Could not start the check.', 'broken-link-notifier' ),
                'check_for_broken_links'    => __( 'Check for Broken Links', 'broken-link-notifier' ),
                'checking'                  => __( 'Checking...', 'broken-link-notifier' ),
                'no_links_found'            => __( 'No links found. Run Step 1 first.', 'broken-link-notifier' )
            ]
        ] );
        wp_enqueue_script( $handle );
    } // End enqueue_scripts()


    /**
     * Ajax: store header/footer nav menu links into the shared links table
     *
     * @return void
     */
    public function ajax_store_menus() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;

        $menu_links = $HELPERS->get_header_footer_links();
        $count = 0;

        foreach ( $menu_links as $menu_link ) {
            $source = 'menu:'.absint( $menu_link[ 'menu_id' ] );
            if ( $LINK_BROWSER->store_link( $menu_link[ 'link' ], $source ) ) {
                $count++;
            }
        }

        wp_send_json_success( [ 'count' => $count ] );
    } // End ajax_store_menus()


    /**
     * Ajax: get all link IDs currently in the table, for Step 2's check loop
     *
     * @return void
     */
    public function ajax_get_link_ids() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'blnotifier_links';

        $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
        if ( !$LINK_BROWSER->table_exists() ) {
            wp_send_json_success( [ 'ids' => [] ] );
        }

        $ids = $wpdb->get_col( "SELECT id FROM $table_name" ); // phpcs:ignore

        wp_send_json_success( [ 'ids' => array_map( 'absint', $ids ) ] );
    } // End ajax_get_link_ids()


    /**
     * Ajax: check a single stored link and add it to Results if broken/warning
     *
     * @return void
     */
    public function ajax_check_link() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $link_id = isset( $_REQUEST[ 'linkID' ] ) ? absint( wp_unslash( $_REQUEST[ 'linkID' ] ) ) : 0;
        if ( !$link_id ) {
            wp_send_json_error( [ 'msg' => __( 'No link ID provided.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'blnotifier_links';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $link_id ) ); // phpcs:ignore

        if ( !$row ) {
            wp_send_json_error( [ 'msg' => __( 'Link not found.', 'broken-link-notifier' ) ] );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        $status = $HELPERS->check_link( $row->link );

        if ( $status[ 'type' ] === 'broken' || $status[ 'type' ] === 'warning' ) {

            $sources = json_decode( $row->sources, true );
            $sources = is_array( $sources ) ? $sources : [];

            $source_url = '';
            foreach ( $sources as $source_id ) {
                if ( $source_id && get_post( $source_id ) ) {
                    $source_url = get_the_permalink( $source_id );
                    break;
                }
            }
            if ( !$source_url ) {
                $source_url = home_url();
            }

            $RESULTS = new BLNOTIFIER_RESULTS;
            $RESULTS->add( [
                'type'     => $status[ 'type' ],
                'code'     => $status[ 'code' ],
                'text'     => $status[ 'text' ],
                'link'     => $status[ 'link' ],
                'source'   => $source_url,
                'author'   => get_current_user_id(),
                'location' => 'content',
                'method'   => 'site-scan',
            ] );
        }

        wp_send_json_success( [ 'status' => $status ] );
    } // End ajax_check_link()


    /**
     * Ajax: record when Step 2 last ran
     *
     * @return void
     */
    public function ajax_finish() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $checked = isset( $_REQUEST[ 'checked' ] ) ? absint( wp_unslash( $_REQUEST[ 'checked' ] ) ) : 0;
        $broken  = isset( $_REQUEST[ 'broken' ] ) ? absint( wp_unslash( $_REQUEST[ 'broken' ] ) ) : 0;
        $warning = isset( $_REQUEST[ 'warning' ] ) ? absint( wp_unslash( $_REQUEST[ 'warning' ] ) ) : 0;

        update_option( 'blnotifier_site_scan_last_check', [
            'time'    => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
            'checked' => $checked,
            'broken'  => $broken,
            'warning' => $warning,
        ] );

        wp_send_json_success();
    } // End ajax_finish()

}