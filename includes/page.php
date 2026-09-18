<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// Get the active tab
$blnotifier_tab = (new BLNOTIFIER_HELPERS)->get_tab();
$blnotifier_final_tab = $blnotifier_tab ?: 'results';

// Get the menu items
$BLNOTIFIER_MENU = (new BLNOTIFIER_MENU);
$blnotifier_menu_items = $BLNOTIFIER_MENU->menu_items;
?>

<div class="wrap blnotifier-wrap <?php echo esc_attr( BLNOTIFIER_TEXTDOMAIN ); ?>">

    <div class="blnotifier-content-wrap tab-content">
        <?php
        $blnotifier_page_file = BLNOTIFIER_PLUGIN_INCLUDES_PATH.'page-'.sanitize_key( $blnotifier_final_tab ).'.php';

        if ( file_exists( $blnotifier_page_file ) ) {
            include $blnotifier_page_file;
        } else {
            ?>
            <div class="blnotifier-box">
                <div class="blnotifier-box-body">
                    <p><?php esc_html_e( "That page couldn't be found.", 'broken-link-notifier' ); ?></p>
                    <a href="<?php echo esc_url( $BLNOTIFIER_MENU->get_plugin_page( 'results' ) ); ?>" class="blnotifier-button"><?php esc_html_e( 'Go to Results', 'broken-link-notifier' ); ?></a>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>