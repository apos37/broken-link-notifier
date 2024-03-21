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
        if ( !wp_verify_nonce( $_REQUEST[ 'nonce' ], $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }
    
        // Get the ID
        $link = sanitize_text_field( $_REQUEST[ 'link' ] );
        $post_id = isset( $_REQUEST[ 'postID' ] ) ? absint( $_REQUEST[ 'postID' ] ) : false;

        // Make sure we have a source URL
        if ( $link ) {

            // Initiate helpers
            $HELPERS = new BLNOTIFIER_HELPERS;

            // Check status
            $status = $HELPERS->check_link( $link );

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
        if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) == 'xmlhttprequest' ) {
            echo wp_json_encode( $result );
        } else {
            header( 'Location: '.$_SERVER[ 'HTTP_REFERER' ] );
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

        if ( ( $screen == $options_page && $tab == 'scan-single' ) || ( $screen == 'edit.php' && isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( $_REQUEST[ '_wpnonce' ], 'blnotifier_blinks' ) && isset( $_GET[ 'blinks' ] ) && sanitize_key( $_GET[ 'blinks' ] ) == 'true' ) ) {
            if ( !$tab ) {
                $tab = 'scan-full';
            }

            // Nonce
            $nonce = wp_create_nonce( $this->nonce );

            // Register, localize, and enqueue
            $handle = 'blnotifier_'.str_replace( '-', '_', $tab ).'_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.$tab.'.js', [], BLNOTIFIER_VERSION, true );
            wp_localize_script( $handle, 'blnotifier_'.str_replace( '-', '_', $tab ), [
                'nonce'   => $nonce,
                'ajaxurl' => admin_url( 'admin-ajax.php' ) 
            ] );
            wp_enqueue_script( $handle );
            // wp_enqueue_script( 'jquery' );
        }
    } // End enqueue_scripts()
}