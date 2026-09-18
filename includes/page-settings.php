<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

$BLNOTIFIER_SETTINGS = new BLNOTIFIER_SETTINGS;
?>

<?php if ( isset( $_REQUEST[ 'settings-updated' ] ) ) { // phpcs:ignore ?>
    <?php if ( !get_option( 'blnotifier_has_updated_settings' ) ) { update_option( 'blnotifier_has_updated_settings', true ); } ?>
    <div id="message" class="updated">
        <p><strong><?php esc_html_e( 'Settings saved successfully.', 'broken-link-notifier' ) ?></strong></p>
    </div>
<?php } ?>

<form method="post" action="options.php" id="blnotifier-settings-form">
    <?php
        settings_fields( $BLNOTIFIER_SETTINGS->page_slug );
        $BLNOTIFIER_SETTINGS->render_settings_boxes();
    ?>
</form>