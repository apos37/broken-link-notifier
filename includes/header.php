<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Menu + active tab
$MENU = new BLNOTIFIER_MENU;
$menu_items = $MENU->menu_items;
$active_tab = isset( $final_tab ) ? $final_tab : ( (new BLNOTIFIER_HELPERS)->get_tab() ?: false );

// Self-detect the tab when included outside page.php (e.g. taxonomy list screens)
if ( ! $active_tab ) {
    $current_screen = get_current_screen();
    if ( isset( $current_screen->id ) && $current_screen->id === 'edit-omit-links' ) {
        $active_tab = 'omit-links';
    } elseif ( isset( $current_screen->id ) && $current_screen->id === 'edit-omit-pages' ) {
        $active_tab = 'omit-pages';
    } else {
        $active_tab = 'results';
    }
}

// Logo
$logo_url = BLNOTIFIER_PLUGIN_IMG_PATH.'logo-transparent.png';
?>
<div id="blnotifier-header">
    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( BLNOTIFIER_NAME ); ?> Logo" class="logo">

    <div class="title-cont">
        <h1><?php echo esc_html( BLNOTIFIER_NAME ); ?></h1>
    </div>

    <div class="tabs-wrapper">
        <?php foreach ( $menu_items as $key => $menu_item ) : ?>
            <?php
            $link = isset( $menu_item[1] ) ? admin_url( $menu_item[1] ) : $MENU->get_plugin_page( $key );
            $class = ( $active_tab === $key ) ? 'blnotifier-tab blnotifier-tab-active' : 'blnotifier-tab';
            ?>
            <a href="<?php echo esc_url( $link ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $menu_item[0] ); ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div id="blnotifier-subheader">
    <div class="subheader-left">
        <h2 class="tab-title"><?php echo esc_html( isset( $menu_items[ $active_tab ] ) ? $menu_items[ $active_tab ][0] : ucwords( str_replace( '-', ' ', $active_tab ) ) ); ?></h2>
        <?php do_action( 'blnotifier_subheader_left', $active_tab ); ?>
    </div>
    <div class="subheader-right<?php echo ( $active_tab === 'link-browser' ) ? ' bln-gap-2rem' : ''; ?>">
        <?php do_action( 'blnotifier_subheader_right', $active_tab ); ?>
    </div>
</div>