<?php
$HELPERS = new BLNOTIFIER_HELPERS;
?>
<style>
.scan-button {
    margin-top: 5px !important;
}
</style>
<p>Our "Full Scan" is different than most plugins. It doesn't just scan the whole website and give you results like you would expect. Sorry to make things complicated for you. The reason we don't do that, though, is because most hosts do not allow for such a big load all at once. It causes a slew of issues and often times out. Generally speaking, doing a full scan like that is better to be done off-site. Here are a few links to consider:</p>
<ul>
    <?php
    foreach ( $HELPERS->get_suggested_offsite_checkers() as $name => $url ) {
        ?>
        <li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $name ); ?></a></li>
        <?php
    }
    ?>
</ul>
<br>
<p>The way we do it is by loading your WP List Tables for individual post types, checking one set of pages at a time. We also ignore the header and footer during the process since it's unlikely to be an issue. The scan runs on AJAX in the background, too, so you can see the results as they happen. Give it a try!</p>
<?php
$post_types = get_option( 'blnotifier_post_types' );
$post_types = !empty( $post_types ) ? array_keys( $post_types ) : [ 'post', 'page' ];
foreach ( $post_types as $post_type ) {
    $count = $HELPERS->count_posts_by_status( 'publish', $post_type );
    $post_type_name = $HELPERS->get_post_type_name( $post_type );
    ?>
    <a href="/<?php echo esc_html( BLNOTIFIER_ADMIN_DIR ); ?>/edit.php?post_status=publish&post_type=<?php echo esc_attr( $post_type ); ?>&mode=list&blinks=true&_wpnonce=<?php echo wp_create_nonce( 'blnotifier_blinks' ); ?>" target="_blank" class="scan-button button button-primary" style="margin-right: 10px;">Scan <?php echo esc_html( $post_type_name ); ?>  (<?php echo absint( $count ); ?>)</a>
    <?php
}
?>
<br><br><br>
<em>You can change the number of posts scanned at a time by going to Screen Options at the top of the WP List Table pages:</em><br><br>
<img src="<?php echo esc_url( BLNOTIFIER_PLUGIN_IMG_PATH ); ?>screen_options.png">