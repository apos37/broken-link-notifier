<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// Initiate
$MENU = new BLNOTIFIER_MENU;
?>

<p>You can now export links to a CSV file.</p>
<ul>
    <li><code>Broken Links Only</code> will export your <a href="<?php echo esc_url( $MENU->get_plugin_page( 'results' ) ); ?>">Results</a>.</li>
    <li><code>Broken Links + Cached Good Links</code> will export your <a href="<?php echo esc_url( $MENU->get_plugin_page( 'results' ) ); ?>">Results</a> <em>AND</em> your cached good links if you have enabled caching in <a href="<?php echo esc_url( $MENU->get_plugin_page( 'settings' ) ); ?>">Settings</a>.</li>
    <li><code>All Links Only</code> will attempt to extract all links on your site and include menu items from your header and footer first. Make sure you <a href="<?php echo esc_url( $MENU->get_plugin_page( 'omit-pages' ) ); ?>">omit any pages that redirect</a> or it will cause issues.</li>
</ul>
<br>

<?php
$buttons = [ 
    'broken' => __( 'Broken Links Only', 'broken-link-notifier' ),
    'cached' => __( 'Broken Links + Cached Good Links', 'broken-link-notifier' ),
    'links'  => __( 'All Links Only (Without Checking Statuses)', 'broken-link-notifier' )
];
foreach ( $buttons as $key => $label ) {
    $url = add_query_arg( [
        'export'   => $key,
        '_wpnonce' => wp_create_nonce( 'blnotifier_export_nonce' )
    ] );
    ?>
    <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="export-button button button-primary" style="margin-right: 10px;"><?php echo esc_html( $label ); ?></a>
    <?php
}
?>