<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// ID
if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_link_search' ) &&
     isset( $_GET[ 'search' ] ) && !empty( wp_unslash( $_GET[ 'search' ] ) ) ) {
    $blnotifier_s = sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) );
} else {
    $blnotifier_s = '';
}

// Tab
$blnotifier_tab = (new BLNOTIFIER_HELPERS)->get_tab();
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">
        <div class="url-search-bar">
            <form method="get" action="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( $blnotifier_tab ) ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo sanitize_key( wp_create_nonce( 'blnotifier_link_search' ) ); ?>">
                <label for="link-search-input"><h2><?php echo esc_html__( 'Enter a Link URL to Find Pages it Appears On', 'broken-link-notifier' ); ?></h2></label><br>
                <input type="hidden" name="page" value="<?php echo esc_html( BLNOTIFIER_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $blnotifier_tab ); ?>">
                <div id="bln-link-autocomplete-wrap">
                    <input type="text" name="search" id="link-search-input" value="<?php echo esc_html( $blnotifier_s ); ?>">
                    <div id="bln-link-suggestions"></div>
                </div>
                <input type="submit" value="<?php echo esc_attr__( 'Search Now', 'broken-link-notifier' ); ?>" id="url-search-button" class="blnotifier-button" style="margin-left: 5px;"/>
            </form>
        </div>

        <?php
        // Results
        if ( $blnotifier_s != '' ) {

            global $wpdb;

            // Normalize the search string
            $blnotifier_s_decoded  = rawurldecode( $blnotifier_s );
            $blnotifier_s_trimmed  = untrailingslashit( $blnotifier_s_decoded );
            $blnotifier_s_trailing = trailingslashit( $blnotifier_s_trimmed );
            $blnotifier_s_encoded  = rawurlencode( $blnotifier_s_trimmed );

            // Build the query with multiple LIKE conditions
            $blnotifier_query = $wpdb->prepare("
                SELECT ID, post_title, post_status, post_type
                FROM $wpdb->posts 
                WHERE post_content LIKE %s
                   OR post_content LIKE %s
                   OR post_content LIKE %s
            ",
                '%' . $wpdb->esc_like( $blnotifier_s_trimmed ) . '%',
                '%' . $wpdb->esc_like( $blnotifier_s_trailing ) . '%',
                '%' . $wpdb->esc_like( $blnotifier_s_encoded ) . '%'
            );

            $blnotifier_posts = $wpdb->get_results( $blnotifier_query ); // phpcs:ignore

            $blnotifier_post_statuses = [
                'publish' => __( 'Published', 'broken-link-notifier' ),
                'draft'   => __( 'Draft', 'broken-link-notifier' ),
                'pending' => __( 'Pending Review', 'broken-link-notifier' ),
                'private' => __( 'Private', 'broken-link-notifier' ),
                'trash'   => __( 'Trash', 'broken-link-notifier' )
            ];

            $blnotifier_post_types = get_post_types( [], 'objects' );
            ?>

            <br><br>
            <h2><?php echo esc_html__( 'Search Results for', 'broken-link-notifier' ); ?> "<?php echo wp_kses_post( $blnotifier_s ); ?>"</h2>

            <?php
            // If found
            if ( $blnotifier_posts ) {
                ?>
                <table class="page-scan wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th class="post_title"><?php echo esc_html__( 'Post/Page Title', 'broken-link-notifier' ); ?></th>
                            <th class="post_status"><?php echo esc_html__( 'Status', 'broken-link-notifier' ); ?></th>
                            <th class="post_type"><?php echo esc_html__( 'Post Type', 'broken-link-notifier' ); ?></th>
                            <th class="actions"><?php echo esc_html__( 'Actions', 'broken-link-notifier' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ( $blnotifier_posts as $blnotifier_post ) {
                            $blnotifier_post_type = $blnotifier_post->post_type;
                            if ( $blnotifier_post_type == 'revision' ) {
                                continue;
                            }

                            $blnotifier_post_status = $blnotifier_post->post_status;

                            $blnotifier_post_status_label = isset( $blnotifier_post_statuses[ $blnotifier_post_status ] ) ? $blnotifier_post_statuses[ $blnotifier_post_status ] : $blnotifier_post_status;
                            $blnotifier_post_type_label = isset( $blnotifier_post_types[ $blnotifier_post_type ] ) ? $blnotifier_post_types[ $blnotifier_post_type ]->labels->singular_name : $blnotifier_post_type;
                            
                            ?>
                            <tr class="post-row">
                                <td class="post_title"><?php echo esc_html( $blnotifier_post->post_title ); ?></td>
                                <td class="post_status"><?php echo esc_html( $blnotifier_post_status_label ); ?></td>
                                <td class="post_type"><?php echo esc_html( $blnotifier_post_type_label ); ?></td>
                                <td class="actions"><a href="<?php echo esc_url( add_query_arg( 'blink', $blnotifier_s, get_the_permalink( $blnotifier_post->ID ) ) ); ?>" target="_blank"><?php echo esc_html__( 'Show Me', 'broken-link-notifier' ); ?></a></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="post_title"><?php echo esc_html__( 'Post/Page Title', 'broken-link-notifier' ); ?></th>
                            <th class="post_status"><?php echo esc_html__( 'Status', 'broken-link-notifier' ); ?></th>
                            <th class="post_type"><?php echo esc_html__( 'Post Type', 'broken-link-notifier' ); ?></th>
                            <th class="actions"><?php echo esc_html__( 'Actions', 'broken-link-notifier' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <?php

            // Not found
            } else {
                ?>
                <em><?php echo esc_html__( 'Link not found. Please try again.', 'broken-link-notifier' ); ?></em>
                <?php
            }
        }
        ?>
    </div>
</div>