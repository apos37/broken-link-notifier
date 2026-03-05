<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

/**
 * Skip links containing a specific query string before link checks.
 *
 * @param string|array $link The original link to be checked.
 * @return string|array Modified link or structured status array.
 */
add_filter( 'blnotifier_link_before_prechecks', function( $link ) {
    // Skip UTM tracking links
    if ( is_string( $link ) && strpos( $link, 'utm_' ) !== false ) {
        return [
            'type' => 'good',
            'code' => 200,
            'text' => 'Skipped due to tracking params',
            'link' => $link
        ];
    }

    return $link;
}, 10 );