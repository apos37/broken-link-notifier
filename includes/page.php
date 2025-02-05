<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// Get the active tab
$tab = (new BLNOTIFIER_HELPERS)->get_tab();
$final_tab = $tab ?? 'results';

// Get the menu items
$BLNOTIFIER_MENU = (new BLNOTIFIER_MENU);
$menu_items = $BLNOTIFIER_MENU->menu_items;
$submenu_title = isset( $menu_items[ $tab ] ) ? '— '.$menu_items[ $tab ][0] : '';
?>
<style>
h2 { margin: 3rem 0 1rem 0; }
</style>

<div class="wrap <?php echo esc_attr( BLNOTIFIER_TEXTDOMAIN ); ?>">

    <div class="admin—title-cont">
        <h1><span id="plugin-page-title"><?php echo esc_attr( BLNOTIFIER_NAME ).' '.esc_html( $submenu_title ); ?></span></h1>
    </div>
    <div id="plugin-version"><?php echo esc_html__( 'Version', 'broken-link-notifier' ); ?> <?php echo esc_attr( BLNOTIFIER_VERSION ); ?></div>

    <br><br>
    <div class="tab-content">
        <?php
        foreach ( $menu_items as $key => $menu_item ) {
            if ( $final_tab === $key ) { 
                include 'page-'.$key.'.php';
            }
        }

        // What to do if there is no tab?
        if ( !$tab || !array_key_exists( $tab, $menu_items ) ) {
            ?>
            <br><br>
            <?php
            wp_safe_redirect( $BLNOTIFIER_MENU->get_plugin_page() ); exit();
        }
        ?>
    </div>
</div>