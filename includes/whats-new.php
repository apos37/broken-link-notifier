<?php
/**
 * What's New overlay
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function() {
    (new BLNOTIFIER_WHATS_NEW)->init();
} );

class BLNOTIFIER_WHATS_NEW {

    /**
     * The version this notice is for. Bump this whenever there's a new
     * major announcement worth showing again.
     *
     * @var string
     */
    private $notice_version = '2.0';


    /**
     * Load on init
     */
    public function init() {
        add_action( 'in_admin_header', [ $this, 'render' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_blnotifier_dismiss_whats_new', [ $this, 'ajax_dismiss' ] );
    } // End init()


    /**
     * Should the notice show for this user?
     *
     * @return boolean
     */
    public function should_show() {
        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            return false;
        }

        $screen = get_current_screen();
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        if ( !isset( $screen->id ) || $screen->id !== $options_page ) {
            return false;
        }

        $seen_version = get_option( 'blnotifier_whats_new_seen', '' );
        return $seen_version !== $this->notice_version;
    } // End should_show()


    /**
     * Enqueue assets
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        if ( !$this->should_show() ) {
            return;
        }

        wp_enqueue_style( 'blnotifier-whats-new', BLNOTIFIER_PLUGIN_CSS_PATH.'whats-new.css', [], BLNOTIFIER_SCRIPT_VERSION );

        $handle = 'blnotifier_whats_new_script';
        wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'whats-new.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
        wp_localize_script( $handle, 'blnotifier_whats_new', [
            'nonce'   => wp_create_nonce( 'blnotifier_dismiss_whats_new' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ] );
        wp_enqueue_script( $handle );
        wp_enqueue_script( 'jquery' );
    } // End enqueue_scripts()


    /**
     * Render the overlay
     *
     * @return void
     */
    public function render() {
        if ( !$this->should_show() ) {
            return;
        }
        ?>
        <div id="blnotifier-whats-new-overlay">
            <div id="blnotifier-whats-new-modal">
                <button type="button" id="blnotifier-whats-new-close" aria-label="Close">&times;</button>

                <div class="blnotifier-whats-new-header">
                    <img src="<?php echo esc_url( BLNOTIFIER_PLUGIN_IMG_PATH.'logo-transparent.png' ); ?>" alt="<?php echo esc_attr( BLNOTIFIER_NAME ); ?>" class="logo">
                    <h1>What's New in <?php echo esc_html( BLNOTIFIER_NAME ); ?> 2.0</h1>
                </div>

                <div class="blnotifier-whats-new-body">
                    <ul class="blnotifier-whats-new-list">
                        <li>
                            <strong>Site Scan</strong>
                            <p>A brand new two-step scanner: discover every link on your site, then check them all for broken links and warnings — no more scanning through WP List Tables.</p>
                        </li>
                        <li>
                            <strong>Link Browser</strong>
                            <p>Browse every link found on your site in one searchable, filterable table — see where each one is used before you even check its status.</p>
                        </li>
                        <li>
                            <strong>A completely redesigned interface</strong>
                            <p>New header, navigation, and layout across every page, built to match the rest of the PluginRx family.</p>
                        </li>
                        <li>
                            <strong>Smarter Results verification</strong>
                            <p>Verifying a link now also confirms it's still referenced on its source page, and automatically clears it with a clear reason if it's not.</p>
                        </li>
                        <li>
                            <strong>And much more</strong>
                            <p>Smarter Settings tools, quick-add helpers on Omitted Links/Pages, autocomplete on Page Scan and Link Search, automatic redirect detection, flexible CSV exports, and a batch of new developer filters.</p>
                        </li>
                    </ul>
                </div>

                <div class="blnotifier-whats-new-footer">
                    <a href="<?php echo esc_url( BLNOTIFIER_GUIDE_URL ); ?>" target="_blank" class="blnotifier-button bln-external-link">Read the Full Guide <span class="dashicons dashicons-external"></span></a>
                    <button type="button" id="blnotifier-whats-new-got-it" class="blnotifier-button">Got It, Thanks!</button>
                </div>
            </div>
        </div>
        <?php
    } // End render()


    /**
     * Ajax: mark the notice as seen
     *
     * @return void
     */
    public function ajax_dismiss() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), 'blnotifier_dismiss_whats_new' ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid nonce.' ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => 'Unauthorized.' ] );
        }

        update_option( 'blnotifier_whats_new_seen', $this->notice_version );

        wp_send_json_success();
    } // End ajax_dismiss()

}