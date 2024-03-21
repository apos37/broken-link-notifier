<?php
/**
 * Results Custom Post Type
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */

add_action( 'init', function() {
    (new BLNOTIFIER_RESULTS)->init();
} );



/**
 * Main plugin class.
 */
class BLNOTIFIER_RESULTS {

    /**
     * Post type
     * 
     * @var string
     */ 
    public $post_type = 'blnotifier-results';


    /**
     * The key that is used to identify the ajax response
     *
     * @var string
     */
    // private $back_end_ajax_key = 'blnotifier_ignore';
    private $ajax_key = 'blnotifier_blinks';


    /**
     * Name of nonce used for ajax call
     *
     * @var string
     */
    private $nonce = 'blnotifier_blinks_found';


    /**
     * Load on init
     */
    public function init() {

        // Register the post type
        $this->register_post_type();

        // Add the header to the top of the admin list page
        add_action( 'load-edit.php', [ $this, 'add_header' ] );
        add_action( 'load-edit-tags.php', [ $this, 'add_header' ] );

        // Redirect
        add_filter( 'get_edit_post_link', [ $this, 'redirect' ], 10, 3 );

        // Remove Edit from Bulk Actions
        add_filter( 'bulk_actions-edit-'.$this->post_type, [ $this, 'remove_from_bulk_actions' ] );

        // Remove post states
        add_filter( 'display_post_states', [ $this, 'remove_post_states' ], 999, 2 );

        // Remove Edit and Quick Edit links, and add ignore link
        add_action( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );

        // Skip trash and auto delete
        add_action( 'trashed_post', [ $this, 'skip_trash' ] );

        // Add a type filter
        add_action( 'restrict_manage_posts', [ $this, 'admin_filters' ], 10, 2 );
        add_action( 'pre_get_posts', [ $this, 'admin_filters_query' ] );

        // Add admin columns
        add_filter( 'manage_'.$this->post_type.'_posts_columns', [ $this, 'admin_columns' ] );
        add_action( 'manage_'.$this->post_type.'_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );

        // Make admin columns sortable
        add_filter( 'manage_edit-'.$this->post_type.'_sortable_columns', [ $this, 'sort_columns' ] );
        add_action( 'pre_get_posts', [ $this, 'sort_columns_query' ] );

        // Add notifications to admin bar
        add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 999 );

        // Log failed email notifications
        add_action( 'wp_mail_failed', [ $this, 'on_email_error' ] );

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key, [ $this, 'ajax' ] );
        add_action( 'wp_ajax_nopriv_'.$this->ajax_key, [ $this, 'ajax' ] );
        
        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'front_script_enqueuer' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'back_script_enqueuer' ] );

    } // End init()

    
    /**
     * Register the post type
     */
    public function register_post_type() {
        $singular_lc = 'link';
        $singular = ucwords( $singular_lc );
        $plural_lc = $singular_lc.'s';
        $plural = ucwords( $plural_lc );
        $menu_label = $plural;
        $name_admin_bar = $plural;

        // Set the labels
        $labels = [
            'name'                  => _x( $plural, 'Post Type General Name', 'broken-link-notifier' ),
            'singular_name'         => _x( $singular, 'Post Type Singular Name', 'broken-link-notifier' ),
            'menu_name'             => __( $menu_label, 'broken-link-notifier' ),
            'name_admin_bar'        => __( $name_admin_bar, 'broken-link-notifier' ),
            'search_items'          => __( 'Search '.$plural, 'broken-link-notifier' ),
            'not_found'             => __( 'Not found', 'broken-link-notifier' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'broken-link-notifier' ),
            'filter_items_list'     => __( 'Filter '.$singular_lc.' list', 'broken-link-notifier' ),
        ];
    
        // Set the CPT args
        $args = [
            'label'                 => __( $name_admin_bar, 'broken-link-notifier' ),
            'description'           => __( $plural, 'broken-link-notifier' ),
            'labels'                => $labels,
            'supports'              => [],
            'taxonomies'            => [],
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => false,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'query_var'             => $this->post_type,
            'capability_type'       => 'post',
            'capabilities'          => [
                'create_posts'      => false, 
            ],
            'map_meta_cap'          => true, 
            'show_in_rest'          => true,
        ];
    
        // Register the CPT
        register_post_type( $this->post_type, $args );
    } // End register_post_type()


    /**
     * Add the header to the top of the admin list page
     *
     * @return void
     */
    public function add_header() {
        $screen = get_current_screen();

        // Only edit post screen:
        if ( 'edit-'.$this->post_type === $screen->id ) {

            // Add the header
            add_action( 'all_admin_notices', function() {
                echo '<style>
                .bln-type {
                    padding: 5px 10px;
                    font-weight: bold;
                    margin-bottom: 10px;
                    width: 100px;
                    text-align: center;
                    text-transform: uppercase;
                    box-shadow: 0 2px 4px 0 rgba(7, 36, 86, 0.075);
                    border: 1px solid rgba(7, 36, 86, 0.075);
                    border-radius: 10px;
                }
                .bln-type code {
                    margin-right: 10px;
                }
                .bln-type.broken {
                    background: red;
                    color: white;
                }
                .bln-type.warning {
                    background: yellow;
                    color: black;
                }
                .source-url {
                    font-weight: 600;
                }
                .bln_source strong {
                    display: block;
                    margin-bottom: 0.2em;
                    font-size: 14px;
                }
                .bln_source .row-actions {
                    padding-top: 2px;
                }
                #message {
                    display: none;
                }
                tr.omitted {
                    opacity: 0.5;
                }
                </style>';
                echo '<div class="admin—title-cont">
                    <h1><span id="plugin-page-title">'.esc_attr( BLNOTIFIER_NAME ).' — Results</span></h1>
                </div>
                <div id="plugin-version">Version '.esc_attr( BLNOTIFIER_VERSION ).'</div>';
            } );
        }
    } // End add_header()


    /**
     * Redirect the edit link
     *
     * @param string $url
     * @param int $post_id
     * @param [type] $context
     * @return string
     */
    public function redirect( $url, $post_id, $context ) {
        if ( get_post_type( $post_id ) == $this->post_type ) {
            return sanitize_text_field( get_the_title( $post_id ) );
        } else {
            return $url;
        }
    } // End redirect()
  

    /**
     * Remove Edit from Bulk Actions
     *
     * @param array $actions
     * @return array
     */
    public function remove_from_bulk_actions( $actions ) {
        unset( $actions[ 'edit' ] );
        return $actions;
    } // End remove_from_bulk_actions()


    /**
     * Remove states
     *
     * @param array $states
     * @param WP_Post $post
     * @return void
     */
    public function remove_post_states( $states, $post ) {
        if ( get_post_type( $post ) == $this->post_type ) {
            return false;
        }
        return $states;
    } // End remove_post_states()


    /**
     * Action links
     *
     * @param array $actions
     * @param object $post
     * @return array
     */
    public function row_actions( $actions, $post ) {
        if ( $this->post_type == $post->post_type ) {
            foreach ( $actions as $action => $link ) {
                if ( $action != 'trash' ) {
                    unset( $actions[ $action ] );
                }
            }
            $permalink = get_the_title( $post );
            if ( !(new BLNOTIFIER_OMITS)->is_omitted( $permalink, 'links' ) ) {
                $actions[ 'omit' ] = '<a class="omit-link" href="#" data-link="'.$permalink.'" data-post-id="'.$post->ID.'">Omit From Future Scans</a>';
            }
        }
        return $actions;
    } // End row_actions()


    /**
     * Skip trash and auto delete
     *
     * @param int $post_id
     * @return void
     */
    public function skip_trash( $post_id ) {
        if ( get_post_type( $post_id ) == $this->post_type ) {
            wp_delete_post( $post_id, true );
        }
    } // End skip_trash()


    /**
     * Type filter
     *
     * @return void
     */
    public function admin_filters( $post_type, $which ) {
        $screen = get_current_screen();
        if ( $screen && $screen->id == 'edit-'.$this->post_type ) {

            // Link type
            if ( isset( $_REQUEST[ 'link-type' ] ) ) {  // phpcs:ignore
                $s = sanitize_key( $_REQUEST[ 'link-type' ] ); // phpcs:ignore
            } else {
                $s = '';
            }
            $HELPERS = new BLNOTIFIER_HELPERS;
            echo '<select id="link-type-filter" name="link-type">
                <option value=""'.esc_html( $HELPERS->is_selected( $s, '' ) ).'>All Link Types</option>
                <option value="broken"'.esc_html( $HELPERS->is_selected( $s, 'broken' ) ).'>Broken Links</option>
                <option value="warning"'.esc_html( $HELPERS->is_selected( $s, 'warning' ) ).'>Warning Links</option>
            </select>';

            // Status code
            $bad_codes = $HELPERS->get_bad_status_codes();
            $warning_codes = $HELPERS->get_warning_status_codes();
            $all_codes = array_unique( array_merge( $bad_codes, $warning_codes ) );
            sort( $all_codes );

            if ( isset( $_GET[ 'code' ] ) && sanitize_text_field( $_GET[ 'code' ] ) != '' ) { // phpcs:ignore
                $s = absint( $_GET[ 'code' ] ); // phpcs:ignore
            } else {
                $s = '';
            }
            $HELPERS = new BLNOTIFIER_HELPERS;
            echo '<select id="code-filter" name="code">
                <option value=""'.esc_html( $HELPERS->is_selected( $s, '' ) ).'>All Status Codes</option>';

                foreach ( $all_codes as $code ) {
                    echo '<option value="'.absint( $code ).'"'.esc_html( $HELPERS->is_selected( $s, $code ) ).'>'.( $code ).'</option>';
                }

            echo '</select>';
        }
    } // End admin_filters()


    /**
     * The type filter query
     *
     * @param object $query
     * @return void
     */
    public function admin_filters_query( $query ) {
        $post_type = $query->get( 'post_type' );
        if ( $post_type == $this->post_type ) {

            // Link type
            if ( isset( $_REQUEST[ 'link-type' ] ) && sanitize_text_field( $_REQUEST[ 'link-type' ] ) != '' ) { // phpcs:ignore
                $type = sanitize_key( $_REQUEST[ 'link-type' ] ); // phpcs:ignore
                if ( $type != '' ) {
                    $meta_query[] = [
                        'key'     => 'type',
                        'value'   => $type,
                    ];
                    $query->set( 'meta_query', $meta_query );
                }

            // Status code
            } elseif ( isset( $_REQUEST[ 'code' ] ) && sanitize_text_field( $_REQUEST[ 'code' ] ) != '' ) { // phpcs:ignore
                $code = absint( $_REQUEST[ 'code' ] ); // phpcs:ignore
                if ( $code != '' ) {
                    $meta_query[] = [
                        'key'     => 'code',
                        'value'   => $code,
                    ];
                    $query->set( 'meta_query', $meta_query );
                }
            }
        }
    } // End admin_filters_query()

    
    /**
     * Admin columns
     *
     * @param array $columns
     * @return array
     */
    public function admin_columns( $columns ) {
        return [
            'cb'            => '<input type="checkbox"/>',
            'bln_type'      => __( 'Type', 'broken-link-notifier' ),
            'title'         => __( 'Link', 'broken-link-notifier' ),
            'bln_source'    => __( 'Source', 'broken-link-notifier' ),
            'bln_source_pt' => __( 'Source Post Type', 'broken-link-notifier' ),
            'bln_date'      => __( 'Date', 'broken-link-notifier' ),
            'bln_author'    => __( 'User', 'broken-link-notifier' ),
        ];
    } // End admin_columns()


    /**
     * Admin column content
     *
     * @param string $column
     * @param int $post_id
     * @return void
     */
    public function admin_column_content( $column, $post_id ) {
        // Type
        if ( 'bln_type' === $column ) {
            $post = get_post( $post_id );
            if ( $post->type == 'broken' ) {
                echo '<div class="bln-type broken">Broken</div>';
            } elseif ( $post->type == 'warning' ) {
                echo '<div class="bln-type warning">Warning</div>';
            }
            $code = $post->code;
            if ( $code != 0 && $code != 666 ) {
                $code = '<a href="https://http.dev/'.$code.'" target="_blank">'.$code.'</a>';
            }
            if ( $code == 666 ) {
                $incl_title = ' title="A status code of 666 is a code we use to force invalid URL code 0 to be a broken link. It is not an official status code."';
            } elseif ( $code == 0 ) {
                $incl_title = ' title="A status code of 0 means there was no response and it can occur for various reasons, like request time outs. It almost always means something is randomly interfering with the user\'s connection, like a proxy server / firewall / load balancer / laggy connection / network congestion, etc."';
            }else {
                $incl_title = '';
            }
            echo '<code'.wp_kses_post( $incl_title ).'>Code: '.wp_kses_post( $code ).'</code> <span class="message">'.esc_html( $post->post_content ).'</span>';
        }

        // Source
        if ( 'bln_source' === $column ) {
            $source_url = get_post_meta( $post_id, 'source', true );
            if ( $source_url ) {
                $source_url = filter_var( $source_url, FILTER_SANITIZE_URL );
                $source_url = remove_query_arg( (new BLNOTIFIER_HELPERS)->get_qs_to_remove_from_source(), $source_url );
                $actions = [
                    '<span class="view"><a href="'.add_query_arg( 'blink', urlencode( strtok( get_the_title( $post_id ), '?' ) ), $source_url ).'" target="_blank">View</a></span>'
                ];
                if ( $source_id = url_to_postid( $source_url ) ) {
                    if ( !(new BLNOTIFIER_OMITS)->is_omitted( $source_url, 'pages' ) ) {
                        $actions[] = '<span class="omit"><a class="omit-page" href="#" data-link="'.$source_url.'">Omit</a></span>';
                    }
                    $nonce = wp_create_nonce( 'blnotifier_scan_single' );
                    $actions[] = '<span class="scan"><a class="scan-page" href="'.(new BLNOTIFIER_MENU)->get_plugin_page( 'scan-single' ).'&scan='.$source_url.'&_wpnonce='.$nonce.'" target="_blank">Scan Page</a></span>';
                    $actions[] = '<span class="edit"><a href="/'.BLNOTIFIER_ADMIN_DIR.'/post.php?post='.$source_id.'&action=edit">Edit</a></span>';
                    if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                        $actions[] = '<span class="edit-in-cornerstone"><a href="'.home_url( '/cornerstone/edit/'.$source_id ).'">Edit in Cornerstone</a></span>';
                    }
                    $source_url = '<strong><a class="source-url" href="'.$source_url.'">'.get_the_title( $source_id ).'</a></strong>';
                }
                echo wp_kses_post( $source_url ).'<div class="row-actions">'.implode( ' | ', $actions ).'</div>';
            }
        }

        // Source URL
        if ( 'bln_source_pt' === $column ) {
            $url = get_post_meta( $post_id, 'source', true );
            if ( $url ) {
                $url = filter_var( $url, FILTER_SANITIZE_URL );
                if ( $source_id = url_to_postid( $url ) ) {
                    $post_type = get_post_type( $source_id );
                    $post_type_name = (new BLNOTIFIER_HELPERS)->get_post_type_name( $post_type, true );
                } else {
                    $post_type_name = '--';
                }
                echo esc_html( $post_type_name );
            }
        }

        // Date
        if ( 'bln_date' === $column ) {
            $date = get_the_date( 'F j, Y g:i A', $post_id );
            $location = get_post_meta( $post_id, 'location', true );
            echo 'Discovered in '.esc_html( ucwords( $location ) ).'<br>'.esc_html( $date );
        }

        // Author
        if ( 'bln_author' === $column ) {
            $post = get_post( $post_id );
            if ( isset( $post->guest ) && $post->guest ) {
                $display_name = 'Guest';
            } elseif ( $author = $post->post_author ) {
                $user = get_user_by( 'ID', $author );
                $display_name = $user->display_name;
            } else {
                $display_name = 'Guest';
            }
            echo esc_html( $display_name );
        }
    } // End admin_column_content()
    

    /**
     * Make admin columns sortable
     *
     * @param array $columns
     * @return array
     */
    public function sort_columns( $columns ) {
        $columns[ 'bln_type' ]      = 'bln_type';
        $columns[ 'bln_source' ]    = 'bln_source';
        $columns[ 'bln_source_pt' ] = 'bln_source_pt';
        $columns[ 'bln_date' ]      = 'bln_date';
        return $columns;
    } // End sort_columns()


    /**
     * Sort the order column properly
     *
     * @param object $query
     * @return void
     */
    public function sort_columns_query( $query ) {
        global $current_screen;
        if ( is_admin() && isset( $current_screen ) && $current_screen->id === 'edit-'.$this->post_type ) {
            $orderby = $query->get( 'orderby' );
            if ( 'bln_type' == $orderby ) {
                $query->set( 'meta_key', 'type' );
                $query->set( 'orderby', 'meta_value' );
            } elseif ( 'bln_source' == $orderby ) {
                $query->set( 'meta_key', 'bln_source' );
                $query->set( 'orderby', 'meta_value' );
            } elseif ( 'bln_date' == $orderby ) {
                // $query->set( 'meta_key', 'date' );
                $query->set( 'orderby', 'date' );
            }
        }
    } // End sort_columns_query()


    /**
     * Add an online user count to the admin bar
     *
     * @param [type] $wp_admin_bar
     * @return void
     */
    public function admin_bar( $wp_admin_bar ) {
        // Add the node
        $wp_admin_bar->add_node( [
            'id'    => 'blnotifier-notify',
            'title' => '<span class="ab-icon dashicons dashicons-editor-unlink"></span> <span class="awaiting-mod">'.(new BLNOTIFIER_HELPERS)->count_broken_links().'</span>',
            'href'  => (new BLNOTIFIER_MENU)->get_plugin_page( 'results' )
        ] );

        // Add some CSS
        echo '<style>
        #wp-admin-bar-blnotifier-notify a {
            text-decoration: none !important;
        }
        #wp-admin-bar-blnotifier-notify .ab-icon {
            height: 5px;
            width: 13px;
            margin-top: 0px;
            margin-right: 8px;
            text-decoration: none !important;
        }
        #wp-admin-bar-blnotifier-notify .ab-icon:before {
            font-size: 16px;
        }
        </style>';
    } // End admin_bar()


    /**
     * Check if the link has already been added
     *
     * @param string $link
     * @return boolean
     */
    public function already_added( $link ) {
        return post_exists( sanitize_text_field( $link ), '', '', $this->post_type );
    } // End already_added()


    /**
     * Add a new broken or warning link
     * $args = [
     *      'broken'   => true,
     *      'warning'  => false,
     *      'code'     => 404,
     *      'text'     => 'Not found',
     *      'link'     => 'https://brokenlink.com',
     *      'source'   => 'https://source-url.com',
     *      'author'   => 1,
     *      'location' => 'content'
     * ]
     *
     * @param array $args
     * @return void
     */
    public function add( $args ) {
        // If already added, no need to add again
        if ( $this->already_added( $args[ 'link' ] ) ) {
            return 'Link already added';
        }

        // Args
        $data = [
            'post_title'    => sanitize_text_field( $args[ 'link' ] ),
            'post_content'  => sanitize_text_field( $args[ 'text' ] ),
            'post_status'   => 'publish',
            'post_type'     => $this->post_type,
            'meta_input'    => [
                'type'     => sanitize_key( $args[ 'type' ] ),
                'code'     => absint( $args[ 'code' ] ),
                'source'   => remove_query_arg( (new BLNOTIFIER_HELPERS)->get_qs_to_remove_from_source(), filter_var( $args[ 'source' ], FILTER_SANITIZE_URL ) ),
                'location' => sanitize_key( $args[ 'location' ] ),
                'guest'    => false
            ],
        ];

        // Guest or not?
        if ( $args[ 'author' ] == 0 ) {
            $data[ 'meta_input' ][ 'guest' ] = true;
        } else {
            $data[ 'post_author' ] = absint( $args[ 'author' ] );
        }

        // Add it
        $link_id = wp_insert_post( $data );
        if ( !is_wp_error( $link_id ) ) {
            return $link_id;
        } else {
            $error = $link_id->get_error_message();
            ddtt_write_log( $error );
            return $error;
        }
    } // End add()


    /**
     * Remove a broken or warning link
     * 
     * @param string $link
     * @return boolean
     */
    public function remove( $link ) {
        if ( $link_id = post_exists( $link, '', '', $this->post_type ) ) {
            if ( wp_delete_post( $link_id ) ) {
                return true;
            }
        }
        return false;
    } // End remove()


    /**
     * Notify
     *
     * @param array $args
     * @return void
     */
    public function notify( $flagged, $flagged_count, $all_links, $source_url  ) {
        // Perform any actions that people want to use
        do_action( 'blnotifier_notify', $flagged, $flagged_count, $all_links, $source_url );

        // Only notify flagged
        if ( $flagged_count > 0 ) {
    
            // Check if we are emailing
            if ( get_option( 'blnotifier_enable_emailing' ) ) {

                // Get the emails to send to
                $emails = sanitize_text_field( get_option( 'blnotifier_emails', '' ) );
                if ( $emails != '' ) {

                    // Headers
                    $headers[] = 'From: '.BLNOTIFIER_NAME.' <'.get_bloginfo( 'admin_email' ).'>';
                    $headers[] = 'Content-Type: text/html; charset=UTF-8';

                    // Subject
                    $subject = 'Broken Links Found';

                    // Message
                    $message = 'The following broken links were found today on '.$source_url.':<br><br>';
                    $broken_links = [];
                    foreach ( $flagged as $key => $section ) {
                        $message .= strtoupper( $key ).':<br><br>';
                        foreach ( $section as $f ) {
                            if ( $f[ 'type' ] == 'broken' && !$this->already_added( $f[ 'link' ] ) ) {
                                $broken_links[] = 'URL: '.$f[ 'link' ].'<br>Status Code: '.$f[ 'code' ].' - '.$f[ 'text' ];
                            }
                        }
                    }
                    $message .= implode( '<br><br>', $broken_links ).'<br><br><em>- '.BLNOTIFIER_NAME.' Plugin</em>';

                    // Filters
                    $emails = apply_filters( 'blnotifier_email_emails', $emails, $flagged, $source_url );
                    $subject = apply_filters( 'blnotifier_email_subject', $subject, $flagged, $source_url );
                    $message = apply_filters( 'blnotifier_email_message', $message, $flagged, $source_url );
                    $headers = apply_filters( 'blnotifier_email_headers', $headers, $flagged, $source_url );

                    // Try or log
                    if ( !wp_mail( $emails, $subject, $message, $headers ) ) {
                        error_log( BLNOTIFIER_NAME.' email could not be sent. Please check for issues with WP Mailer.' );
                    }
                }
            }

            // Discord
            if ( get_option( 'blnotifier_enable_discord' ) ) {
                $DISCORD = new BLNOTIFIER_DISCORD;
                $discord_webhook = get_option( 'blnotifier_discord' );
                if ( $discord_webhook && $DISCORD->sanitize_webhook_url( $discord_webhook ) != '' ) {
                    $discord_args = [
                        'msg'            => '',
                        'embed'          => true,
                        'author_name'    => 'Source: '.$source_url,
                        'author_url'     => $source_url,
                        'title'          => get_bloginfo( 'name' ),
                        'title_url'      => home_url(),
                        'desc'           => '-------------------',
                        'img_url'        => '',
                        'thumbnail_url'  => '',
                        'disable_footer' => false,
                        'bot_avatar_url' => BLNOTIFIER_PLUGIN_IMG_PATH.'logo-teal.png',
                        'bot_name'       => BLNOTIFIER_NAME,
                        'fields'         => []
                    ];
                    foreach ( $flagged as $key => $section ) {
                        foreach ( $section as $f ) {
                            if ( $f[ 'type' ] == 'broken' && !$this->already_added( $f[ 'link' ] ) ) {
                                $discord_args[ 'fields' ][] = [
                                    'name'   => 'Broken Link:',
                                    'value'  => html_entity_decode( $f[ 'link' ] ).'
                                    Status Code: '.$f[ 'code' ].' - '.$f[ 'text' ],
                                    'inline' => false
                                ];
                            }
                        }
                    }
                    if ( !empty( $discord_args[ 'fields' ] ) ) {
                        $discord_args = apply_filters( 'blnotifier_discord_args', $discord_args, $flagged, $source_url );
                        $send_to_discord = $DISCORD->send( $discord_webhook, $discord_args );
                        do_action( 'blnotifier_discord_response', $send_to_discord );
                    }
                }
            }

            // MS Teams
            if ( get_option( 'blnotifier_enable_msteams' ) ) {
                $MSTEAMS = new BLNOTIFIER_MSTEAMS;
                $msteams_webhook = get_option( 'blnotifier_msteams' );
                if ( $msteams_webhook && $MSTEAMS->sanitize_webhook_url( $msteams_webhook ) != '' ) {
                    $msteams_args = [
                        'site_name'     => get_bloginfo( 'name' ),
                        'title'         => 'Broken Links Found',
                        'msg'           => 'The following broken links were found:',
                        'img_url'       => '',
                        'source_url'    => $source_url,
                        'facts'         => []
                    ];
                    foreach ( $flagged as $key => $section ) {
                        foreach ( $section as $f ) {
                            if ( $f[ 'type' ] == 'broken' && !$this->already_added( $f[ 'link' ] ) ) {
                                $msteams_args[ 'facts' ][] = [
                                    'name'   => 'Broken Link:',
                                    'value'  => '['.$f[ 'link' ].']('.$f[ 'link' ].') \
                                    _Status Code: **'.$f[ 'code' ].'** - '.$f[ 'text' ].'_',
                                ];
                            }
                        }
                    }
                    if ( !empty( $msteams_args[ 'facts' ] ) ) {
                        $msteams_args = apply_filters( 'blnotifier_msteams_args', $msteams_args, $flagged, $source_url );
                        $send_to_msteams = $MSTEAMS->send( $msteams_webhook, $msteams_args );
                        do_action( 'blnotifier_msteams_response', $send_to_msteams );
                    }
                }
            }
        }
    } // End notify()


    /**
     * Log email notifications errors
     *
     * @param [type] $wp_error
     * @return void
     */
    public function on_email_error( $wp_error ) {
        error_log( $wp_error );
    } // End on_email_error()


    /**
     * Ajax call for front end
     *
     * @return void
     */
    public function ajax() {
        // Verify nonce
        if ( !wp_verify_nonce( $_REQUEST[ 'nonce' ], $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }
    
        // Get the ID
        $source_url = filter_var( $_REQUEST[ 'source_url' ], FILTER_SANITIZE_URL );
        $header_links = isset( $_REQUEST[ 'header_links' ] ) ? filter_var_array( $_REQUEST[ 'header_links' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : [];
        $content_links = isset( $_REQUEST[ 'content_links' ] ) ? filter_var_array( $_REQUEST[ 'content_links' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : [];
        $footer_links = isset( $_REQUEST[ 'footer_links' ] ) ? filter_var_array( $_REQUEST[ 'footer_links' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : [];

        // Make sure we have a source URL
        if ( $source_url ) {

            // Initiate helpers
            $HELPERS = new BLNOTIFIER_HELPERS;
            $bad_status_codes = $HELPERS->get_bad_status_codes();
            $warning_status_codes = $HELPERS->get_warning_status_codes();
            $notify_status_codes = array_merge( $bad_status_codes, $warning_status_codes );

            // Start timing
            $start = $HELPERS->start_timer();

            // Store the links we're going to notify
            $notify = [];
            $count_links = 0;
            $count_notify = 0;

            // Header links
            if ( !empty( $header_links ) ) {
                foreach ( $header_links as $header_link ) {
                    $count_links++;
                    $status = $HELPERS->check_link( $header_link );
                    if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                        $count_notify++;
                        $notify[ 'header' ][] = $status;
                    }
                }
            }

            // Content links
            if ( !empty( $content_links ) ) {
                foreach ( $content_links as $content_link ) {
                    $count_links++;
                    $status = $HELPERS->check_link( $content_link );
                    if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                        $count_notify++;
                        $notify[ 'content' ][] = $status;
                    }
                }
            }

            // Footer links
            if ( !empty( $footer_links ) ) {
                foreach ( $footer_links as $footer_link ) {
                    $count_links++;
                    $status = $HELPERS->check_link( $footer_link );
                    if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                        $count_notify++;
                        $notify[ 'footer' ][] = $status;
                    }
                }
            }

            // Notify
            $all_links = array_merge( $header_links, $content_links, $footer_links );
            $this->notify( $notify, $count_notify, $all_links, $source_url );

            // Add posts
            foreach ( $notify as $location => $n ) {
                foreach ( $n as $status ) {
                    $this->add( [
                        'type'     => $status[ 'type' ],
                        'code'     => $status[ 'code' ],
                        'text'     => $status[ 'text' ],
                        'link'     => $status[ 'link' ],
                        'source'   => $source_url,
                        'author'   => get_current_user_id(),
                        'location' => $location
                    ] );
                }
            }

            // Stop time
            $total_time = $HELPERS->stop_timer( $start );

            // Calculate per link
            if ( $count_links > 0 ) {
                $sec_per_link = round( ( $total_time / $count_links ), 2 );
            } else {
                $sec_per_link = 0;
            }

            // Return
            $result[ 'type' ] = 'success';
            $result[ 'notify' ] = $notify;
            $result[ 'timing' ] = 'Results were generated in '.$total_time.' seconds ('.$sec_per_link.'/link)';

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'No source url';
        }
    
        // Echo the result or redirect
        if( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) == 'xmlhttprequest' ) {
            echo wp_json_encode( $result );
        }
        else {
            header( 'Location: '.$_SERVER[ 'HTTP_REFERER' ] );
        }
    
        // We're done here
        die();
    } // End ajax()


    /**
     * Enque the JavaScript
     * // TODO: Reminder to swap version number after testing
     *
     * @return void
     */
    public function front_script_enqueuer() {
        // Only if
        if ( is_admin() || (new BLNOTIFIER_OMITS)->is_omitted( get_the_permalink(), 'pages' ) || in_array( get_post_type(), (new BLNOTIFIER_HELPERS)->get_omitted_pageload_post_types() ) ) {
            return;
        }

        // CSS
        wp_enqueue_style( 'front_end_css', BLNOTIFIER_PLUGIN_CSS_PATH.'results-front.css' );

        // Nonce
        $nonce = wp_create_nonce( $this->nonce );

        // Javascript
        $handle = 'front_end_js';
        wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'results-front.js', [ 'jquery' ], BLNOTIFIER_VERSION );
        wp_localize_script( $handle, 'blnotifier_front_end', [
            'show_in_console' => filter_var( get_option( 'blnotifier_show_in_console' ), FILTER_VALIDATE_BOOLEAN ),
            'admin_dir'       => BLNOTIFIER_ADMIN_DIR,
            'scan_header'     => filter_var( get_option( 'blnotifier_scan_header' ), FILTER_VALIDATE_BOOLEAN ),
            'scan_footer'     => filter_var( get_option( 'blnotifier_scan_footer' ), FILTER_VALIDATE_BOOLEAN ),
            'nonce'           => $nonce,
            'ajaxurl'         => admin_url( 'admin-ajax.php' )
        ] );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $handle );
    } // End front_script_enqueuer()


    /**
     * Enque the JavaScript
     * // TODO: Reminder to swap version number after testing
     *
     * @return void
     */
    public function back_script_enqueuer( $screen ) {
        $post_type = get_post_type();
        if ( $screen == 'edit.php' && $post_type == 'blnotifier-results' ) {
            $handle = 'blnotifier_results_back_end_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'results-back.js', [ 'jquery' ], BLNOTIFIER_VERSION );
            wp_enqueue_script( $handle );
            wp_enqueue_script( 'jquery' );
        }
    } // End back_script_enqueuer()
}