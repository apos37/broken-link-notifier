<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

// ID
if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_scan_single' ) &&
     isset( $_GET[ 'scan' ] ) && sanitize_text_field( $_GET[ 'scan' ] ) ) {
    $s = sanitize_text_field( $_GET[ 'scan' ] );
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
table.page-scan th.status,
table.page-scan th.code,
table.page-scan th.speed,
table.page-scan th.actions {
    width: 100px;
}
table.page-scan th.message {
    width: 300px;
}
table.page-scan tr.broken {
    background: red;
    color: white;
    font-weight: 600;
}
table.page-scan tr.broken td,
table.page-scan tr.broken td a {
    color: white;
    font-weight: 600;
}
table.page-scan tr.warning {
    background: yellow;
    font-weight: 600;
}
table.page-scan tr.warning td {
    font-weight: 600;
}
table.page-scan tr td a {
    text-decoration: underline;
}
table.page-scan .link-row .type {
    text-transform: capitalize;
}
.pending {
    opacity: .5;
}
.omitted td:not(.type) {
    text-decoration: line-through;
}
.omitted td a {
    text-decoration: none;
}
.dotdotdot:after {
    display: inline-block;
    animation: dotty steps(1,end) 1s infinite;
    content: '';
}
@keyframes dotty {
    0%   { content: ''; }
    25%  { content: '.'; }
    50%  { content: '..'; }
    75%  { content: '...'; }
    100% { content: ''; }
}
table.page-scan .link-row .actions {
    display: none;
}
.more-info {
    display: inline-block;
    width: 14px;
    height: 14px;
    content: "\003F";
    background: #1770DB;
    color: white;
    border-radius: 50%;
    text-align: center;
    position: relative;
    margin-left: 10px;
    box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    cursor: pointer;
}
/* .more-info span {
    position: absolute;
    top: 50%;
    left: 50%;
} */
</style>

<div class="url-search-bar">
    <form method="get" action="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( $tab ) ); ?>">
        <input type="hidden" name="_wpnonce" value="<?php echo sanitize_key( wp_create_nonce( 'blnotifier_scan_single' ) ); ?>">
        <label for="url-search-input"><h2>Enter a URL or Post ID</h2></label><br>
        <input type="hidden" name="page" value="<?php echo esc_html( BLNOTIFIER_TEXTDOMAIN ); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
        <input type="text" name="scan" id="url-search-input" value="<?php echo esc_html( $s ); ?>" style="height: 2.5em;">
        <input type="submit" value="Scan Now" id="url-search-button" class="button button-primary" style="margin-left: 5px;"/>
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
    <h2>Content Scan Results for "<?php echo wp_kses_post( $display_s ); ?>"</h2>
    <p><em>Does not include links in the <code>&#x3c;header&#x3e;</code> or <code>&#x3c;footer&#x3e;</code>. Also, <strong>remember</strong> that links will not include content if it is hidden behind conditional logic.</strong></em></p>
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
            <em>This page is only redirecting to another page. Try a different page.</em>
            <?php

        // Search the content
        } elseif ( $content = apply_filters( 'the_content', $get_the_content ) ) {
            
            // Get the links
            $links = $HELPERS->extract_links( $content );

            // Did we find any
            if ( !empty( $links ) ) {

                // Edit buttons
                $buttons = [
                    '<a class="button button-secondary view" href="'.$permalink.'" target="_blank">View</a>',
                    '<a class="button button-secondary edit" href="'.add_query_arg( [ 'post' => $post_id, 'action' => 'edit' ], admin_url( 'post.php' ) ).'">Edit</a>',
                ];
                if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                    $buttons[] = '<a class="button button-secondary edit-in-cornerstone" href="'.home_url( '/cornerstone/edit/'.$post_id ).'">Edit in Cornerstone</a>';
                }
                ?>
                <div class="above-table-cont">
                    <div class="page-count">
                        <strong>Total Links Found:</strong> <?php echo absint( count( $links ) ); ?>
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
                            <th class="link">Link</th>
                            <th class="title">Title (if local)</th>
                            <th class="status">Status</th>
                            <th class="code">Code</th>
                            <th class="message">Message</th>
                            <th class="speed">Speed</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                <?php
                // Iter
                foreach ( $links as $link ) {

                    // Include find
                    if ( $link != '' ) {
                        $incl_find = ' | <a href="'.add_query_arg( 'blink', $link, $s ).'" target="_blank">Find</a>';
                    } else {
                        $incl_find = '';
                    }

                    // Link it
                    if ( str_starts_with( $link, '/' ) ) {
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
                        <td class="type dotdotdot"><em>Pending</em></td>
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
                            <th>Link</th>
                            <th>Title (if local)</th>
                            <th>Status</th>
                            <th>Code</th>
                            <th>Message</th>
                            <th>Speed</th>
                            <th>Actions</th>
                        </tr>
                    </tfoot>
                </table>
                <?php

            // No links on page 
            } else {
                
                // If cornerstone
                if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                    $incl_solution = ' If you know there are links on the page, try <a href="'.home_url( '/cornerstone/edit/'.$post_id ).'" target="_blank">editing the page in Cornerstone</a> and resaving it. Sometimes the content is saved correctly after editing it outside of Cornerstone, so resaving in Cornerstone helps repopulate the data where we can read the links.';
                } else {
                    $incl_solution = '';
                }
                ?>
                <em><strong>No links found.</strong><?php echo esc_html( $incl_solution ); ?></em>
                <?php
            }

        // Content missing
        } else {
            ?>
            <em>Content not found.</em>
            <?php
        }

    // Not found
    } else {
        ?>
        <em>Page not found. You can only scan your site's posts, pages, and custom post types here. Please try again.</em>
        <?php
    }
}
?>