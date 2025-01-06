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
	 * Constructor
	 */
	public function __construct() {

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key, [ $this, 'ajax' ] );
        add_action( 'wp_ajax_nopriv_'.$this->ajax_key, [ $this, 'must_login' ] );
        
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
        if ( !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }

        // Initiate helpers
        $HELPERS = new BLNOTIFIER_HELPERS;
    
        // Get the ID
        $link = $HELPERS->sanitize_link( $_REQUEST[ 'link' ] );
        $post_id = isset( $_REQUEST[ 'postID' ] ) ? absint( $_REQUEST[ 'postID' ] ) : false;

        // Make sure we have a source URL
        if ( $link ) {

            // Check status
            $status = $HELPERS->check_link( $link );

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
                    'source'   => get_the_permalink( $post_id ),
                    'author'   => get_current_user_id(),
                    'location' => 'content'
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
            $result[ 'msg' ] = 'No link found';
        }
    
        // Echo the result or redirect
        if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( sanitize_key( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) ) == 'xmlhttprequest' ) {
            echo wp_json_encode( $result );
        } else {
            header( 'Location: '.filter_var( $_SERVER[ 'HTTP_REFERER' ], FILTER_SANITIZE_URL ) );
        }
    
        // We're done here
        die();
    } // End ajax()


    /**
     * What to do if they are not logged in
     *
     * @return void
     */
    public function must_login() {
        die();
    } // End must_login()


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

        if ( ( $screen == $options_page && $tab == 'scan-single' && isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_scan_single' ) && isset( $_REQUEST[ 'scan' ] ) && sanitize_text_field( $_REQUEST[ 'scan' ] ) ) || ( $screen == 'edit.php' && isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_blinks' ) && isset( $_REQUEST[ 'blinks' ] ) && sanitize_key( $_REQUEST[ 'blinks' ] ) == 'true' ) ) {
            if ( !$tab ) {
                $tab = 'scan-multi';
            }
            if ( $tab == 'scan-single' ) {
                $post_id = url_to_postid( filter_var( $_REQUEST[ 'scan' ], FILTER_SANITIZE_URL ) );
            } else {
                $post_id = false;
            }

            // Nonce
            $nonce = wp_create_nonce( $this->nonce );

            // Register, localize, and enqueue
            $handle = 'blnotifier_'.str_replace( '-', '_', $tab ).'_script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $handle, site_url().BLNOTIFIER_PLUGIN_JS_PATH.$tab.'.js', [ 'jquery' ], time() );
            wp_localize_script( $handle, 'blnotifier_'.str_replace( '-', '_', $tab ), [
                'post_id' => $post_id, 
                'nonce'   => $nonce,
                'ajaxurl' => admin_url( 'admin-ajax.php' ) 
            ] );
            wp_enqueue_script( $handle );
        }
    } // End enqueue_scripts()
}