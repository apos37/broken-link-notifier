<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// ID
if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_link_search' ) &&
     isset( $_GET[ 'search' ] ) && !empty( wp_unslash( $_GET[ 'search' ] ) ) ) {
    $s = filter_var( $_GET[ 'search' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
} else {
    $s = '';
}

// Tab
$tab = (new BLNOTIFIER_HELPERS)->get_tab();
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">
        <div class="url-search-bar">
            <form method="get" action="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( $tab ) ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo sanitize_key( wp_create_nonce( 'blnotifier_link_search' ) ); ?>">
                <label for="link-search-input"><h2><?php echo esc_html__( 'Enter a Link URL to Find Pages it Appears On', 'broken-link-notifier' ); ?></h2></label><br>
                <input type="hidden" name="page" value="<?php echo esc_html( BLNOTIFIER_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
                <div id="bln-link-autocomplete-wrap">
                    <input type="text" name="search" id="link-search-input" value="<?php echo esc_html( $s ); ?>">
                    <div id="bln-link-suggestions"></div>
                </div>
                <input type="submit" value="<?php echo esc_attr__( 'Search Now', 'broken-link-notifier' ); ?>" id="url-search-button" class="blnotifier-button" style="margin-left: 5px;"/>
            </form>
        </div>

        <?php
        // Results
        if ( $s != '' ) {

            global $wpdb;

            // Normalize the search string
            $s_decoded  = rawurldecode( $s );
            $s_trimmed  = untrailingslashit( $s_decoded );
            $s_trailing = trailingslashit( $s_trimmed );
            $s_encoded  = rawurlencode( $s_trimmed );

            // Build the query with multiple LIKE conditions
            $query = $wpdb->prepare("
                SELECT ID, post_title, post_status, post_type
                FROM $wpdb->posts 
                WHERE post_content LIKE %s
                   OR post_content LIKE %s
                   OR post_content LIKE %s
            ",
                '%' . $wpdb->esc_like( $s_trimmed ) . '%',
                '%' . $wpdb->esc_like( $s_trailing ) . '%',
                '%' . $wpdb->esc_like( $s_encoded ) . '%'
            );

            $posts = $wpdb->get_results( $query ); // phpcs:ignore

            $post_statuses = [
                'publish' => __( 'Published', 'broken-link-notifier' ),
                'draft'   => __( 'Draft', 'broken-link-notifier' ),
                'pending' => __( 'Pending Review', 'broken-link-notifier' ),
                'private' => __( 'Private', 'broken-link-notifier' ),
                'trash'   => __( 'Trash', 'broken-link-notifier' )
            ];

            $post_types = get_post_types( [], 'objects' );
            ?>

            <br><br>
            <h2><?php echo esc_html__( 'Search Results for', 'broken-link-notifier' ); ?> "<?php echo wp_kses_post( $s ); ?>"</h2>

            <?php
            // If found
            if ( $posts ) {
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
                                <td class="actions"><a href="<?php echo esc_url( add_query_arg( 'blink', $s, get_the_permalink( $post->ID ) ) ); ?>" target="_blank"><?php echo esc_html__( 'Show Me', 'broken-link-notifier' ); ?></a></td>
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