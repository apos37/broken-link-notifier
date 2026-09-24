<?php
/**
 * Shared scan class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
add_action( 'init', function() {
    new BLNOTIFIER_SCAN;
} );


/**
 * Main plugin class.
 */
class BLNOTIFIER_SCAN {

    /**
     * The key that is used to identify the ajax response
     *
     * @var string
     */
    private $ajax_key = 'blnotifier_scan';


    /**
     * Name of nonce used for ajax call
     *
     * @var string
     */
    private $nonce = 'blnotifier_scan';


    /**
     * Nonce used for the title/URL/ID suggestion ajax
     *
     * @var string
     */
    private $suggest_nonce = 'blnotifier_scan_suggest';


    /**
	 * Constructor
	 */
	public function __construct() {

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key, [ $this, 'ajax' ] );
        add_action( 'wp_ajax_'.$this->suggest_nonce, [ $this, 'ajax_suggest' ] );

        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End __construct()


    /**
     * Ajax call
     *
     * @return void
     */
    public function ajax() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( esc_html__( 'No naughty business please.', 'broken-link-notifier' ) );
        }        

        // Check permissions
        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            exit( esc_html__( 'Unauthorized access.', 'broken-link-notifier' ) );
        }

        // Initiate helpers
        $HELPERS = new BLNOTIFIER_HELPERS;

        // Get the ID
        $link     = isset( $_REQUEST[ 'link' ] ) ? $HELPERS->sanitize_link( wp_unslash( $_REQUEST[ 'link' ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $post_id  = isset( $_REQUEST[ 'postID' ] ) ? absint( wp_unslash( $_REQUEST[ 'postID' ] ) ) : false;
        $method   = isset( $_REQUEST[ 'method' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'method' ] ) ) : false;

        // Make sure we have a source URL
        if ( $link ) {

            // Check status
            $status = $HELPERS->check_link( $link );

            // Determine the source URL, falling back to the homepage if there's no valid post (e.g. a menu-only link)
            $source_url = ( $post_id && get_post( $post_id ) ) ? get_the_permalink( $post_id ) : home_url();

            // Add to results
            $bad_status_codes = $HELPERS->get_bad_status_codes();
            $warning_status_codes = $HELPERS->get_warning_status_codes();
            $notify_status_codes = array_merge( $bad_status_codes, $warning_status_codes );
            if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                (new BLNOTIFIER_RESULTS)->add( [
                    'type'     => $status[ 'type' ],
                    'code'     => $status[ 'code' ],
                    'text'     => $status[ 'text' ],
                    'link'     => $status[ 'link' ],
                    'source'   => $source_url,
                    'author'   => get_current_user_id(),
                    'location' => 'content',
                    'method'   => $method
                ] );
            }

            // Return
            $result[ 'type' ] = 'success';
            $result[ 'status' ] = $status;
            $result[ 'link' ] = $link;
            $result[ 'post_id' ] = $post_id;

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = __( 'No link found', 'broken-link-notifier' );
        }

        // Echo the result or redirect
        if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( sanitize_key( wp_unslash( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) ) ) === 'xmlhttprequest' ) {
            echo wp_json_encode( $result );
        } else {
            $referer = isset( $_SERVER[ 'HTTP_REFERER' ] ) ? filter_var( wp_unslash( $_SERVER[ 'HTTP_REFERER' ] ), FILTER_SANITIZE_URL ) : '';
            header( 'Location: ' . $referer );
        }

        // We're done here
        die();
    } // End ajax()


    /**
     * Ajax: suggest posts by title while typing
     *
     * @return void
     */
    public function ajax_suggest() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->suggest_nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $search = isset( $_REQUEST[ 'search' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'search' ] ) ) : '';
        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( [ 'items' => [] ] );
        }

        $post_types = (new BLNOTIFIER_HELPERS)->get_allowed_multiscan_post_types();

        $query = new WP_Query( [
            's'                   => $search,
            'post_type'           => $post_types,
            'post_status'         => [ 'publish', 'private', 'draft', 'pending' ],
            'posts_per_page'      => 10,
            'orderby'             => 'relevance',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ] );

        $items = [];
        foreach ( $query->posts as $post ) {
            $type_object = get_post_type_object( $post->post_type );
            $status_object = get_post_status_object( $post->post_status );

            $items[] = [
                'id'     => $post->ID,
                'title'  => $post->post_title ? $post->post_title : '(no title)',
                'type'   => $type_object ? $type_object->labels->singular_name : $post->post_type,
                'status' => $status_object ? $status_object->label : $post->post_status,
            ];
        }

        wp_send_json_success( [ 'items' => $items ] );
    } // End ajax_suggest()


    /**
     * Enqueue script
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        // Only on these pages
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();

        // Stylesheet for Page Scan
        if ( $screen === $options_page && $tab === 'scan-single' ) {
            wp_enqueue_style( 'blnotifier-scan', BLNOTIFIER_PLUGIN_CSS_PATH.'scan.css', [ 'blnotifier-theme' ], BLNOTIFIER_SCRIPT_VERSION );
        }

        // Suggestion field script, loads any time we're on Page Scan
        if ( $screen === $options_page && $tab === 'scan-single' ) {
            $suggest_handle = 'blnotifier_scan_suggest_script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $suggest_handle, site_url().BLNOTIFIER_PLUGIN_JS_PATH.'scan-suggest.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
            wp_localize_script( $suggest_handle, 'blnotifier_scan_suggest', [
                'nonce'       => wp_create_nonce( $this->suggest_nonce ),
                'omits_nonce' => wp_create_nonce( 'blnotifier_omit_something' ),
                'post_types'  => (new BLNOTIFIER_OMITS)->get_scannable_post_type_choices(),
                'ajaxurl'     => admin_url( 'admin-ajax.php' ),
                'text'        => [
                    'loading'          => __( 'Loading...', 'broken-link-notifier' ),
                    'choose_post_type' => __( 'Choose a Post Type First...', 'broken-link-notifier' ),
                    'no_items_found'   => __( 'No items found', 'broken-link-notifier' ),
                    'choose_a'         => __( 'Choose a ', 'broken-link-notifier' ),
                    'scanning'         => __( 'Scanning...', 'broken-link-notifier' ),
                ],
            ] );
            wp_enqueue_script( $suggest_handle );
        }

        $is_scan_single_page = (
            $screen === $options_page &&
            $tab === 'scan-single' &&
            isset( $_REQUEST[ '_wpnonce' ], $_REQUEST[ 'scan' ] ) &&
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_scan_single' ) &&
            sanitize_text_field( wp_unslash( $_REQUEST[ 'scan' ] ) )
        );
        
        $is_blinks_page = (
            $screen === 'edit.php' &&
            isset( $_REQUEST[ '_wpnonce' ], $_REQUEST[ 'blinks' ] ) &&
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_blinks' ) &&
            sanitize_key( wp_unslash( $_REQUEST[ 'blinks' ] ) ) === 'true'
        );        

        if ( $is_scan_single_page || $is_blinks_page ) {
            if ( !$tab ) {
                $tab = 'scan-multi';
            }

            if ( $tab == 'scan-single' ) {
                $post_id = url_to_postid( filter_var( wp_unslash( $_REQUEST[ 'scan' ] ), FILTER_SANITIZE_URL ) );
            } else {
                $post_id = false;
            }

            $nonce = wp_create_nonce( $this->nonce );

            $handle = 'blnotifier_'.str_replace( '-', '_', $tab ).'_script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $handle, site_url().BLNOTIFIER_PLUGIN_JS_PATH.$tab.'.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );

            $localize_data = [
                'post_id' => $post_id, 
                'nonce'   => $nonce,
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'text'    => [
                    'scanning_link'          => __( 'Scanning Link', 'broken-link-notifier' ),
                    'scanning'               => __( 'Scanning', 'broken-link-notifier' ),
                    'please_try_again'       => __( 'Please try again.', 'broken-link-notifier' ),
                    'skipping_missing_links' => __( 'Skipping missing links', 'broken-link-notifier' ),
                ],
            ];

            if ( $tab === 'scan-multi' ) {
                $localize_data[ 'text' ] = array_merge( $localize_data[ 'text' ], [
                    'scanning_complete' => __( 'Scanning Complete', 'broken-link-notifier' )
                ] );
            } elseif ( $tab === 'scan-single' ) {
                $localize_data[ 'text' ] = array_merge( $localize_data[ 'text' ], [
                    'title_broken'      => __( "If the link works fine and it's still being flagged as broken, then there is an issue with the page's response headers and there's nothing we can do about it. You may use the Omit option on the right to omit it from future scans.", 'broken-link-notifier' ),
                    'title_warning'     => __( "Warnings mean the link was found, but they may be unsecure or slow to respond. If you are getting too many warnings due to timeouts, try increasing your timeout in Settings. This will just result in longer wait times, but with more accuracy.", 'broken-link-notifier' ),
                    'title_405'         => __( "405 Method Not Allowed indicates that the target resource doesn't support checking for header responses using our method, but is still telling us that the page exists which is what we actually want to know. So it's fine; nothing to worry about.", 'broken-link-notifier' ),
                    'scanning_complete' => __( 'Scanning links complete.', 'broken-link-notifier' ),
                    'redirect'          => __( 'Redirect', 'broken-link-notifier' ),
                ] );
            }

            wp_localize_script( $handle, 'blnotifier_'.str_replace( '-', '_', $tab ), $localize_data );
            wp_enqueue_script( $handle );
        }
    } // End enqueue_scripts()
    
}