<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// ID
if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_link_search' ) &&
     isset( $_GET[ 'search' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) ) ) {
    $s = sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) );
} else {
    $s = '';
}

// Tab
$tab = (new BLNOTIFIER_HELPERS)->get_tab();
?>

<style>
#url-search-input {
    width: 600px;
}
label[for="url-search-input"] h2 {
    margin-bottom: 0;
}
.page-actions {
    float: right;
    margin-top: -30px;
    margin-bottom: 10px;
}
table.page-scan th.post_title {
    width: auto;
}
table.page-scan th.post_status,
table.page-scan th.post_type,
table.page-scan th.actions {
    width: 150px;
}
table.page-scan tr td a {
    text-decoration: underline;
}
</style>

<div class="url-search-bar">
    <form method="get" action="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( $tab ) ); ?>">
        <input type="hidden" name="_wpnonce" value="<?php echo sanitize_key( wp_create_nonce( 'blnotifier_link_search' ) ); ?>">
        <label for="url-search-input"><h2>Enter a URL</h2></label><br>
        <input type="hidden" name="page" value="<?php echo esc_html( BLNOTIFIER_TEXTDOMAIN ); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
        <input type="text" name="search" id="url-search-input" value="<?php echo esc_html( $s ); ?>" style="height: 2.5em;">
        <input type="submit" value="Search Now" id="url-search-button" class="button button-primary" style="margin-left: 5px;"/>
    </form>
</div>

<?php
// Results
if ( $s != '' ) {

    // The query
    global $wpdb;
    $query = $wpdb->prepare("
        SELECT ID, post_title, post_status, post_type
        FROM $wpdb->posts 
        WHERE post_content LIKE %s
    ", '%' . $wpdb->esc_like( $s ) . '%');

    $posts = $wpdb->get_results( $query ); // phpcs:ignore 

    $post_statuses = [
        'publish' => 'Published',
        'draft'   => 'Draft',
        'pending' => 'Pending Review',
        'private' => 'Private',
        'trash'   => 'Trash'
    ];
    
    $post_types = get_post_types( [], 'objects' );
    ?>

    <br><br><br>
    <h2>Search Results for "<?php echo wp_kses_post( $s ); ?>"</h2>

    <?php
    // If found
    if ( $posts ) {
        ?>
        <table class="page-scan wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th class="post_title">Post/Page Title</th>
                    <th class="post_status">Status</th>
                    <th class="post_type">Post Type</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $posts as $post ) {
                    $post_type = $post->post_type;
                    if ( $post_type == 'revision' ) {
                        continue;
                    }

                    $post_status = $post->post_status;

                    $post_status_label = isset( $post_statuses[ $post_status ] ) ? $post_statuses[ $post_status ] : $post_status;
                    $post_type_label = isset( $post_types[ $post_type ] ) ? $post_types[ $post_type ]->labels->singular_name : $post_type;
                    
                    ?>
                    <tr class="post-row">
                        <td class="post_title"><?php echo esc_html( $post->post_title ); ?></td>
                        <td class="post_status"><?php echo esc_html( $post_status_label ); ?></td>
                        <td class="post_type"><?php echo esc_html( $post_type_label ); ?></td>
                        <td class="actions"><a href="<?php echo esc_url( add_query_arg( 'blink', $s, get_the_permalink( $post->ID ) ) ); ?>" target="_blank">Show Me</a></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="post_title">Post/Page Title</th>
                    <th class="post_status">Status</th>
                    <th class="post_type">Post Type</th>
                    <th class="actions">Actions</th>
                </tr>
            </tfoot>
        </table>
        <?php

    // Not found
    } else {
        ?>
        <em>Link not found. Please try again.</em>
        <?php
    }
}
?>