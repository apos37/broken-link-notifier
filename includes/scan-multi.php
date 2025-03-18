<?php
/**
 * Multi-Scan class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
add_action( 'init', function() {
    (new BLNOTIFIER_FULL_SCAN)->init();
} );


/**
 * Main plugin class.
 */
class BLNOTIFIER_FULL_SCAN {

    /**
	 * Load on init
	 */
	public function init() {

        // Add a run scan button at top of WP List Tables
        add_action( 'admin_head-edit.php', [ $this, 'run_scan_button' ] );

        // Remove Edit and Quick Edit links, and add ignore link
        add_action( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
        add_action( 'page_row_actions', [ $this, 'row_actions' ], 10, 2 );

        // Add columns for each post type
        foreach ( (new BLNOTIFIER_HELPERS)->get_allowed_multiscan_post_types() as $post_type ) {
            add_filter( 'manage_'.$post_type.'_posts_columns', [ $this, 'column' ] );
            add_action( 'manage_'.$post_type.'_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
        }

        // Add css
        add_action( 'admin_head', [ $this, 'css' ] );
        
	} // End init()


    /**
     * Should we do stuff?
     *
     * @return boolean
     */
    public function do_stuff() {
        return ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_blinks' ) && 
                 isset( $_GET[ 'blinks' ] ) && sanitize_key( $_GET[ 'blinks' ] ) === 'true' ) ? true : false;
    } // End do_stuff()


    /**
     * Add a run scan button to the top of all post types
     *
     * @return void
     */
    public function run_scan_button() {
        global $current_screen;
        $post_types = get_option( 'blnotifier_post_types' );
        $post_types = !empty( $post_types ) ? array_keys( $post_types ) : [ 'post', 'page' ];
        if ( !in_array( $current_screen->post_type, $post_types ) ) {
            return;
        }
        $nonce = wp_create_nonce( 'blnotifier_blinks' );
        ?>
        <script>
            jQuery( $ => { 
                const currentURL = window.location.href;
                var btnURL;
                var btnText;
                if ( currentURL.includes( 'blinks=true' ) && currentURL.includes( '_wpnonce=<?php echo esc_html( $nonce ); ?>' ) ) {
                    btnURL = '<?php echo esc_url( remove_query_arg( [ 'blinks', '_wpnonce' ] ) ); ?>';
                    btnText = 'Stop Scanning';
                } else {
                    btnURL = '<?php echo esc_url( add_query_arg( [ 'blinks' => 'true', '_wpnonce' => $nonce ] ) ); ?>';
                    btnText = 'Scan for Broken Links';
                }
                $( '.wrap > a.page-title-action' ).after( `<a id="bln-run-scan" href="${btnURL}" class="page-title-action" style="margin-left: 10px;"><span class="text">${btnText}</span><span class="done"></span></a>` );
            } )
        </script>
        <?php
    } // End run_scan_button()


    /**
     * Action links
     *
     * @param array $actions
     * @param object $post
     * @return array
     */
    public function row_actions( $actions, $post ) {
        // The link
        $link = get_the_permalink( $post );

        // Post types
        $post_types = (new BLNOTIFIER_HELPERS)->get_allowed_multiscan_post_types();

        // Add page scan to all post types
        if ( in_array( $post->post_type, $post_types ) ) {
            $nonce = wp_create_nonce( 'blnotifier_scan_single' );
            $actions[ 'scan' ] = '<a class="scan-page" href="'.(new BLNOTIFIER_MENU)->get_plugin_page( 'scan-single' ).'&scan='.$link.'&_wpnonce='. $nonce.'" target="_blank">Scan for Broken Links</a>';
        }

        // Only when scanning
        if ( $this->do_stuff() ) {
            if ( in_array( $post->post_type, $post_types ) ) {
                if ( !(new BLNOTIFIER_OMITS)->is_omitted( $link, 'pages' ) ) {
                    $actions[ 'omit-future' ] = '<a class="omit-page" href="#" data-link="'.$link.'" data-post-id="'.$post->ID.'">Omit from Scans</a>';
                }
            }
        }
        
        // Return all actions
        return $actions;
    } // End row_actions()


    /**
     * Add the column
     *
     * @param array $columns
     * @return array
     */
    public function column( $columns ) {
        if ( $this->do_stuff() ) {
            $columns[ 'blinks' ] = __( 'Broken Links', 'broken-link-notifier' );
        }
        return $columns;
    } // End column()


    /**
     * Column content
     *
     * @param string $column
     * @param int $post_id
     * @return void
     */
    public function column_content( $column, $post_id ) {
        // Initiate helpers class
        $HELPERS = new BLNOTIFIER_HELPERS;

        // Only include column if we have it in our query string
        if ( $this->do_stuff() && 'blinks' === $column ) {

            // Get the permalink
            $permalink = get_the_permalink( $post_id );

            // Skip not published
            $post_status = get_post_status( $post_id );
            if ( $post_status != 'publish' && $post_status != 'private' ) {
                $results = '<em>Skipping - not published</em>';

            // Skip posts page
            } elseif ( $post_id == get_option( 'page_for_posts' ) ) {
                $results = '<em>Skipping Posts Archive Page since it will never have broken links</em>';

            // Skip omitted pages
            } elseif ( (new BLNOTIFIER_OMITS)->is_omitted( $permalink, 'pages' ) ) {
                $results = '<em>Omitted</em>';

            // Otherwise we're good to go.
            } else {

                // Get the post content
                $get_the_content = get_the_content( null, false, $post_id );

                // Skip if redirecting page using [redirect_this_page] shortcode
                if ( strpos( $get_the_content, '[redirect_this_page') !== false ) {

                    // Skip for redirecting
                    $results = '<em>Skipping since this page is only redirecting to another page</em>';

                // Search the content
                // } elseif ( $content = apply_filters( 'the_content', $get_the_content ) ) {
                } elseif ( $get_the_content ) {

                    // Prevent any redirects
                    add_filter( 'wp_redirect', '__return_false', 1 );
                    add_filter( 'wp_safe_redirect', '__return_false', 1 );

                    // Start output buffering to suppress unexpected output
                    ob_start();

                    $redirect_detected = false;

                    try {
                        // Process shortcodes and expand them in the content
                        $content = apply_filters( 'the_content', $get_the_content );
                    } catch ( Exception $e ) {
                        error_log( 'Error processing shortcodes: ' . $e->getMessage() );
                        $redirect_detected = true;
                    }

                    // Clear any unexpected output
                    ob_end_clean();

                    // After processing the content, remove the filters to restore redirect functionality
                    remove_filter( 'wp_redirect', '__return_false', 1 );
                    remove_filter( 'wp_safe_redirect', '__return_false', 1 );

                    // Handle redirects
                    if ( $redirect_detected ) {
                        $results = '<em>This page redirects, skipping...</em>';

                    // Or else extract the links
                    } else {

                        // Extract the links
                        $links = $HELPERS->extract_links( $content );

                        // Display the number of broken links found
                        if ( !empty( $links ) ) {

                            // Count
                            $count_links = count( $links );

                            // Start container
                            $results = '<div id="bln-results-'.$post_id.'" class="bln-scan-results">
                                <span class="progress dotdotdot"><em>Pending</em></span>';

                                // HELPERS
                                $HELPERS = new BLNOTIFIER_HELPERS;

                                // Add the counts
                                $results .= '<div id="bln-counts-'.$post_id.'" class="bln-count-cont">
                                    <span class="count-links"><strong>'.$count_links.'</strong> link'.$HELPERS->include_s( $count_links ).' found</span>
                                    <span class="count-broken-links"><strong>0</strong> broken link(s) found</span>
                                    <span class="count-warning-links"><strong>0</strong> warning link(s) found</span>
                                    <span class="count-error-links"><strong>0</strong> error(s) occured</span>
                                    <div class="time-loaded">Results generated in <strong><span class="timing">0</span> seconds</strong></div>
                                </div>';

                            // End container
                            $results .= '</div>';

                            // Start a list
                            $results .= '<ul id="bln-links-'.$post_id.'" class="bln-links" data-post-id="'.absint( $post_id ).'" data-total-count="'.$count_links.'">';

                                // For each link...
                                foreach ( $links as $link ) {

                                    // Increase count for link
                                    $count_links++;

                                    // Encode the link
                                    $page_url = urlencode( $link );

                                    // If it is broken, then display this with JS
                                    $results .= '<li class="link" data-link="'.$link.'" data-post-id="'.$post_id.'"><strong><a class="url" href="'.$link.'" target="_blank">'.$link.'</a></strong><div class="status"></div><div class="actions"><a class="omit-link" href="#">Omit</a> | <a href="'.$permalink.'?blink='.$page_url.'" target="_blank">Find On Page</a></div></li>';
                                }

                            // End the list
                            $results .= '</ul>';

                        } else {
                            $results = '<em>No links found</em>';
                        }
                    }
                    
                // No content
                } else {
                    $results = '<em>No content found</em>';
                }
            }

            // Return the results
            echo '<div id="bln-'.absint( $post_id ).'" class="bln-cont">
                '.wp_kses_post( $results ).'
            </div>';
        }
    } // End column_content()

    
    /**
     * Adjust the width of the admin column
     *
     * @return void
     */
    public function css() {
        // Only add it if the query string exists
        if ( $this->do_stuff() ) {
            echo '<style type="text/css">
            .bln-count-cont {
                margin: 30px 0px;
                background: white;
                border: 1px solid #ccc;
                border-radius: 5px;
                width: 300px;
                padding: 20px;
            }
            .count-posts,
            .count-links,
            .count-broken-links,
            .count-warning-links,
            .count-error-links,
            .time-loaded,
            .blinks-notice {
                display: block;
                padding: 2px 5px;
            }
            .bln-links {
                list-style: auto;
            }
            .bln-links .link {
                display: none;
                margin-left: 20px;
                margin-bottom: 20px;
            }
            .bln-links .link.omitted {
                opacity: .5;
            }
            .bln-links .link.omitted a:first-of-type {
                text-decoration: line-through;
            }
            .bln-links .link .actions {
                display: none;
                margin-top: 0.3rem;
            }
            .count-broken-links.found,
            .link.broken .status .type {
                background: red;
                color: white;
            }
            .count-warning-links.found,
            .link.warning .status .type {
                background: yellow;
            }
            .count-error-links.found,
            .link.error .status .type {
                background: black;
                color: white;
            }
            .link .status {
                margin-top: 7px;
            }
            .link .status .type {
                padding: 1px 10px;
                font-weight: bold;
                width: 100px;
                text-align: center;
                text-transform: uppercase;
                box-shadow: 0 2px 4px 0 rgba(7, 36, 86, 0.5);
                border: 1px solid rgba(7, 36, 86, 0.075);
                border-radius: 10px;
            }
            .dotdotdot:after {
                display: inline-block;
                animation: dotty steps(1,end) 1s infinite;
                content: "";
            }
            @keyframes dotty {
                0%   { content: ""; }
                25%  { content: "."; }
                50%  { content: ".."; }
                75%  { content: "..."; }
                100% { content: ""; }
            }
            </style>';
        }
    } // End css()
}