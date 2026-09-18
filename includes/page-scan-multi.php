<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

$blnotifier_is_enabled = apply_filters( 'blnotifier_enable_legacy_multiscan', false ) || (new BLNOTIFIER_HELPERS)->is_test_mode();

if ( !$blnotifier_is_enabled ) {
    ?>
    <div class="blnotifier-box">
        <div class="blnotifier-box-body">
            <p>
                <?php
                printf(
                    /* translators: %s: link to the Site Scan tab, with "Site Scan" as the link text */
                    esc_html__( 'Multi-Scan has been replaced by %s and is disabled by default.', 'broken-link-notifier' ),
                    '<a href="' . esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'site-scan' ) ) . '">' . esc_html__( 'Site Scan', 'broken-link-notifier' ) . '</a>'
                );
                ?>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: the literal functions.php filename */
                    esc_html__( "Developers can still re-enable this legacy tool by adding the following to a custom plugin or their theme's %s:", 'broken-link-notifier' ),
                    '<code>functions.php</code>'
                );
                ?>
            </p>
            <pre><code>add_filter( 'blnotifier_enable_legacy_multiscan', '__return_true' );</code></pre>
        </div>
    </div>
    <?php
    return;
}

// Initiate
$BLNOTIFIER_HELPERS = new BLNOTIFIER_HELPERS;
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">
        <p>
            <?php
            printf(
                /* translators: %s: link to the Site Scan tab, with "Site Scan" as the link text */
                esc_html__( '%s covers everything linked from your own pages and menus, but may take awhile to complete depending on how many pages and links you have. If you want a more exhaustive crawl (including pages not linked from anywhere on your site), here are a few off-site tools worth considering:', 'broken-link-notifier' ),
                '<a href="' . esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'site-scan' ) ) . '">' . esc_html__( 'Site Scan', 'broken-link-notifier' ) . '</a>'
            );
            ?>
        </p>
        <ul>
            <?php
            foreach ( $BLNOTIFIER_HELPERS->get_suggested_offsite_checkers() as $blnotifier_name => $blnotifier_url ) {
                ?>
                <li><a href="<?php echo esc_url( $blnotifier_url ); ?>" target="_blank"><?php echo esc_html( $blnotifier_name ); ?></a></li>
                <?php
            }
            ?>
        </ul>
        <br>
        <p><?php echo esc_html__( 'The way we do it is by loading your WP List Tables for individual post types, checking one set of pages at a time. We also ignore the header and footer during the process since it\'s unlikely to be an issue. The scan runs on AJAX in the background, too, so you can see the results as they happen. Give it a try!', 'broken-link-notifier' ); ?></p>
        <?php
        $blnotifier_post_types = get_option( 'blnotifier_post_types' );
        $blnotifier_post_types = !empty( $blnotifier_post_types ) ? array_keys( $blnotifier_post_types ) : [ 'post', 'page' ];
        foreach ( $blnotifier_post_types as $blnotifier_post_type ) {
            $blnotifier_count = $BLNOTIFIER_HELPERS->count_posts_by_status( 'publish', $blnotifier_post_type );
            $blnotifier_post_type_name = $BLNOTIFIER_HELPERS->get_post_type_name( $blnotifier_post_type );
            $blnotifier_url = add_query_arg( [
                'post_status' => 'publish',
                'post_type'   => $blnotifier_post_type,
                'mode'        => 'list',
                'blinks'      => 'true',
                '_wpnonce'    => wp_create_nonce( 'blnotifier_blinks' )
            ], admin_url( 'edit.php' ) );
            ?>
            <a href="<?php echo esc_url( $blnotifier_url ); ?>" target="_blank" class="scan-button blnotifier-button" style="margin-right: 10px;">Scan <?php echo esc_html( $blnotifier_post_type_name ); ?>  (<?php echo absint( $blnotifier_count ); ?>)</a>
            <?php
        }
        ?>
        <br><br>
        <em><?php echo esc_html__( 'You can change the number of posts scanned at a time by going to Screen Options at the top of the WP List Table pages:', 'broken-link-notifier' ); ?></em><br><br>
        <img src="<?php echo esc_url( BLNOTIFIER_PLUGIN_IMG_PATH ); ?>screen_options.png">
    </div>
</div>