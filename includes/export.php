<?php
/**
 * Export class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initialize the class
 */

new BLNOTIFIER_EXPORT();


/**
 * Main plugin class.
 */
class BLNOTIFIER_EXPORT {

    /**
     * Length of time to cache links
     *
     * @var int
     */
    public $nonce = 'blnotifier_export_nonce';


    /**
	 * Constructor
	 */
	public function __construct() {

        // Handler
        add_action( 'admin_init', [ $this, 'handler' ] );
        
	} // End __construct()


    /**
     * Handle export request for different types of links.
     */
    public function handler() {
        if ( !isset( $_GET[ 'export' ], $_GET[ '_wpnonce' ] ) ) {
            return;
        }

        // Capability check: only allow admins or users with manage_options capability
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to export links.', 'broken-link-notifier' ) );
        }

        if ( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ '_wpnonce' ] ) ), $this->nonce ) ) {
            wp_die( __( 'Security check failed.', 'broken-link-notifier' ) );
        }

        $type = sanitize_key( $_GET[ 'export' ] );
        $links = (new BLNOTIFIER_HELPERS())->get_links( $type );
        $headers = [
            'date'            => __( 'Date', 'broken-link-notifier' ),
            'type'            => __( 'Type', 'broken-link-notifier' ),
            'code'            => __( 'Code', 'broken-link-notifier' ),
            'message'         => __( 'Message', 'broken-link-notifier' ),
            'link'            => __( 'Link', 'broken-link-notifier' ),
            'source_name'     => __( 'Source Name', 'broken-link-notifier' ),
            'source_link'     => __( 'Source URL', 'broken-link-notifier' ),
            'source_posttype' => __( 'Source Post Type', 'broken-link-notifier' ),
            'location'        => __( 'Location', 'broken-link-notifier' ),
            'User'            => __( 'User', 'broken-link-notifier' ),
            'Method'          => __( 'Method', 'broken-link-notifier' ),
        ];

        if ( empty( $links ) ) {
            wp_die( __( 'No links found to export.', 'broken-link-notifier' ) );
        }

        $this->send_csv_headers( $type );
        $this->output_csv( $links, $headers );
        exit;
    } // End handler()


    /**
     * Output CSV headers.
     *
     * @param string $type
     */
    protected function send_csv_headers( $type ) {
        $filename = "blnotifier-{$type}-export-" . gmdate( 'Y-m-d-H-i-s' ) . ".csv";

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
    } // End send_csv_headers()


    /**
     * Output CSV content to browser.
     *
     * @param array $data
     */
    protected function output_csv( array $data, array $headers ) {
        $output = fopen( 'php://output', 'w' );

        // Convert the headers
        $header_keys = array_keys( $data[0] );
        $header_row = [];
        foreach ( $header_keys as $key ) {
            if ( isset( $headers[ $key ] ) ) {
                $header_row[] = $headers[ $key ];
            } else {
                $header_row[] = ucwords( $key );
            }
        }

        // Add headers from array keys
        fputcsv( $output, $header_row );

        foreach ( $data as $row ) {
            fputcsv( $output, $row );
        }

        fclose( $output );
    } // End output_csv()

}