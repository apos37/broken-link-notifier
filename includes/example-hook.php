<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

/**
 * An example of how to filter a link before it's checked for validity
 *
 * @param string $link
 * @return string|false
 */
function blnotifier_link( $link ) {
    // Filter ajax calls
    if ( strpos( $link, '/admin-ajax.php' ) !== false ) {

        // Parse the url (ie. https://mydomain.com/wp-admin/admin-ajax.php?action=my_action&post_id=12345&nonce=1234567890)
        $url_components = wp_parse_url( $link );
        $query_strings = explode( '&', $url_components[ 'query' ] );
        $action = false;
        $post_id = false;
        foreach ( $query_strings as $query_string ) {
            $query_string = str_replace( 'amp;', '', $query_string );
            if ( substr( $query_string, 0, strlen( 'action=' ) ) == 'action=' ) {
                $action = substr( $query_string, strlen( 'action=' ) );
            } 
            if ( substr( $query_string, 0, strlen( 'post_id=' ) ) == 'post_id=' ) {
                $post_id = substr( $query_string, strlen( 'post_id=' ) );
            }
        }

        // Check the action and make sure we have a post id
        if ( $action == 'my_action' && $post_id ) {

            // Check the post it's associated with
            $meta_value = get_post_meta( $post_id, 'meta_key', true );

            // New url to pass through all the checks
            if ( $meta_value && $meta_value == 'some keyword' ) {
                $link = 'https://somewebsite.com/'.$meta_value;
            }

        // Or simply return a status
        } elseif ( $action == 'my_other_action' ) {

            // Condition
            if ( get_post( $post_id ) ) {
                $status = [
                    'type' => 'good',
                    'code' => 200,
                    'text' => 'Post exists: '.get_the_title( $post_id ),
                    'link' => $link
                ];
            } else {
                $status = [
                    'type' => 'broken',
                    'code' => 666, // We use 666 as an alternative to 0 in case warnings are disabled
                    'text' => 'Post does not exist',
                    'link' => $link
                ];
            }
            return $status;
        }
    }

    // Always return link
    return $link;
} // End blnotifier_link()

add_filter( 'blnotifier_link_before_prechecks', 'blnotifier_link' );