<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    
?>

<style>
input[type="text"], input[type="url"], textarea { width: 700px; }
textarea { height: 200px; }
.description {
    display: block;
    width: fit-content;
    margin: 3px 0 0 1px;
    border: 1px solid #e5e5e5;
    padding: 10px;
    font-size: 12px !important;
    line-height: 1.5;
    background-color: #f9f9f9;
    -webkit-box-shadow: 0 1px 1px rgba(0,0,0,.04);
    box-shadow: 0 1px 1px #0000000a;
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}
</style>

<?php if ( isset( $_REQUEST[ 'settings-updated' ] ) ) { // phpcs:ignore ?>
    <?php if ( !get_option( 'blnotifier_has_updated_settings' ) ) { update_option( 'blnotifier_has_updated_settings', true ); } ?>
    <div id="message" class="updated">
        <p><strong><?php esc_html__( 'Settings saved.', 'broken-link-notifier' ) ?></strong></p>
    </div>
<?php } ?>

<a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ); ?>" class="button button-secondary">View Results</a>
<br><br>

<div class="wrap">
    <form method="post" action="options.php">
        <?php
            settings_fields( $this->page_slug );
            do_settings_sections( $this->page_slug );
            submit_button();
        ?>
    </form>
</div>