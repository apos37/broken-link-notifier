<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// ID
if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_scan_single' ) &&
     isset( $_GET[ 'scan' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'scan' ] ) ) ) {
    $s = sanitize_text_field( wp_unslash( $_GET[ 'scan' ] ) );
} else {
    $s = '';
}

// Which field was actually used to submit
$scan_source = isset( $_GET[ 'scan_source' ] ) ? sanitize_key( wp_unslash( $_GET[ 'scan_source' ] ) ) : 'text';

// Restore the picker's selection server-side, only when the picker was used
$selected_post_type = '';
$selected_post_id = 0;

if ( $scan_source === 'picker' && $s !== '' && is_numeric( $s ) ) {
    $selected_post_id = absint( $s );
    $selected_post_type = get_post_type( $selected_post_id ) ?: '';
}

// The text field only ever shows a value when the text-search path was actually used
$s_display = ( $scan_source === 'text' ) ? $s : '';
if ( $scan_source === 'text' && $s !== '' && is_numeric( $s ) ) {
    $existing_title = get_the_title( $s );
    if ( $existing_title ) {
        $s_display = $existing_title;
    }
}

// Tab
$tab = (new BLNOTIFIER_HELPERS)->get_tab();
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">
        <div class="url-search-bar">
            <form method="get" action="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( $tab ) ); ?>" autocomplete="off">
                <input type="hidden" name="_wpnonce" value="<?php echo sanitize_key( wp_create_nonce( 'blnotifier_scan_single' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_html( BLNOTIFIER_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
                <input type="hidden" name="scan_source" id="scan-source-field" value="text">

                <div class="bln-scan-option">
                    <label class="bln-scan-option-label"><?php echo esc_html__( 'Browse for a Page', 'broken-link-notifier' ); ?></label>
                    <div id="bln-scan-picker">
                        <select id="bln-scan-picker-post-type" class="blnotifier-select-field">
                            <option value=""><?php echo esc_html__( 'Choose a Post Type...', 'broken-link-notifier' ); ?></option>
                            <?php foreach ( (new BLNOTIFIER_OMITS)->get_scannable_post_type_choices() as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_post_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="bln-scan-picker-post" class="blnotifier-select-field" <?php echo !$selected_post_type ? 'disabled' : ''; ?>>
                            <?php if ( $selected_post_type ) : ?>
                                <?php
                                $picker_posts = get_posts( [
                                    'post_type'      => $selected_post_type,
                                    'post_status'    => [ 'publish', 'private', 'draft', 'pending' ],
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'fields'         => 'ids',
                                ] );
                                ?>
                                <option value=""><?php echo esc_html__( 'Choose a Page...', 'broken-link-notifier' ); ?></option>
                                <?php foreach ( $picker_posts as $picker_post_id ) : ?>
                                    <option value="<?php echo absint( $picker_post_id ); ?>" <?php selected( $selected_post_id, $picker_post_id ); ?>><?php echo esc_html( get_the_title( $picker_post_id ) ?: esc_html__( '(no title)', 'broken-link-notifier' ) ); ?></option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value=""><?php echo esc_html__( 'Choose a Post Type First...', 'broken-link-notifier' ); ?></option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" id="bln-scan-picker-button" class="blnotifier-button"><?php echo esc_html__( 'Scan Now', 'broken-link-notifier' ); ?></button>
                    </div>
                </div>

                <div class="bln-scan-divider"><span>OR</span></div>

                <div class="bln-scan-option">
                    <label for="url-search-input" class="bln-scan-option-label"><?php echo esc_html__( 'Enter a URL, Post ID, or Title', 'broken-link-notifier' ); ?></label>
                    <div id="bln-scan-search-wrap">
                        <input type="text" id="url-search-input" autocomplete="off" value="<?php echo esc_attr( $s_display ); ?>">
                        <input type="hidden" name="scan" id="url-search-value" value="<?php echo esc_attr( $s ); ?>">
                        <div id="bln-scan-suggestions"></div>
                        <button type="submit" id="url-search-button" class="blnotifier-button"><?php echo esc_html__( 'Scan Now', 'broken-link-notifier' ); ?></button>
                    </div>
                </div>
            </form>
        </div>

        <?php
        // Results
        if ( $s != '' ) {

            // Get the post id
            if ( is_numeric( $s ) ) {
                $post_id = $s;
                $permalink = get_the_permalink( $s );
            } else {
                $post_id = url_to_postid( $s );
                $permalink = $s;
            }

            // Scanning for
            if ( $post_title = get_the_title( $post_id ) ) {
                $permalink = add_query_arg( 'blinks', 'true', $permalink );
                $display_s = '<a href="'.$permalink.'" target="_blank">'.$post_title.'</a>';
                $found = true;
            } else {
                $display_s = $s;
                $found = false;
            }
            ?>
            <br><br><br>
            <h2><?php
            /* translators: %s: post title or URL being scanned. */
            printf( esc_html__( 'Content Scan Results for "%s"', 'broken-link-notifier' ), wp_kses_post( $display_s ) );
            ?></h2>
            <?php $remote_fetch_enabled = filter_var( get_option( 'blnotifier_remote_fetch_links' ), FILTER_VALIDATE_BOOLEAN ); ?>
            <?php if ( $remote_fetch_enabled ) : ?>
                <p><em><?php echo esc_html__( 'Links were also fetched from the live published page, in addition to its stored content.', 'broken-link-notifier' ); ?></em></p>
            <?php else : ?>
                <p><em><?php echo esc_html__( 'Does not include links in the <code>&#x3c;header&#x3e;</code> or <code>&#x3c;footer&#x3e;</code>. Also, remember that links will not include content if it is hidden behind conditional logic.', 'broken-link-notifier' ); ?></em></p>
            <?php endif; ?>
            <br><br>
            <?php
            // If found
            if ( $found ) {

                // HELPERS
                $HELPERS = new BLNOTIFIER_HELPERS;
                
                // Get the content
                $get_the_content = get_the_content( null, false, $post_id );

                // Redirects from shortcodes
                if ( strpos( $get_the_content, '[redirect_this_page') !== false ) {
                    ?>
                    <em><?php echo esc_html__( 'This page is only redirecting to another page. Try a different page.', 'broken-link-notifier' ); ?></em>
                    <?php

                // Search the content
                } else {

                    global $post;
                    $scanned_post = get_post( $post_id );
                    $original_post = $post;
                    if ( $scanned_post ) {
                        $post = $scanned_post;
                        setup_postdata( $post );
                    }

                    $content = apply_filters( 'the_content', $get_the_content );

                    if ( $scanned_post ) {
                        $post = $original_post;
                        wp_reset_postdata();
                    }

                    if ( $content ) {
                    
                    // Get the links
                    $links = $HELPERS->extract_links( $content );

                    // Merge in remotely fetched links, if enabled
                    if ( filter_var( get_option( 'blnotifier_remote_fetch_links' ), FILTER_VALIDATE_BOOLEAN ) ) {
                        $remote_links = $HELPERS->get_remote_page_links( $post_id );
                        $links = $HELPERS->merge_and_dedupe_links( $links, $remote_links );
                    }

                    // Did we find any
                    if ( !empty( $links ) ) {

                        // Edit buttons
                        $buttons = [
                            '<a class="blnotifier-button view" href="'.$permalink.'" target="_blank">'.esc_html__( 'View', 'broken-link-notifier' ).'</a>',
                            '<a class="blnotifier-button edit" href="'.add_query_arg( [ 'post' => $post_id, 'action' => 'edit' ], admin_url( 'post.php' ) ).'">'.esc_html__( 'Edit', 'broken-link-notifier' ).'</a>',
                        ];
                        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                            $buttons[] = '<a class="blnotifier-button edit-in-cornerstone" href="'.home_url( '/cornerstone/edit/'.$post_id ).'">'.esc_html__( 'Edit in Cornerstone', 'broken-link-notifier' ).'</a>';
                        }
                        ?>
                        <div class="above-table-cont">
                            <div class="page-count">
                                <strong><?php echo esc_html__( 'Total Links Found:', 'broken-link-notifier' ); ?></strong> <?php echo absint( count( $links ) ); ?>
                            </div>
                            <div class="page-actions">
                                <?php echo wp_kses_post( implode( ' ', $buttons ) ); ?>
                            </div>
                        </div>
                        <?php
                        // Table
                        ?>
                        <table class="page-scan wp-list-table widefat fixed striped table-view-list">
                            <thead>
                                <tr>
                                    <th class="link"><?php echo esc_html__( 'Link', 'broken-link-notifier' ); ?></th>
                                    <th class="title"><?php echo esc_html__( 'Title (if local)', 'broken-link-notifier' ); ?></th>
                                    <th class="status"><?php echo esc_html__( 'Status', 'broken-link-notifier' ); ?></th>
                                    <th class="code"><?php echo esc_html__( 'Code', 'broken-link-notifier' ); ?></th>
                                    <th class="message"><?php echo esc_html__( 'Message', 'broken-link-notifier' ); ?></th>
                                    <th class="speed"><?php echo esc_html__( 'Speed', 'broken-link-notifier' ); ?></th>
                                    <th class="actions"><?php echo esc_html__( 'Actions', 'broken-link-notifier' ); ?></th>
                                </tr>
                            </thead>
                        <?php
                        // Iter
                        foreach ( $links as $link ) {

                            // Include find
                            if ( $link != '' ) {
                                $incl_find = ' | <a href="'.add_query_arg( 'blink', $link, $s ).'" target="_blank">'.esc_html__( 'Find', 'broken-link-notifier' ).'</a>';
                            } else {
                                $incl_find = '';
                            }

                            // Link it
                            if ( str_starts_with( $link, '/' ) && !str_starts_with( $link, '//' ) ) {
                                $check_link = home_url().$link;
                            } else {
                                $check_link = $link;
                            }
                            if ( filter_var( $check_link, FILTER_VALIDATE_URL ) && str_starts_with( $check_link, 'http' ) ) {
                                $link = '<a href="'.$link.'" target="_blank">'.$link.'</a>';
                            }

                            // The title
                            if ( $this_post_id = url_to_postid( $check_link ) ) {
                                $incl_title = get_the_title( $this_post_id );
                            } else {
                                $incl_title = '';
                            }

                            // The row
                            ?>
                            <tr class="link-row pending" data-link="<?php echo esc_html( $check_link ); ?>">
                                <td class="link"><?php echo wp_kses_post( $link ); ?></td>
                                <td><?php echo esc_html( $incl_title ); ?></td>
                                <td class="type dotdotdot"><em><?php echo esc_html__( 'Pending', 'broken-link-notifier' ); ?></em></td>
                                <td class="code"></td>
                                <td class="text"></td>
                                <td class="speed"></td>
                                <td class="actions"><a class="omit-link" href="#">Omit</a><?php echo wp_kses_post( $incl_find ); ?></td>
                            </tr>
                            <?php
                        }
                        // Table footer
                        ?>
                            <tfoot>
                                <tr>
                                    <th><?php echo esc_html__( 'Link', 'broken-link-notifier' ); ?></th>
                                    <th><?php echo esc_html__( 'Title (if local)', 'broken-link-notifier' ); ?></th>
                                    <th><?php echo esc_html__( 'Status', 'broken-link-notifier' ); ?></th>
                                    <th><?php echo esc_html__( 'Code', 'broken-link-notifier' ); ?></th>
                                    <th><?php echo esc_html__( 'Message', 'broken-link-notifier' ); ?></th>
                                    <th><?php echo esc_html__( 'Speed', 'broken-link-notifier' ); ?></th>
                                    <th><?php echo esc_html__( 'Actions', 'broken-link-notifier' ); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        <?php

                    // No links on page 
                    } else {
                        
                        // If cornerstone
                        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                            $incl_solution = sprintf(
                                /* translators: %s: URL for editing the page in Cornerstone. */
                                __( ' If you know there are links on the page, try <a href="%s" target="_blank">editing the page in Cornerstone</a> and resaving it. Sometimes the content is saved correctly after editing it outside of Cornerstone, so resaving in Cornerstone helps repopulate the data where we can read the links.', 'broken-link-notifier' ),
                                esc_url( home_url( '/cornerstone/edit/' . $post_id ) )
                            );
                        } else {
                            $incl_solution = '';
                        }
                        ?>
                        <em><strong><?php echo esc_html__( 'No links found.', 'broken-link-notifier' ); ?></strong><?php echo esc_html( $incl_solution ); ?></em>
                        <?php
                    }

                    // Content missing
                    } else {
                        ?>
                        <em><?php echo esc_html__( 'Content not found.', 'broken-link-notifier' ); ?></em>
                        <?php
                    }
                }

            // Not found
            } else {
                ?>
                <em><?php echo esc_html__( 'Page not found. You can only scan your site\'s posts, pages, and custom post types here. Please try again.', 'broken-link-notifier' ); ?></em>
                <?php
            }
        }
        ?>
    </div>
</div>