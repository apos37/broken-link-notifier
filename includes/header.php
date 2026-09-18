<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Menu + active tab
$BLNOTIFIER_MENU = new BLNOTIFIER_MENU;
$blnotifier_menu_items = $BLNOTIFIER_MENU->menu_items;
$blnotifier_active_tab = isset( $blnotifier_final_tab ) ? $blnotifier_final_tab : ( (new BLNOTIFIER_HELPERS)->get_tab() ?: false );

// Self-detect the tab when included outside page.php (e.g. taxonomy list screens)
if ( ! $blnotifier_active_tab ) {
    $blnotifier_current_screen = get_current_screen();
    if ( isset( $blnotifier_current_screen->id ) && $blnotifier_current_screen->id === 'edit-omit-links' ) {
        $blnotifier_active_tab = 'omit-links';
    } elseif ( isset( $blnotifier_current_screen->id ) && $blnotifier_current_screen->id === 'edit-omit-pages' ) {
        $blnotifier_active_tab = 'omit-pages';
    } else {
        $blnotifier_active_tab = 'results';
    }
}

// Logo
$blnotifier_logo_url = BLNOTIFIER_PLUGIN_IMG_PATH.'logo-transparent.png';
?>
<div id="blnotifier-header">
    <img src="<?php echo esc_url( $blnotifier_logo_url ); ?>" alt="<?php echo esc_attr( BLNOTIFIER_NAME ); ?> Logo" class="logo">

    <div class="title-cont">
        <h1><?php echo esc_html( BLNOTIFIER_NAME ); ?></h1>
    </div>

    <div class="tabs-wrapper">
        <?php foreach ( $blnotifier_menu_items as $blnotifier_key => $blnotifier_menu_item ) : ?>
            <?php
            $blnotifier_link = isset( $blnotifier_menu_item[1] ) ? admin_url( $blnotifier_menu_item[1] ) : $BLNOTIFIER_MENU->get_plugin_page( $blnotifier_key );
            $blnotifier_class = ( $blnotifier_active_tab === $blnotifier_key ) ? 'blnotifier-tab blnotifier-tab-active' : 'blnotifier-tab';
            ?>
            <a href="<?php echo esc_url( $blnotifier_link ); ?>" class="<?php echo esc_attr( $blnotifier_class ); ?>"><?php echo esc_html( $blnotifier_menu_item[0] ); ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div id="blnotifier-subheader">
    <div class="subheader-left">
        <h2 class="tab-title"><?php echo esc_html( isset( $blnotifier_menu_items[ $blnotifier_active_tab ] ) ? $blnotifier_menu_items[ $blnotifier_active_tab ][0] : ucwords( str_replace( '-', ' ', $blnotifier_active_tab ) ) ); ?></h2>
        <?php do_action( 'blnotifier_subheader_left', $blnotifier_active_tab ); ?>
    </div>
    <div class="subheader-right<?php echo ( $blnotifier_active_tab === 'link-browser' ) ? ' bln-gap-2rem' : ''; ?>">
        <?php do_action( 'blnotifier_subheader_right', $blnotifier_active_tab ); ?>
    </div>
</div>