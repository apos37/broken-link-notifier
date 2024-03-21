<?php
/**
 * Integrations class file
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
new BLNOTIFIER_INTEGRATIONS;


/**
 * Main plugin class.
 */
class BLNOTIFIER_INTEGRATIONS {


    /**
	 * Constructor
	 */
	public function __construct() {

        // ERI files
        add_filter( 'blnotifier_link_before_prechecks', [ $this, 'eri_files' ] );
        
	} // End __construct()

    
    /**
     * ERI files
     *
     * @param string $link
     * @return array|string|false
     */
    public function eri_files( $link ) {
        // Filter ajax calls
        if ( strpos( $link, '/admin-ajax.php' ) !== false ) {
            $url_components = wp_parse_url( $link );
            $query_strings = explode( '&', $url_components[ 'query' ] );
            $action = false;
            $file_id = false;
            foreach ( $query_strings as $query_string ) {
                $query_string = str_replace( 'amp;', '', $query_string );
                if ( substr( $query_string, 0, strlen( 'action=' ) ) == 'action=' ) {
                    $action = substr( $query_string, strlen( 'action=' ) );
                } 
                if ( substr( $query_string, 0, strlen( 'post_id=' ) ) == 'post_id=' ) {
                    $file_id = substr( $query_string, strlen( 'post_id=' ) );
                }
            }

            // Check the action
            if ( $action == 'eri_file_count' && $file_id ) {

                // Check for a filename
                $filename = get_post_meta( $file_id, '_post_url', true );
                if ( $filename && sanitize_text_field( $filename ) != '' ) {
                    $filename = sanitize_text_field( $filename );

                    // Rebuild the link with the file name
                    $upload_dir = wp_upload_dir();
                    $folder = get_option( 'eri_files_folder', 'eri-files' );
                    $file_dir = $upload_dir[ 'basedir' ].'/'.$folder;
                    $file_dir_full_path = $file_dir.'/'.$filename;
                    $file_url = $upload_dir[ 'baseurl' ].'/'.$folder.'/'.$filename;

                    // Check if it's a legitament file
                    if ( is_file( $file_dir_full_path ) ) {
                        $status = [
                            'type' => 'good',
                            'code' => 200,
                            'text' => 'File #'.$file_id.' exists at '.$file_url,
                            'link' => $link
                        ];
                    } else {
                        $status = [
                            'type' => 'broken',
                            'code' => 666, // We use 666 as an alternative to 0 in case warnings are disabled
                            'text' => 'File #'.$file_id.' not found at '.$file_url,
                            'link' => $link
                        ];
                    }
                    return $status;
                }
            }
        }

        // Always return link
        return $link;
    } // End eri_files

}