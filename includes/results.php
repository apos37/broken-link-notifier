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
 * Results class.
 */
class BLNOTIFIER_RESULTS {

    /**
     * Post type
     * 
     * @var string
     */ 
    public $table_name = 'blnotifier_results';


    /**
     * The key that is used to identify the ajax response
     *
     * @var string
     */
    // private $back_end_ajax_key = 'blnotifier_ignore';
    private $ajax_key_blinks = 'blnotifier_blinks';
    private $ajax_key_rescan = 'blnotifier_rescan';
    private $ajax_key_replace_link = 'blnotifier_replace_link';
    private $ajax_key_delete_result = 'blnotifier_delete_result';
    private $ajax_key_delete_source = 'blnotifier_delete_source';


    /**
     * Name of nonce used for ajax call
     *
     * @var string
     */
    private $nonce_blinks = 'blnotifier_blinks_found';
    private $nonce_rescan = 'blnotifier_rescan';
    private $nonce_replace = 'blnotifier_replace';
    private $nonce_delete = 'blnotifier_delete';


    /**
     * Load on init
     */
    public function init() {

        // Maybe create the database table
        $this->maybe_create_db();

        // Add the header to the top of the admin list page
        add_action( 'load-edit.php', [ $this, 'add_header' ] );
        add_action( 'load-edit-tags.php', [ $this, 'add_header' ] );

        // Add notifications to admin bar
        add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 999 );

        // Log failed email notifications
        add_action( 'wp_mail_failed', [ $this, 'on_email_error' ] );

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key_blinks, [ $this, 'ajax_blinks' ] );
        add_action( 'wp_ajax_nopriv_'.$this->ajax_key_blinks, [ $this, 'ajax_blinks' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_rescan, [ $this, 'ajax_rescan' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_replace_link, [ $this, 'ajax_replace_link' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_delete_result, [ $this, 'ajax_delete_result' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_delete_source, [ $this, 'ajax_delete_source' ] );
        
        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'front_script_enqueuer' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'back_script_enqueuer' ] );

    } // End init()


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
                .bln-type.good,
                .bln-type.fixed {
                    background: green;
                    color: white;
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
                <div id="plugin-version">' . esc_html__( 'Version', 'broken-link-notifier' ) . ' '.esc_attr( BLNOTIFIER_VERSION ).'</div>';
            } );
        }
    } // End add_header()


    /**
     * Add an online user count to the admin bar
     *
     * @param [type] $wp_admin_bar
     * @return void
     */
    public function admin_bar( $wp_admin_bar ) {
        $count = ( new BLNOTIFIER_HELPERS )->count_broken_links();
        $count_class = $count > 0 ? ' blnotifier-count-indicator' : '';

        $roles = get_option( 'blnotifier_editable_roles', [] );
        $roles[] = 'administrator';
        if ( !is_user_logged_in() || !array_intersect( wp_get_current_user()->roles, $roles ) ) {
            return;
        }

        // Add the node
        $wp_admin_bar->add_node( [
            'id'    => 'blnotifier-notify',
            'title' => '<span class="ab-icon dashicons dashicons-editor-unlink"></span> <span class="ab-count' . $count_class . '">' . $count . '</span>',
            'href'  => ( new BLNOTIFIER_MENU )->get_plugin_page( 'results' )
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
        #wp-admin-bar-blnotifier-notify .ab-count {
            margin: 0 0 0 2px !important;
        }
        #wp-admin-bar-blnotifier-notify .blnotifier-count-indicator {
            display: inline-block;
            margin: 0 0 0 2px !important;
            padding: 0 5px;
            background-color: #dc3232;
            color: #fff;
        }
        </style>';
    } // End admin_bar()


    /**
     * Create the database table if it doesn't exist.
     *
     * @return void
     */
    public function maybe_create_db() {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_name;
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                link varchar(2048) NOT NULL,
                link_hash char(32) NOT NULL,
                text varchar(255) NOT NULL,
                type varchar(20) NOT NULL,
                code smallint(5) unsigned NOT NULL,
                source varchar(2048) NOT NULL,
                location varchar(50) NOT NULL,
                method varchar(20) NOT NULL,
                guest tinyint(1) NOT NULL DEFAULT 0,
                author_id bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY link_hash (link_hash),
                KEY code (code),
                KEY created_at (created_at)
            ) $charset_collate;";

            dbDelta( $sql );

            // Migrate old posts if they exist
            $old_posts = get_posts( [
                'post_type'      => 'blnotifier-results',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
            ] );

            if ( ! empty( $old_posts ) ) {
                foreach ( $old_posts as $post_id ) {
                    $code     = absint( get_post_meta( $post_id, 'code', true ) );
                    $location = get_post_meta( $post_id, 'location', true );
                    $method   = get_post_meta( $post_id, 'method', true );
                    $source   = get_post_meta( $post_id, 'source', true );
                    $type     = get_post_meta( $post_id, 'type', true );

                    $post    = get_post( $post_id );
                    $link    = $post ? $post->post_title : '';
                    $text    = $post ? $post->post_content : '';
                    $author  = $post ? $post->post_author : 0;
                    $created = $post ? $post->post_date : current_time( 'mysql' );

                    $this->add( [
                        'link'   => $link,
                        'text'   => $text,
                        'type'   => $type,
                        'code'   => $code,
                        'source' => $source,
                        'location' => $location,
                        'method' => $method,
                        'author' => $author,
                        'created_at' => $created,
                    ] );

                    // Delete the old post
                    wp_delete_post( $post_id, true );
                }
            }
        }
    } // End maybe_create_db()


    /**
     * Check if the link has already been added
     *
     * @param string $link
     * @return boolean
     */
    public function already_added( $link ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $link_clean = sanitize_text_field( $link );

        // 1. New Hash
        $url_parts = explode( '?', $link_clean );
        $url_parts[ 0 ] = untrailingslashit( $url_parts[ 0 ] );
        $new_hash = md5( strtolower( implode( '?', $url_parts ) ) );

        // 2. Old Hash
        $old_hash = md5( strtolower( untrailingslashit( $link_clean ) ) );

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE link_hash = %s OR link_hash = %s LIMIT 1",
                $new_hash,
                $old_hash
            )
        );

        return ! empty( $exists );
    } // End already_added()


    /**
     * Add a new broken or warning link
     *
     * @param array $args
     * @return void
     */
    public function add( $args ) {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_name;

        $link = sanitize_text_field( $args[ 'link' ] );
        
        $url_parts = explode( '?', $link );
        $url_parts[ 0 ] = untrailingslashit( $url_parts[ 0 ] );
        $link_normalized = strtolower( implode( '?', $url_parts ) );
        $link_hash = md5( $link_normalized );

        if ( $this->already_added( $link ) ) {
            return 'Link already added';
        }

        $source_url = remove_query_arg(
            ( new BLNOTIFIER_HELPERS )->get_qs_to_remove_from_source(),
            filter_var( $args[ 'source' ], FILTER_SANITIZE_URL )
        );

        if ( ! $source_url ) {
            return __( 'Invalid source:', 'broken-link-notifier' ) . ' ' . $source_url;
        }

        $inserted = $wpdb->insert(
            $table_name,
            [
                'link'       => $link,
                'link_hash'  => $link_hash,
                'text'       => sanitize_text_field( $args[ 'text' ] ),
                'type'       => sanitize_key( $args[ 'type' ] ),
                'code'       => absint( $args[ 'code' ] ),
                'source'     => esc_url_raw( $source_url ),
                'location'   => sanitize_key( $args[ 'location' ] ),
                'method'     => sanitize_key( $args[ 'method' ] ),
                'guest'      => ( absint( $args[ 'author' ] ) === 0 ) ? 1 : 0,
                'author_id'  => absint( $args[ 'author' ] ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s' ]
        );

        if ( $inserted ) {
            return $wpdb->insert_id;
        }

        return 'Insert failed';
    } // End add()


    /**
     * Remove a broken or warning link
     * 
     * @param string $link
     * @return boolean
     */
    public function remove( $link ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $link = sanitize_text_field( $link );

        // 1. New Normalized Hash
        $url_parts = explode( '?', $link );
        $url_parts[ 0 ] = untrailingslashit( $url_parts[ 0 ] );
        $new_hash = md5( strtolower( implode( '?', $url_parts ) ) );

        // 2. Old Logic Hash (Legacy)
        $old_hash = md5( strtolower( untrailingslashit( $link ) ) );

        // Try to delete by either hash
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE link_hash = %s OR link_hash = %s",
                $new_hash,
                $old_hash
            )
        );

        return ( false !== $deleted && $deleted > 0 );
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

                    $emails = array_map( 'trim', explode( ',', $emails ) );

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

                    // Verify before sending
                    if ( !empty( $broken_links ) ) {

                        // Results page link
                        $results_page_link = '<br><br>You can see all broken links here:<br>'.(new BLNOTIFIER_MENU)->get_plugin_page( 'results' ).'<br><br>';

                        // Add links and footer
                        $message .= implode( '<br><br>', $broken_links ).$results_page_link.'<br><br><hr><br>'.get_bloginfo( 'name' ).'<br><em>'.BLNOTIFIER_NAME.' Plugin<br></em>';
                        
                        // Filters
                        $emails = apply_filters( 'blnotifier_email_emails', $emails, $flagged, $source_url );
                        $subject = apply_filters( 'blnotifier_email_subject', $subject, $flagged, $source_url );
                        $message = apply_filters( 'blnotifier_email_message', $message, $flagged, $source_url );
                        $headers = apply_filters( 'blnotifier_email_headers', $headers, $flagged, $source_url );

                        // Try or log
                        if ( ! wp_mail( $emails, $subject, $message, $headers ) ) {
                            error_log( BLNOTIFIER_NAME.' email could not be sent. Please check for issues with WP Mailer.' ); // phpcs:ignore 
                        }
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
        error_log( $wp_error->get_error_message() ); // phpcs:ignore 
    } // End on_email_error()


    /**
     * Public AJAX endpoint used by the front-end scanner.
     *
     * This endpoint intentionally allows unauthenticated requests because the
     * plugin scans links from publicly accessible pages visited by guests.
     *
     * Security protections implemented:
     * - Nonce verification to prevent CSRF
     * - Rate limiting per IP via transient
     * - Maximum links per scan enforced
     * - URLs sanitized before processing
     * - Only HTTP/HTTPS sources allowed
     *
     * No privileged actions are performed. The endpoint only scans links and
     * records results in a custom table that is not publicly accessible.
     */
    public function ajax_blinks() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce_blinks ) ) {
            exit( 'No naughty business please.' );
        }

        // Public endpoint: allow guests, but validate capability for logged-in users.
        if ( is_user_logged_in() && ! current_user_can( 'read' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
    
        // Get the source and links
        $source_url    = isset( $_REQUEST[ 'source_url' ] ) ? filter_var( wp_unslash( $_REQUEST[ 'source_url' ] ), FILTER_SANITIZE_URL ) : '';
        $header_links  = isset( $_REQUEST[ 'header_links' ] ) ? wp_unslash( $_REQUEST[ 'header_links' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content_links = isset( $_REQUEST[ 'content_links' ] ) ? wp_unslash( $_REQUEST[ 'content_links' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $footer_links  = isset( $_REQUEST[ 'footer_links' ] ) ? wp_unslash( $_REQUEST[ 'footer_links' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Enforce max links per page
        $max_links = absint( get_option( 'blnotifier_max_links_per_page', 200 ) );
        $total_links = count( $header_links ) + count( $content_links ) + count( $footer_links );
        if ( $total_links > $max_links ) {
            $result = [
                'type' => 'error',
                'msg'  => sprintf( 'Too many links in one scan. Max allowed: %d', $max_links )
            ];
            self::send_ajax_or_redirect( $result );
        }

        // Rate limit per IP only for non-link-managers
        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            $ip = $_SERVER[ 'REMOTE_ADDR' ];
            $transient_key = 'bln_rate_' . md5( $ip );
            if ( get_transient( $transient_key ) ) {
                $result = [
                    'type' => 'error',
                    'msg'  => 'Scan rate limit exceeded'
                ];
                self::send_ajax_or_redirect( $result );
            }
            set_transient( $transient_key, 1, 10 ); // 10-second cooldown
        }

        // Make sure we have a source URL
        if ( $source_url ) {

            // Only allow webpages, not file:///, etc.
            if ( !str_starts_with( $source_url, 'http' ) ) {
                wp_send_json_error( 'Invalid source: ' . $source_url );
            }

            // Validate that the URL belongs to this site and exists
            $site_url = site_url();
            if ( ! str_starts_with( $source_url, $site_url ) ) {
                wp_send_json_error( 'External source URLs are not permitted.' );
            }

            // Check for Post ID with full URL
            $post_id = url_to_postid( $source_url );

            // If not found, check without query parameters
            if ( ! $post_id ) {
                $clean_url = strtok( $source_url, '?' );
                $post_id  = url_to_postid( $clean_url );
            }

            // If it's not a post/page and it's not the homepage, it's likely a 404 or invalid
            if ( ! $post_id && $source_url !== trailingslashit( $site_url ) && $source_url !== $site_url ) {
                wp_send_json_error( 'Source URL does not exist on this site.' );
            }

            // Initiate helpers
            $HELPERS = new BLNOTIFIER_HELPERS;

            // Codes
            $bad_status_codes = $HELPERS->get_bad_status_codes();
            $warning_status_codes = $HELPERS->get_warning_status_codes();
            $notify_status_codes = array_merge( $bad_status_codes, $warning_status_codes );
            $show_good_links_in_results = get_option( 'blnotifier_enable_good_links' );

            // Start timing
            $start = $HELPERS->start_timer();

            // Store the links we're going to notify
            $notify = [];
            $count_links = 0;
            $count_notify = 0;
            $good_links = [];

            // Header links
            if ( !empty( $header_links ) ) {
                foreach ( $header_links as &$header_link ) {
                    $count_links++;
                    $header_link = $HELPERS->sanitize_link( $header_link );
                    $status = $HELPERS->check_link( $header_link );
                    if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                        $count_notify++;
                        $notify[ 'header' ][] = $status;
                    } else {
                        $good_links[ 'header' ][] = $status;
                    }
                }
            }

            // Content links
            if ( !empty( $content_links ) ) {
                foreach ( $content_links as &$content_link ) {
                    $count_links++;
                    $content_link = $HELPERS->sanitize_link( $content_link );
                    $status = $HELPERS->check_link( $content_link );
                    if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                        $count_notify++;
                        $notify[ 'content' ][] = $status;
                    } else {
                        $good_links[ 'content' ][] = $status;
                    }
                }
            }

            // Footer links
            if ( !empty( $footer_links ) ) {
                foreach ( $footer_links as &$footer_link ) {
                    $count_links++;
                    $footer_link = $HELPERS->sanitize_link( $footer_link );
                    $status = $HELPERS->check_link( $footer_link );
                    if ( in_array( $status[ 'code' ], $notify_status_codes ) ) {
                        $count_notify++;
                        $notify[ 'footer' ][] = $status;
                    } else {
                        $good_links[ 'footer' ][] = $status;
                    }
                }
            }

            // Notify
            $all_links = array_merge( $header_links, $content_links, $footer_links );
            $this->notify( $notify, $count_notify, $all_links, $source_url );

            $current_user_id = get_current_user_id();

            // Add posts
            foreach ( $notify as $location => $n ) {
                foreach ( $n as $status ) {
                    $this->add( [
                        'type'     => $status[ 'type' ],
                        'code'     => $status[ 'code' ],
                        'text'     => $status[ 'text' ],
                        'link'     => $status[ 'link' ],
                        'source'   => $source_url,
                        'author'   => $current_user_id,
                        'location' => $location,
                        'method'   => 'visit'
                    ] );
                }
            }

            // Add posts
            if ( $show_good_links_in_results ) {
                foreach ( $good_links as $location => $gl ) {
                    foreach ( $gl as $status ) {
                        $this->add( [
                            'type'     => $status[ 'type' ],
                            'code'     => $status[ 'code' ],
                            'text'     => $status[ 'text' ],
                            'link'     => $status[ 'link' ],
                            'source'   => $source_url,
                            'author'   => $current_user_id,
                            'location' => $location,
                            'method'   => 'visit'
                        ] );
                    }
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
        self::send_ajax_or_redirect( $result );
    } // End ajax_blinks()


    /**
     * Ajax call for back end
     *
     * @return void
     */
    public function ajax_rescan() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce_rescan ) ) {
            exit( 'No naughty business please.' );
        }

        // Check permissions
        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( !$HELPERS->user_can_manage_broken_links() ) {
            exit( 'Unauthorized access.' );
        }
    
        // Get the data
        $link      = isset( $_REQUEST[ 'link' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'link' ] ) ) : false;
        $link_id   = isset( $_REQUEST[ 'linkID' ] ) ? absint( wp_unslash( $_REQUEST[ 'linkID' ] ) ) : false;
        $code      = isset( $_REQUEST[ 'code' ] ) ? absint( wp_unslash( $_REQUEST[ 'code' ] ) ) : false;
        $type      = isset( $_REQUEST[ 'type' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'type' ] ) ) : false;
        $source_id = isset( $_REQUEST[ 'sourceID' ] ) ? absint( wp_unslash( $_REQUEST[ 'sourceID' ] ) ) : false;
        $method    = isset( $_REQUEST[ 'method' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'method' ] ) ) : false;

        // Make sure we have a source URL
        if ( $link ) {

            // If the source no longer exists, auto remove it
            if ( !$source_id || !get_post( $source_id ) ) {
                $remove = $this->remove( $HELPERS->str_replace_on_link( $link ) );
                $status = [
                    'type' => 'n/a',
                    'code' => $code,
                    'text' => __( 'Source no longer exists.', 'broken-link-notifier' ),
                    'link' => $link
                ];

                if ( $remove ) {
                    $result[ 'type' ] = 'success';
                    $result[ 'status' ] = $status;
                    $result[ 'link' ] = $link;
                    $result[ 'link_id' ] = $link_id;
                } else {
                    $result[ 'type' ] = 'error';
                    $result[ 'msg' ] = __( 'Could not auto-remove link.', 'broken-link-notifier' );
                }

            // Source exists
            } else {

                // Check status
                $status = $HELPERS->check_link( $link );
                
                // If it's good now, remove the old post
                if ( $status[ 'type' ] == 'good' || $status[ 'type' ] == 'omitted' ) {
                    $remove = $this->remove( $HELPERS->str_replace_on_link( $link ) );
                    if ( $remove ) {
                        $result[ 'type' ] = 'success';
                        $result[ 'status' ] = $status;
                        $result[ 'link' ] = $link;
                        $result[ 'link_id' ] = $link_id;
                    } else {
                        $result[ 'type' ] = 'error';
                        // translators: the status type
                        $result[ 'msg' ] = sprintf( __( 'Could not remove %s link. Please try again.', 'broken-link-notifier' ),
                            $status[ 'type' ]
                        );
                    }
    
                // If it's still not good, but doesn't have the same code or type, update it
                } elseif ( $code !== $status[ 'code' ] || $type !== $status[ 'type' ] ) {
                    $remove = $this->remove( $HELPERS->str_replace_on_link( $link ) );
                    if ( $remove ) {
                        $result[ 'type' ] = 'success';
                        $result[ 'status' ] = $status;
                        $result[ 'link' ] = $link;
                        $result[ 'link_id' ] = $link_id;
    
                        // Re-add it with new data
                        $this->add( [
                            'type'     => $status[ 'type' ],
                            'code'     => $status[ 'code' ],
                            'text'     => $status[ 'text' ],
                            'link'     => $status[ 'link' ],
                            'source'   => get_the_permalink( $source_id ),
                            'author'   => get_current_user_id(),
                            'location' => 'content',
                            'method'   => $method
                        ] );
                    } else {
                        $result[ 'type' ] = 'error';
                        $result[ 'msg' ] = __( 'Could not update link with new code. Please try again.', 'broken-link-notifier' );
                    }
                } else {
                    $result[ 'type' ] = 'success';
                    $result[ 'status' ] = $status;
                    $result[ 'link' ] = $link;
                    $result[ 'link_id' ] = $link_id;
                }
            }

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = __( 'No link found.', 'broken-link-notifier' );
        }
    
        // Echo the result or redirect
        self::send_ajax_or_redirect( $result );
    } // End ajax_rescan()


    /**
     * Ajax call for back end
     *
     * @return void
     */
    public function ajax_replace_link() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce_replace ) ) {
            exit( 'No naughty business please.' );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( !$HELPERS->user_can_manage_broken_links() ) {
            exit( 'Unauthorized access.' );
        }
    
        // Get the vars
        $oldLink    = isset( $_REQUEST[ 'oldLink' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'oldLink' ] ) ) : false;
        $newLink    = isset( $_REQUEST[ 'newLink' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'newLink' ] ) ) : false;
        $source_id  = isset( $_REQUEST[ 'sourceID' ] ) ? absint( wp_unslash( $_REQUEST[ 'sourceID' ] ) ) : false;

        if ( $oldLink && $newLink && $source_id && get_post( $source_id ) ) {

            // Get the current post content
            $post_content = get_post_field( 'post_content', $source_id );

            // Replace old link with new link in the content
            $updated_content = str_replace( $oldLink, $newLink, $post_content );

            // Update the post content
            $updated_post = [
                'ID'           => $source_id,
                'post_content' => $updated_content,
            ];

            // Update the post in the database
            $result = wp_update_post( $updated_post );
            if ( !is_wp_error( $result ) ) {

                // Let's also delete the result
                $this->remove( $HELPERS->str_replace_on_link( $oldLink ) );

                // Respond
                wp_send_json_success();
            } else {
                wp_send_json_error( 'Failed to update the post: ' . $result->get_error_message() );
            }
        }

        // Failure
        wp_send_json_error( 'Failed to delete.' );
    } // End ajax_replace_link()


    /**
     * Ajax call for back end
     *
     * @return void
     */
    public function ajax_delete_result() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce_delete ) ) {
            exit( 'No naughty business please.' );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( !$HELPERS->user_can_manage_broken_links() ) {
            exit( 'Unauthorized access.' );
        }
    
        // Remove the link
        $link = isset( $_REQUEST[ 'link' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'link' ] ) ) : false;
        if ( $link ) {
            $this->remove( $HELPERS->str_replace_on_link( $link ) );
            wp_send_json_success();
        }

        // Failure
        wp_send_json_error( 'Failed to delete.' );
    } // End ajax_delete_result()


    /**
     * Ajax call for back end
     *
     * @return void
     */
    public function ajax_delete_source() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce_delete ) ) {
            exit( 'No naughty business please.' );
        }

        // Check permissions
        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            exit( 'Unauthorized access.' );
        }

        // Make sure we are allowed to delete the source
        if ( !get_option( 'blnotifier_enable_delete_source' ) ) {
            wp_send_json_error( 'Deleting source is not enabled.' );
        }
    
        // Get the ID
        $source_id = isset( $_REQUEST[ 'sourceID' ] ) ? absint( $_REQUEST[ 'sourceID' ] ) : false;
        if ( $source_id ) {

            // Delete all links with this source
            global $wpdb;

            $source_url = (new BLNOTIFIER_HELPERS)->get_clean_permalink( $source_id );
            if ( $source_url ) {
                $table_name = $wpdb->prefix . $this->table_name;

                $wpdb->delete(
                    $table_name,
                    [ 'source' => $source_url ],
                    [ '%s' ]
                );
            }

            // Trash the source itself
            if ( wp_trash_post( $source_id ) ) {
                wp_send_json_success();
            }
        }

        // Failure
        wp_send_json_error( 'Failed to delete.' );
    } // End ajax_delete_source()


    /**
     * Send JSON result or redirect for non-AJAX requests.
     *
     * @param array $result The result array to return.
     *
     * @return void
     */
    public static function send_ajax_or_redirect( $result ) {
        if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( sanitize_key( wp_unslash( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) ) ) === 'xmlhttprequest' ) {
            header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
            echo wp_json_encode( $result );
        } else {
            $referer = isset( $_SERVER[ 'HTTP_REFERER' ] ) ? filter_var( wp_unslash( $_SERVER[ 'HTTP_REFERER' ] ), FILTER_SANITIZE_URL ) : '';
            header( 'Location: ' . $referer );
        }
        die();
    } // End send_ajax_or_redirect()


    /**
     * Enque the JavaScript
     *
     * @return void
     */
    public function front_script_enqueuer() {
        // Only if
        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( is_admin() || (new BLNOTIFIER_OMITS)->is_omitted( get_the_permalink(), 'pages' ) || in_array( get_post_type(), $HELPERS->get_omitted_pageload_post_types() ) || $HELPERS->is_frontend_scanning_paused() ) {
            return;
        }

        // CSS
        wp_enqueue_style( 'front_end_css', BLNOTIFIER_PLUGIN_CSS_PATH.'results-front.min.css', [], BLNOTIFIER_VERSION );

        // Nonce
        $nonce = wp_create_nonce( $this->nonce_blinks );

        // Javascript
        $handle = 'front_end_js';
        wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'results-front.min.js', [ 'jquery' ], BLNOTIFIER_VERSION, true ); 
        wp_localize_script( $handle, 'blnotifier_front_end', [
            'show_in_console' => filter_var( get_option( 'blnotifier_show_in_console' ), FILTER_VALIDATE_BOOLEAN ),
            'admin_dir'       => BLNOTIFIER_ADMIN_DIR,
            'scan_header'     => filter_var( get_option( 'blnotifier_scan_header' ), FILTER_VALIDATE_BOOLEAN ),
            'scan_footer'     => filter_var( get_option( 'blnotifier_scan_footer' ), FILTER_VALIDATE_BOOLEAN ),
            'elements'        => (new BLNOTIFIER_HELPERS)->get_html_link_sources(),
            'nonce'           => $nonce,
            'ajaxurl'         => admin_url( 'admin-ajax.php' )
        ] );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $handle );
    } // End front_script_enqueuer()


    /**
     * Enque the JavaScript
     *
     * @return void
     */
    public function back_script_enqueuer( $screen ) {
        if ( $screen == 'toplevel_page_' . BLNOTIFIER_TEXTDOMAIN ) {
            $handle = 'blnotifier_results_back_end_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'results-back.js', [ 'jquery' ], time(), true );
            wp_localize_script( $handle, 'blnotifier_back_end', [
                'verifying'     => !(new BLNOTIFIER_HELPERS())->is_results_verification_paused(),
                'nonce_rescan'  => wp_create_nonce( $this->nonce_rescan ),
                'nonce_replace' => wp_create_nonce( $this->nonce_replace ),
                'nonce_delete'  => wp_create_nonce( $this->nonce_delete ),
                'ajaxurl'       => admin_url( 'admin-ajax.php' )
            ] );
            wp_enqueue_script( $handle );
            wp_enqueue_script( 'jquery' );
        }
    } // End back_script_enqueuer()
}