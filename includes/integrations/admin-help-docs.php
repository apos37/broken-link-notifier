<?php
/**
 * INTEGRATION: ADMIN HELP DOCS
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function() {

    if ( !function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
    }

    if ( !is_plugin_active( 'admin-help-docs/admin-help-docs.php' ) ) {
        return;
    }

    add_filter( 'blnotifier_theme_colors', function( $colors ) {
        $ahd_colors = get_option( 'helpdocs_colors', [] );

        if ( !is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
            return $colors;
        }

        $map = [
            'header_bg'       => 'header-bg',
            'header_font'     => 'header-font',
            'header_tab'      => 'header-tab',
            'header_tab_link' => 'header-tab-link',
            'doc_accent'      => 'accent',
            'button'          => 'button',
            'button_font'     => 'button-font',
            'button_hover'    => 'button-hover',
        ];

        foreach ( $map as $ahd_key => $blnotifier_key ) {
            if ( !empty( $ahd_colors[ $ahd_key ] ) ) {
                $colors[ $blnotifier_key ] = sanitize_hex_color( $ahd_colors[ $ahd_key ] );
            }
        }

        return $colors;
    } );

} );