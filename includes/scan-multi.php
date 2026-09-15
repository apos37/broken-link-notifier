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

        // Always enqueue our own tab page's CSS, regardless of whether legacy Multi-Scan is enabled
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_tab_page_assets' ] );

        // Legacy Multi-Scan is disabled by default in favor of Site Scan.
        // Enable it either via test mode, or for developers: add_filter( 'blnotifier_enable_legacy_multiscan', '__return_true' );
        $is_enabled = apply_filters( 'blnotifier_enable_legacy_multiscan', false ) || (new BLNOTIFIER_HELPERS)->is_test_mode();
        if ( !$is_enabled ) {
            return;
        }

        // Add the run-scan button + list table hooks
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Remove Edit and Quick Edit links, and add ignore link
        add_action( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
        add_action( 'page_row_actions', [ $this, 'row_actions' ], 10, 2 );

        // Add columns for each post type
        foreach ( (new BLNOTIFIER_HELPERS)->get_allowed_multiscan_post_types() as $post_type ) {
            add_filter( 'manage_'.$post_type.'_posts_columns', [ $this, 'column' ] );
            add_action( 'manage_'.$post_type.'_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
        }

    } // End init()


    /**
     * Does the current user have access to this feature?
     *
     * @return boolean
     */
    public function has_access() {
        return (new BLNOTIFIER_HELPERS)->user_can_manage_broken_links();
    } // End has_access()


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
     * Action links
     *
     * @param array $actions
     * @param object $post
     * @return array
     */
    public function row_actions( $actions, $post ) {
        if ( !$this->has_access() ) {
            return $actions;
        }
        
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
        if ( $this->do_stuff() && $this->has_access() ) {
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
        if ( !$this->has_access() ) {
            return;
        }

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

                } elseif ( $get_the_content ) {

                    // Prevent any redirects
                    add_filter( 'wp_redirect', '__return_false', 1 );
                    add_filter( 'wp_safe_redirect', '__return_false', 1 );

                    // Set up post context so shortcodes relying on get_the_ID()/is_singular() work correctly
                    global $post;
                    $scanned_post = get_post( $post_id );
                    $original_post = $post;
                    if ( $scanned_post ) {
                        $post = $scanned_post;
                        setup_postdata( $post );
                    }

                    // Start output buffering to suppress unexpected output
                    ob_start();

                    $redirect_detected = false;

                    try {
                        // Process shortcodes and expand them in the content
                        $content = apply_filters( 'the_content', $get_the_content );
                    } catch ( Exception $e ) {
                        error_log( 'Error processing shortcodes: ' . $e->getMessage() ); // phpcs:ignore 
                        $redirect_detected = true;
                    }

                    // Clear any unexpected output
                    ob_end_clean();

                    // Restore the original post context
                    if ( $scanned_post ) {
                        $post = $original_post;
                        wp_reset_postdata();
                    }

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

                        // Merge in remotely fetched links, if enabled
                        if ( filter_var( get_option( 'blnotifier_remote_fetch_links' ), FILTER_VALIDATE_BOOLEAN ) ) {
                            $remote_links = $HELPERS->get_remote_page_links( $post_id );
                            $links = $HELPERS->merge_and_dedupe_links( $links, $remote_links );
                        }

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
     * Enqueue the Multi-Scan tab page's own CSS, regardless of enabled state
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_tab_page_assets( $screen ) {
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();

        if ( $screen === $options_page && $tab === 'scan-multi' ) {
            wp_enqueue_style( 'blnotifier-scan-multi', BLNOTIFIER_PLUGIN_CSS_PATH.'scan-multi.css', [], BLNOTIFIER_SCRIPT_VERSION );
        }
    } // End enqueue_tab_page_assets()


    /**
     * Enqueue the run-scan button script and its CSS on the WP List Table screens (only when enabled)
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        if ( $screen !== 'edit.php' || !$this->has_access() ) {
            return;
        }

        global $current_screen;
        $post_types = get_option( 'blnotifier_post_types' );
        $post_types = !empty( $post_types ) ? array_keys( $post_types ) : [ 'post', 'page' ];
        if ( !isset( $current_screen->post_type ) || !in_array( $current_screen->post_type, $post_types ) ) {
            return;
        }

        wp_enqueue_style( 'blnotifier-scan-multi', BLNOTIFIER_PLUGIN_CSS_PATH.'scan-multi.css', [], BLNOTIFIER_SCRIPT_VERSION );

        $nonce = wp_create_nonce( 'blnotifier_blinks' );
        $handle = 'blnotifier_scan_multi_button_script';
        wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'scan-multi-button.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
        wp_localize_script( $handle, 'blnotifier_scan_multi_button', [
            'nonce'     => $nonce,
            'start_url' => add_query_arg( [ 'blinks' => 'true', '_wpnonce' => $nonce ] ),
            'stop_url'  => remove_query_arg( [ 'blinks', '_wpnonce' ] ),
        ] );
        wp_enqueue_script( $handle );
        wp_enqueue_script( 'jquery' );
    } // End enqueue_scripts()
}