<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// Get the active tab
$tab = (new BLNOTIFIER_HELPERS)->get_tab();
$final_tab = $tab ?: 'results';

// Get the menu items
$BLNOTIFIER_MENU = (new BLNOTIFIER_MENU);
$menu_items = $BLNOTIFIER_MENU->menu_items;
?>

<div class="wrap blnotifier-wrap <?php echo esc_attr( BLNOTIFIER_TEXTDOMAIN ); ?>">

    <div class="blnotifier-content-wrap tab-content">
        <?php
        $page_file = BLNOTIFIER_PLUGIN_INCLUDES_PATH.'page-'.sanitize_key( $final_tab ).'.php';

        if ( file_exists( $page_file ) ) {
            include $page_file;
        } else {
            ?>
            <div class="blnotifier-box">
                <div class="blnotifier-box-body">
                    <p><?php esc_html_e( "That page couldn't be found.", 'broken-link-notifier' ); ?></p>
                    <a href="<?php echo esc_url( $BLNOTIFIER_MENU->get_plugin_page( 'results' ) ); ?>" class="blnotifier-button">Go to Results</a>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>