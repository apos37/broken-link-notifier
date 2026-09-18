<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// ID
if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_scan_single' ) &&
     isset( $_GET[ 'scan' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'scan' ] ) ) ) {
    $blnotifier_s = sanitize_text_field( wp_unslash( $_GET[ 'scan' ] ) );
} else {
    $blnotifier_s = '';
}

// Which field was actually used to submit
$blnotifier_scan_source = isset( $_GET[ 'scan_source' ] ) ? sanitize_key( wp_unslash( $_GET[ 'scan_source' ] ) ) : 'text';

// Restore the picker's selection server-side, only when the picker was used
$blnotifier_selected_post_type = '';
$blnotifier_selected_post_id = 0;

if ( $blnotifier_scan_source === 'picker' && $blnotifier_s !== '' && is_numeric( $blnotifier_s ) ) {
    $blnotifier_selected_post_id = absint( $blnotifier_s );
    $blnotifier_selected_post_type = get_post_type( $blnotifier_selected_post_id ) ?: '';
}

// The text field only ever shows a value when the text-search path was actually used
$blnotifier_s_display = ( $blnotifier_scan_source === 'text' ) ? $blnotifier_s : '';
if ( $blnotifier_scan_source === 'text' && $blnotifier_s !== '' && is_numeric( $blnotifier_s ) ) {
    $blnotifier_existing_title = get_the_title( $blnotifier_s );
    if ( $blnotifier_existing_title ) {
        $blnotifier_s_display = $blnotifier_existing_title;
    }
}

// Tab
$blnotifier_tab = (new BLNOTIFIER_HELPERS)->get_tab();
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">
        <div class="url-search-bar">
            <form method="get" action="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( $blnotifier_tab ) ); ?>" autocomplete="off">
                <input type="hidden" name="_wpnonce" value="<?php echo sanitize_key( wp_create_nonce( 'blnotifier_scan_single' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_html( BLNOTIFIER_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $blnotifier_tab ); ?>">
                <input type="hidden" name="scan_source" id="scan-source-field" value="text">

                <div class="bln-scan-option">
                    <label class="bln-scan-option-label"><?php echo esc_html__( 'Browse for a Page', 'broken-link-notifier' ); ?></label>
                    <div id="bln-scan-picker">
                        <select id="bln-scan-picker-post-type" class="blnotifier-select-field">
                            <option value=""><?php echo esc_html__( 'Choose a Post Type...', 'broken-link-notifier' ); ?></option>
                            <?php foreach ( (new BLNOTIFIER_OMITS)->get_scannable_post_type_choices() as $blnotifier_key => $blnotifier_label ) : ?>
                                <option value="<?php echo esc_attr( $blnotifier_key ); ?>" <?php selected( $blnotifier_selected_post_type, $blnotifier_key ); ?>><?php echo esc_html( $blnotifier_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="bln-scan-picker-post" class="blnotifier-select-field" <?php echo !$blnotifier_selected_post_type ? 'disabled' : ''; ?>>
                            <?php if ( $blnotifier_selected_post_type ) : ?>
                                <?php
                                $blnotifier_picker_posts = get_posts( [
                                    'post_type'      => $blnotifier_selected_post_type,
                                    'post_status'    => [ 'publish', 'private', 'draft', 'pending' ],
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'fields'         => 'ids',
                                ] );
                                ?>
                                <option value=""><?php echo esc_html__( 'Choose a Page...', 'broken-link-notifier' ); ?></option>
                                <?php foreach ( $blnotifier_picker_posts as $blnotifier_picker_post_id ) : ?>
                                    <option value="<?php echo absint( $blnotifier_picker_post_id ); ?>" <?php selected( $blnotifier_selected_post_id, $blnotifier_picker_post_id ); ?>><?php echo esc_html( get_the_title( $blnotifier_picker_post_id ) ?: esc_html__( '(no title)', 'broken-link-notifier' ) ); ?></option>
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
                        <input type="text" id="url-search-input" autocomplete="off" value="<?php echo esc_attr( $blnotifier_s_display ); ?>">
                        <input type="hidden" name="scan" id="url-search-value" value="<?php echo esc_attr( $blnotifier_s ); ?>">
                        <div id="bln-scan-suggestions"></div>
                        <button type="submit" id="url-search-button" class="blnotifier-button"><?php echo esc_html__( 'Scan Now', 'broken-link-notifier' ); ?></button>
                    </div>
                </div>
            </form>
        </div>

        <?php
        // Results
        if ( $blnotifier_s != '' ) {

            // Get the post id
            if ( is_numeric( $blnotifier_s ) ) {
                $blnotifier_post_id = $blnotifier_s;
                $blnotifier_permalink = get_the_permalink( $blnotifier_s );
            } else {
                $blnotifier_post_id = url_to_postid( $blnotifier_s );
                $blnotifier_permalink = $blnotifier_s;
            }

            // Scanning for
            if ( $blnotifier_post_title = get_the_title( $blnotifier_post_id ) ) {
                $blnotifier_permalink = add_query_arg( 'blinks', 'true', $blnotifier_permalink );
                $blnotifier_display_s = '<a href="'.$blnotifier_permalink.'" target="_blank">'.$blnotifier_post_title.'</a>';
                $blnotifier_found = true;
            } else {
                $blnotifier_display_s = $blnotifier_s;
                $blnotifier_found = false;
            }
            ?>
            <br><br><br>
            <h2><?php
            /* translators: %s: post title or URL being scanned. */
            printf( esc_html__( 'Content Scan Results for "%s"', 'broken-link-notifier' ), wp_kses_post( $blnotifier_display_s ) );
            ?></h2>
            <?php $blnotifier_remote_fetch_enabled = filter_var( get_option( 'blnotifier_remote_fetch_links' ), FILTER_VALIDATE_BOOLEAN ); ?>
            <?php if ( $blnotifier_remote_fetch_enabled ) : ?>
                <p><em><?php echo esc_html__( 'Links were also fetched from the live published page, in addition to its stored content.', 'broken-link-notifier' ); ?></em></p>
            <?php else : ?>
                <p><em><?php echo esc_html__( 'Does not include links in the <code>&#x3c;header&#x3e;</code> or <code>&#x3c;footer&#x3e;</code>. Also, remember that links will not include content if it is hidden behind conditional logic.', 'broken-link-notifier' ); ?></em></p>
            <?php endif; ?>
            <br><br>
            <?php
            // If found
            if ( $blnotifier_found ) {

                // HELPERS
                $blnotifier_HELPERS = new BLNOTIFIER_HELPERS;
                
                // Get the content
                $blnotifier_get_the_content = get_the_content( null, false, $blnotifier_post_id );

                // Redirects from shortcodes
                if ( strpos( $blnotifier_get_the_content, '[redirect_this_page') !== false ) {
                    ?>
                    <em><?php echo esc_html__( 'This page is only redirecting to another page. Try a different page.', 'broken-link-notifier' ); ?></em>
                    <?php

                // Search the content
                } else {

                    global $post;
                    $blnotifier_scanned_post = get_post( $blnotifier_post_id );
                    $blnotifier_original_post = $post;
                    if ( $blnotifier_scanned_post ) {
                        $post = $blnotifier_scanned_post;
                        setup_postdata( $post );
                    }

                    $blnotifier_content = apply_filters( 'the_content', $blnotifier_get_the_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- 'the_content' is WordPress core's own filter being invoked here, not a hook this plugin defines.

                    if ( $blnotifier_scanned_post ) {
                        $post = $blnotifier_original_post;
                        wp_reset_postdata();
                    }

                    if ( $blnotifier_content ) {
                    
                    // Get the links
                    $blnotifier_links = $blnotifier_HELPERS->extract_links( $blnotifier_content );

                    // Merge in remotely fetched links, if enabled
                    if ( filter_var( get_option( 'blnotifier_remote_fetch_links' ), FILTER_VALIDATE_BOOLEAN ) ) {
                        $blnotifier_remote_links = $blnotifier_HELPERS->get_remote_page_links( $blnotifier_post_id );
                        $blnotifier_links = $blnotifier_HELPERS->merge_and_dedupe_links( $blnotifier_links, $blnotifier_remote_links );
                    }

                    // Did we find any
                    if ( !empty( $blnotifier_links ) ) {

                        // Edit buttons
                        $blnotifier_buttons = [
                            '<a class="blnotifier-button view" href="'.$blnotifier_permalink.'" target="_blank">'.esc_html__( 'View', 'broken-link-notifier' ).'</a>',
                            '<a class="blnotifier-button edit" href="'.add_query_arg( [ 'post' => $blnotifier_post_id, 'action' => 'edit' ], admin_url( 'post.php' ) ).'">'.esc_html__( 'Edit', 'broken-link-notifier' ).'</a>',
                        ];
                        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                            $blnotifier_buttons[] = '<a class="blnotifier-button edit-in-cornerstone" href="'.home_url( '/cornerstone/edit/'.$blnotifier_post_id ).'">'.esc_html__( 'Edit in Cornerstone', 'broken-link-notifier' ).'</a>';
                        }
                        ?>
                        <div class="above-table-cont">
                            <div class="page-count">
                                <strong><?php echo esc_html__( 'Total Links Found:', 'broken-link-notifier' ); ?></strong> <?php echo absint( count( $blnotifier_links ) ); ?>
                            </div>
                            <div class="page-actions">
                                <?php echo wp_kses_post( implode( ' ', $blnotifier_buttons ) ); ?>
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
                        foreach ( $blnotifier_links as $blnotifier_link ) {

                            // Include find
                            if ( $blnotifier_link != '' ) {
                                $blnotifier_incl_find = ' | <a href="'.add_query_arg( 'blink', $blnotifier_link, $blnotifier_s ).'" target="_blank">'.esc_html__( 'Find', 'broken-link-notifier' ).'</a>';
                            } else {
                                $blnotifier_incl_find = '';
                            }

                            // Link it
                            if ( str_starts_with( $blnotifier_link, '/' ) && !str_starts_with( $blnotifier_link, '//' ) ) {
                                $blnotifier_check_link = home_url().$blnotifier_link;
                            } else {
                                $blnotifier_check_link = $blnotifier_link;
                            }
                            if ( filter_var( $blnotifier_check_link, FILTER_VALIDATE_URL ) && str_starts_with( $blnotifier_check_link, 'http' ) ) {
                                $blnotifier_link = '<a href="'.$blnotifier_link.'" target="_blank">'.$blnotifier_link.'</a>';
                            }

                            // The title
                            if ( $blnotifier_this_post_id = url_to_postid( $blnotifier_check_link ) ) {
                                $blnotifier_incl_title = get_the_title( $blnotifier_this_post_id );
                            } else {
                                $blnotifier_incl_title = '';
                            }

                            // The row
                            ?>
                            <tr class="link-row pending" data-link="<?php echo esc_html( $blnotifier_check_link ); ?>">
                                <td class="link"><?php echo wp_kses_post( $blnotifier_link ); ?></td>
                                <td><?php echo esc_html( $blnotifier_incl_title ); ?></td>
                                <td class="type dotdotdot"><em><?php echo esc_html__( 'Pending', 'broken-link-notifier' ); ?></em></td>
                                <td class="code"></td>
                                <td class="text"></td>
                                <td class="speed"></td>
                                <td class="actions"><a class="omit-link" href="#">Omit</a><?php echo wp_kses_post( $blnotifier_incl_find ); ?></td>
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
                            $blnotifier_incl_solution = sprintf(
                                /* translators: %s: URL for editing the page in Cornerstone. */
                                __( ' If you know there are links on the page, try <a href="%s" target="_blank">editing the page in Cornerstone</a> and resaving it. Sometimes the content is saved correctly after editing it outside of Cornerstone, so resaving in Cornerstone helps repopulate the data where we can read the links.', 'broken-link-notifier' ),
                                esc_url( home_url( '/cornerstone/edit/' . $blnotifier_post_id ) )
                            );
                        } else {
                            $blnotifier_incl_solution = '';
                        }
                        ?>
                        <em><strong><?php echo esc_html__( 'No links found.', 'broken-link-notifier' ); ?></strong><?php echo esc_html( $blnotifier_incl_solution ); ?></em>
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