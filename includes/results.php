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
    private $ajax_key_table = 'blnotifier_results_table';
    private $ajax_key_bulk = 'blnotifier_results_bulk';


    /**
     * Name of nonce used for ajax call
     *
     * @var string
     */
    private $nonce_blinks              = 'blnotifier_blinks_found';
    private $nonce_rescan              = 'blnotifier_rescan';
    private $nonce_replace             = 'blnotifier_replace';
    private $nonce_delete              = 'blnotifier_delete';
    private $nonce_table               = 'blnotifier_results_table';
    private $nonce_bulk                = 'blnotifier_results_bulk';


    /**
     * Load on init
     */
    public function init() {

        // Maybe create the database table
        $this->maybe_create_db();

        // Add notifications to admin bar
        add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 999 );

        // Log failed email notifications
        add_action( 'wp_mail_failed', [ $this, 'on_email_error' ] );

        // Render the verify button in the subheader
        add_action( 'blnotifier_subheader_right', [ $this, 'render_subheader_right' ] );

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key_blinks, [ $this, 'ajax_blinks' ] );
        add_action( 'wp_ajax_nopriv_'.$this->ajax_key_blinks, [ $this, 'ajax_blinks' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_rescan, [ $this, 'ajax_rescan' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_replace_link, [ $this, 'ajax_replace_link' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_delete_result, [ $this, 'ajax_delete_result' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_delete_source, [ $this, 'ajax_delete_source' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_table, [ $this, 'ajax_table' ] );
        add_action( 'wp_ajax_'.$this->ajax_key_bulk, [ $this, 'ajax_bulk_action' ] );
        add_action( 'wp_ajax_blnotifier_dismiss_verify_notice', [ $this, 'ajax_dismiss_verify_notice' ] );
        
        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_admin_bar_css_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_bar_css_backend' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'front_script_enqueuer' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'back_script_enqueuer' ] );

    } // End init()


    /**
     * Add an online user count to the admin bar
     *
     * @param  WP_Admin_Bar $wp_admin_bar
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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is a hardcoded prefix + fixed name, not user input; both values are bound via prepare().
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE link_hash = %s OR link_hash = %s LIMIT 1",
                $new_hash,
                $old_hash
            )
        );
        // phpcs:enable

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
            return __( 'Link already added', 'broken-link-notifier' );
        }

        $source_url = remove_query_arg(
            ( new BLNOTIFIER_HELPERS )->get_qs_to_remove_from_source(),
            filter_var( $args[ 'source' ], FILTER_SANITIZE_URL )
        );

        if ( ! $source_url ) {
            return __( 'Invalid source:', 'broken-link-notifier' ) . ' ' . esc_url( $source_url );
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- $table_name is a hardcoded prefix + fixed name, not user input; all values are bound via the $wpdb->insert() format array.
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
        // phpcs:enable

        if ( $inserted ) {
            return $wpdb->insert_id;
        }

        return __( 'Insert failed', 'broken-link-notifier' );
    } // End add()


    /**
     * Remove a broken or warning link
     *
     * @param string   $link
     * @param int|bool $id Optional link ID for direct deletion
     * @return boolean
     */
    public function remove( $link, $id = false ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $deleted    = 0;

        // 1. Try deleting by ID first if provided
        if ( $id ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- $table_name is a hardcoded prefix + fixed name, not user input; all values are bound via the $wpdb->delete() format array.
            $deleted = $wpdb->delete(
                $table_name,
                [ 'id' => absint( $id ) ],
                [ '%d' ]
            );
            // phpcs:enable
        }

        // 2. Fallback to hash lookup if ID didn't work or wasn't provided
        if ( ! $deleted ) {
            $link = sanitize_text_field( $link );

            // New Normalized Hash
            $url_parts = explode( '?', $link );
            $url_parts[0] = untrailingslashit( $url_parts[0] );
            $new_hash = md5( strtolower( implode( '?', $url_parts ) ) );

            // Old Logic Hash (Legacy)
            $old_hash = md5( strtolower( untrailingslashit( $link ) ) );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is a hardcoded prefix + fixed name, not user input; both values are bound via prepare().
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table_name WHERE link_hash = %s OR link_hash = %s",
                    $new_hash,
                    $old_hash
                )
            );
            // phpcs:enable
        }

        return ( false !== $deleted && $deleted > 0 );
    } // End remove()


    /**
     * Notify
     *
     * @param array $flagged
     * @param int $flagged_count
     * @param array $all_links
     * @param string $source_url
     * @return void
     */
    public function notify( $flagged, $flagged_count, $all_links, $source_url  ) {
        // Perform any actions that people want to use
        do_action( 'blnotifier_notify', $flagged, $flagged_count, $all_links, $source_url );

        // Allow notifications to be filtered
        $flagged = apply_filters( 'blnotifier_notify_flagged', $flagged, $flagged_count, $all_links, $source_url );

        // Check if warnings are enabled.
        $warnings_enabled_check = filter_var( get_option( 'blnotifier_enable_warnings' ), FILTER_VALIDATE_BOOLEAN );

        // Count broken and warning links that should be notified.
        $broken_count = 0;
        $warning_count = 0;

        foreach ( $flagged as $section ) {
            foreach ( $section as $f ) {
                if ( $f[ 'type' ] == 'broken' ) {
                    $broken_count++;
                } elseif ( $f[ 'type' ] == 'warning' && $warnings_enabled_check ) {
                    $warning_count++;
                }
            }
        }

        // Only notify flagged
        if ( $flagged_count > 0 ) {

            // Subject & Message
            if ( $broken_count > 0 && $warning_count > 0 ) {
                $subject = __( 'Broken Links and Warnings Found', 'broken-link-notifier' );
                /* translators: %s: source URL */
                $message = sprintf( __( 'The following broken links and warnings were found on %s:', 'broken-link-notifier' ), esc_url( $source_url ) );
            } elseif ( $broken_count > 0 ) {
                $subject = __( 'Broken Links Found', 'broken-link-notifier' );
                /* translators: %s: source URL */
                $message = sprintf( __( 'The following broken links were found on %s:', 'broken-link-notifier' ), esc_url( $source_url ) );
            } else {
                $subject = __( 'Link Warnings Found', 'broken-link-notifier' );
                /* translators: %s: source URL */
                $message = sprintf( __( 'The following link warnings were found on %s:', 'broken-link-notifier' ), esc_url( $source_url ) );
            }
    
            // Check if we are emailing
            if ( get_option( 'blnotifier_enable_emailing' ) ) {

                // Get the emails to send to
                $emails = sanitize_text_field( get_option( 'blnotifier_emails', '' ) );
                if ( $emails != '' ) {

                    $emails = array_map( 'trim', explode( ',', $emails ) );

                    // Headers
                    $headers[] = 'From: '.BLNOTIFIER_NAME.' <'.get_bloginfo( 'admin_email' ).'>';
                    $headers[] = 'Content-Type: text/html; charset=UTF-8';

                    // Addt. Message Breaks
                    $message .= '<br><br>';
                    
                    // Notification links array
                    $notification_links = [];
                    foreach ( $flagged as $key => $section ) {
                        $message .= strtoupper( $key ).':<br><br>';
                        foreach ( $section as $f ) {
                            if ( ( $f[ 'type' ] == 'broken' || $f[ 'type' ] == 'warning' ) && !$this->already_added( $f[ 'link' ] ) ) {
                                $label = $f[ 'type' ] == 'warning' ? __( 'Warning', 'broken-link-notifier' ) : __( 'Broken Link', 'broken-link-notifier' );
                                $notification_links[] = $label.': '.$f[ 'link' ].'<br>'.__( 'Status Code', 'broken-link-notifier' ).': '.$f[ 'code' ].' - '.$f[ 'text' ];
                            }
                        }
                    }

                    // Verify before sending
                    if ( !empty( $notification_links ) ) {

                        // Results page link
                        $results_page_link = '<br><br>'.__( 'You can see all issues here:', 'broken-link-notifier' ).'<br>'.(new BLNOTIFIER_MENU)->get_plugin_page( 'results' ).'<br><br>';

                        // Add links and footer
                        $message .= implode( '<br><br>', $notification_links ).$results_page_link.'<br><br><hr><br>'.get_bloginfo( 'name' ).'<br><em>'.BLNOTIFIER_NAME.' '.__('Plugin', 'broken-link-notifier').'<br></em>';
                        
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
                            if ( ( $f[ 'type' ] == 'broken' || $f[ 'type' ] == 'warning' ) && !$this->already_added( $f[ 'link' ] ) ) {
                                $discord_args[ 'fields' ][] = [
                                    'name'   => $f[ 'type' ] == 'warning' ? 'Warning' : 'Broken Link',
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

            // Slack
            if ( get_option( 'blnotifier_enable_slack' ) ) {
                $SLACK = new BLNOTIFIER_SLACK;
                $slack_webhook = get_option( 'blnotifier_slack' );
                if ( $slack_webhook && $SLACK->sanitize_webhook_url( $slack_webhook ) != '' ) {
                    $slack_args = [
                        'title'  => $subject,
                        'source' => $source_url,
                        'fields' => []
                    ];
                    foreach ( $flagged as $key => $section ) {
                        foreach ( $section as $f ) {
                            if ( ( $f[ 'type' ] == 'broken' || $f[ 'type' ] == 'warning' ) && !$this->already_added( $f[ 'link' ] ) ) {
                                $slack_args[ 'fields' ][] = [
                                    'label' => $f[ 'type' ] == 'warning' ? 'Warning:' : 'Broken Link:',
                                    'link'  => $f[ 'link' ],
                                    'code'  => $f[ 'code' ],
                                    'text'  => $f[ 'text' ],
                                ];
                            }
                        }
                    }
                    if ( !empty( $slack_args[ 'fields' ] ) ) {
                        $slack_args = apply_filters( 'blnotifier_slack_args', $slack_args, $flagged, $source_url );
                        $send_to_slack = $SLACK->send( $slack_webhook, $slack_args );
                        do_action( 'blnotifier_slack_response', $send_to_slack );
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
                        'title'         => $subject,
                        'msg'           => $message,
                        'img_url'       => '',
                        'source_url'    => $source_url,
                        'facts'         => []
                    ];
                    foreach ( $flagged as $key => $section ) {
                        foreach ( $section as $f ) {
                            if ( ( $f[ 'type' ] == 'broken' || $f[ 'type' ] == 'warning' ) && !$this->already_added( $f[ 'link' ] ) ) {
                                $msteams_args[ 'facts' ][] = [
                                    'name'   => $f[ 'type' ] == 'warning' ? 'Warning:' : 'Broken Link:',
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
     * @param  WP_Error $wp_error
     * @return void
     */
    public function on_email_error( $wp_error ) {
        error_log( $wp_error->get_error_message() ); // phpcs:ignore 
    } // End on_email_error()


    /**
     * Get broken/warning counts, split internal vs external
     *
     * @return array
     */
    public function get_counts() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is a hardcoded prefix + fixed name, not user input; type values are bound via prepare().
        $total_broken  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE type = %s", 'broken' ) );
        $total_warning = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE type = %s", 'warning' ) );
        $total_all     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
        // phpcs:enable

        $internal_broken  = 0;
        $external_broken  = 0;
        $internal_warning = 0;
        $external_warning = 0;

        $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is a hardcoded prefix + fixed name, not user input; type value is bound via prepare().
        $broken_links = $wpdb->get_col( $wpdb->prepare( "SELECT link FROM $table_name WHERE type = %s", 'broken' ) );
        // phpcs:enable
        foreach ( $broken_links as $link ) {
            if ( $LINK_BROWSER->determine_type( $link ) === 'internal' ) {
                $internal_broken++;
            } else {
                $external_broken++;
            }
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is a hardcoded prefix + fixed name, not user input; type value is bound via prepare().
        $warning_links = $wpdb->get_col( $wpdb->prepare( "SELECT link FROM $table_name WHERE type = %s", 'warning' ) );
        // phpcs:enable
        foreach ( $warning_links as $link ) {
            if ( $LINK_BROWSER->determine_type( $link ) === 'internal' ) {
                $internal_warning++;
            } else {
                $external_warning++;
            }
        }

        return [
            'total_all'        => $total_all,
            'total_broken'     => $total_broken,
            'internal_broken'  => $internal_broken,
            'external_broken'  => $external_broken,
            'total_warning'    => $total_warning,
            'internal_warning' => $internal_warning,
            'external_warning' => $external_warning,
        ];
    } // End get_counts()


    /**
     * Render the status count filter links
     *
     * @param array $counts
     * @param string $current_filter
     * @param boolean $warnings_enabled
     * @return void
     */
    public function render_status_counts( $counts, $current_filter, $warnings_enabled ) {
        $links = [
            'all'               => [ 'label' => __( 'All', 'broken-link-notifier' ), 'count' => $counts[ 'total_all' ] ],
            'broken'            => [ 'label' => __( 'Broken', 'broken-link-notifier' ), 'count' => $counts[ 'total_broken' ] ],
            'internal-broken'   => [ 'label' => __( 'Broken - Internal', 'broken-link-notifier' ), 'count' => $counts[ 'internal_broken' ] ],
            'external-broken'   => [ 'label' => __( 'Broken - External', 'broken-link-notifier' ), 'count' => $counts[ 'external_broken' ] ],
        ];

        if ( $warnings_enabled ) {
            $links[ 'warning' ]          = [ 'label' => __( 'Warnings', 'broken-link-notifier' ), 'count' => $counts[ 'total_warning' ] ];
            $links[ 'internal-warning' ] = [ 'label' => __( 'Warnings - Internal', 'broken-link-notifier' ), 'count' => $counts[ 'internal_warning' ] ];
            $links[ 'external-warning' ] = [ 'label' => __( 'Warnings - External', 'broken-link-notifier' ), 'count' => $counts[ 'external_warning' ] ];
        }

        $keys = array_keys( $links );
        $last_key = end( $keys );

        echo '<ul class="subsubsub">';
        foreach ( $links as $key => $link ) {
            $is_current = ( $current_filter === $key );
            $class = $is_current ? ' class="current"' : '';
            $aria = $is_current ? ' aria-current="page"' : '';
            echo '<li class="'.esc_attr( $key ).'">';
            echo '<a href="#" data-filter="'.esc_attr( $key ).'" class="bln-status-filter'.( $is_current ? ' current' : '' ).'"'.esc_attr( $aria ).'>'.esc_html( $link[ 'label' ] ).' <span class="count">('.absint( $link[ 'count' ] ).')</span></a>';
            if ( $key !== $last_key ) {
                echo ' |';
            }
            echo '</li>';
        }
        echo '</ul>';
    } // End render_status_counts()


    /**
     * Get the valid per-page choices, and coerce a given value to the nearest valid one
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_per_page( $value ) {
        $value = absint( $value );
        $allowed = (new BLNOTIFIER_HELPERS)->is_test_mode() ? [ 2, 10, 25, 50, 100 ] : [ 10, 25, 50, 100 ];

        if ( in_array( $value, $allowed, true ) ) {
            return $value;
        }

        return 25;
    } // End sanitize_per_page()


    /**
     * Render the tablenav row (bulk actions, per page, pagination) - top or bottom
     *
     * @param string $position 'top' or 'bottom'
     * @param int $per_page
     * @return void
     */
    public function render_tablenav( $position, $per_page ) {
        $is_test_mode = (new BLNOTIFIER_HELPERS)->is_test_mode();
        ?>
        <div class="tablenav <?php echo esc_attr( $position ); ?>">
            <div class="alignleft actions bulkactions">
                <label for="bln-bulk-action-<?php echo esc_attr( $position ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'broken-link-notifier' ); ?></label>
                <select class="bln-bulk-action" id="bln-bulk-action-<?php echo esc_attr( $position ); ?>">
                    <option value=""><?php echo esc_html__( 'Bulk actions', 'broken-link-notifier' ); ?></option>
                    <option value="clear"><?php echo esc_html__( 'Clear Results', 'broken-link-notifier' ); ?></option>
                    <option value="omit-links"><?php echo esc_html__( 'Omit Links', 'broken-link-notifier' ); ?></option>
                    <option value="omit-sources"><?php echo esc_html__( 'Omit Sources', 'broken-link-notifier' ); ?></option>
                </select>
                <input type="button" class="button action button-compact bln-apply-bulk" value="<?php echo esc_attr__( 'Apply', 'broken-link-notifier' ); ?>" disabled>
            </div>
            <div class="alignleft actions">
                <label for="bln-results-per-page-<?php echo esc_attr( $position ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Results per page', 'broken-link-notifier' ); ?></label>
                <select class="bln-results-per-page" id="bln-results-per-page-<?php echo esc_attr( $position ); ?>" autocomplete="off">
                    <?php if ( $is_test_mode ) : ?>
                        <option value="2"<?php selected( $per_page, 2 ); ?>><?php echo esc_html__( '2 per page', 'broken-link-notifier' ); ?></option>
                    <?php endif; ?>
                    <option value="10"<?php selected( $per_page, 10 ); ?>><?php echo esc_html__( '10 per page', 'broken-link-notifier' ); ?></option>
                    <option value="25"<?php selected( $per_page, 25 ); ?>><?php echo esc_html__( '25 per page', 'broken-link-notifier' ); ?></option>
                    <option value="50"<?php selected( $per_page, 50 ); ?>><?php echo esc_html__( '50 per page', 'broken-link-notifier' ); ?></option>
                    <option value="100"<?php selected( $per_page, 100 ); ?>><?php echo esc_html__( '100 per page', 'broken-link-notifier' ); ?></option>
                </select>
            </div>
            <div class="tablenav-pages bln-results-pagination"></div>
            <br class="clear">
        </div>
        <?php
    } // End render_tablenav()


    /**
     * Render result rows (used by both the initial page load and the ajax table)
     *
     * @param array $links
     * @return void
     */
    public function render_rows( $links ) {
        if ( empty( $links ) ) {
            echo '<tr><td colspan="7"><em>' . esc_html__( 'No results found.', 'broken-link-notifier' ) . '</em></td></tr>'; // phpcs:ignore
            return;
        }

        foreach ( $links as $link ) :

            $source_url = filter_var( $link->source, FILTER_SANITIZE_URL );
            $source_url = remove_query_arg( (new BLNOTIFIER_HELPERS)->get_qs_to_remove_from_source(), $source_url );
            $source_id = url_to_postid( $source_url );
            $post_type_name = $source_id ? (new BLNOTIFIER_HELPERS)->get_post_type_name( get_post_type( $source_id ), true ) : '--';

            // Type + code
            $type_label = '';
            switch ( $link->type ) {
                case 'broken': $type_label = '<div class="bln-type broken">' . esc_html__( 'Broken', 'broken-link-notifier' ) . '</div>'; break;
                case 'warning': $type_label = '<div class="bln-type warning">' . esc_html__( 'Warning', 'broken-link-notifier' ) . '</div>'; break;
                case 'good': $type_label = '<div class="bln-type good">' . esc_html__( 'Good', 'broken-link-notifier' ) . '</div>'; break;
            }

            $code = absint( $link->code );
            $code_link = $code;
            $incl_title = '';

            if ( $code != 0 && $code != 666 ) {
                $code_link = '<a href="https://http.dev/'.$code.'" target="_blank">'.$code.'</a>';
            } elseif ( $code == 666 ) {
                $incl_title = ' title="' . __( 'A status code of 666 is a code we use to force invalid URL code 0 to be a broken link. It is not an official status code.', 'broken-link-notifier' ) . '"';
            } elseif ( $code == 0 ) {
                $incl_title = ' title="' . __( 'A status code of 0 means there was no response and it can occur for various reasons, like request time outs. It almost always means something is randomly interfering with the user\'s connection, like a proxy server / firewall / load balancer / laggy connection / network congestion, etc.', 'broken-link-notifier' ) . '"';
            }

            // Method
            switch ( sanitize_key( $link->method ) ) {
                case 'visit': $method_label = __( 'Front-End Visit', 'broken-link-notifier' ); break;
                case 'multi': $method_label = __( 'Multi-Scan', 'broken-link-notifier' ); break;
                case 'single': $method_label = __( 'Page Scan', 'broken-link-notifier' ); break;
                case 'site-scan': $method_label = __( 'Site Scan', 'broken-link-notifier' ); break;
                default: $method_label = __( 'Unknown', 'broken-link-notifier' ); 
            }

            // Actions for link
            $link_actions = [];
            $link_actions[] = '<span class="clear-result"><a href="#" data-link="'.esc_attr( $link->link ).'">' . __( 'Clear Result', 'broken-link-notifier' ) . '</a></span>';
            $link_actions[] = '<span class="omit-link"><a href="#" data-link="'.esc_attr( $link->link ).'">' . __( 'Omit Link', 'broken-link-notifier' ) . '</a></span>';
            $link_actions[] = '<span class="replace-link"><a href="#" data-link="'.esc_attr( $link->link ).'">' . __( 'Replace Link', 'broken-link-notifier' ) . '</a></span>';

            $source_title = get_the_title( $source_id );

            // Actions for source
            $source_actions = [];
            if ( $source_id ) {
                $source_actions[] = '<span class="view"><a href="'.add_query_arg( 'blink', $link->link, get_permalink( $source_id ) ).'" target="_blank">' . __( 'View Page', 'broken-link-notifier' ) . '</a></span>';
                if ( !(new BLNOTIFIER_OMITS)->is_omitted( $source_url, 'pages' ) ) {
                    $source_actions[] = '<span class="omit"><a class="omit-page" href="#" data-link="'.$source_url.'">' . __( 'Omit Source', 'broken-link-notifier' ) . '</a></span>';
                }
                $scan_nonce = wp_create_nonce( 'blnotifier_scan_single' );
                $source_actions[] = '<span class="scan"><a class="scan-page" href="'.(new BLNOTIFIER_MENU)->get_plugin_page( 'scan-single' ).'&scan='.$source_url.'&_wpnonce='.$scan_nonce.'" target="_blank">' . __( 'Scan Page', 'broken-link-notifier' ) . '</a></span>';
                $source_actions[] = '<span class="edit"><a href="'.get_edit_post_link( $source_id ).'">' . __( 'Edit Page', 'broken-link-notifier' ) . '</a></span>';
                if ( get_option( 'blnotifier_enable_delete_source' ) && current_user_can( 'delete_post', $source_id ) ) {
                    $delete_nonce = wp_create_nonce( 'blnotifier_delete_source' );
                    $source_actions[] = '<span class="delete"><a href="#" class="delete-source" data-source-title="'.$source_title.'" data-source-id="'.$source_id.'">' . __( 'Trash Page', 'broken-link-notifier' ) . '</a></span>';
                }
            }
            ?>
            <tr id="link-<?php echo esc_attr( $link->id ); ?>" class="link-row pending" data-link="<?php echo esc_attr( $link->link ); ?>" data-link-id="<?php echo esc_attr( $link->id ); ?>">
                <th scope="row" class="check-column">
                    <input type="checkbox" id="cb-select-<?php echo esc_attr( $link->id ); ?>" class="bln-row-checkbox" name="bln_selected[]" value="<?php echo esc_attr( $link->id ); ?>" />
                </th>
                <td class="type">
                    <?php echo wp_kses_post( $type_label ); ?> <code class="code"<?php echo wp_kses_post( $incl_title ); ?>><?php echo esc_html__( 'Code:', 'broken-link-notifier' ); ?> <?php echo wp_kses_post( $code_link ); ?></code> <span class="message"><?php echo esc_html( $link->text ); ?></span>
                </td>
                <td class="link">
                    <a href="<?php echo esc_url( $link->link ); ?>" class="link-url" target="_blank" rel="noopener"><?php echo esc_html( $link->link ); ?></a>
                    <div class="row-actions"><?php echo wp_kses_post( implode( ' | ', $link_actions ) ); ?></div>
                </td>
                <td class="source" data-source-id="<?php echo esc_attr( $source_id ); ?>">
                    <a href="<?php echo esc_url( $source_url ); ?>" class="source-url" target="_blank" rel="noopener"><?php echo esc_html( $source_id ? $source_title : $source_url ); ?></a>
                    <?php if ( $source_actions ) : ?>
                        <div class="row-actions"><?php echo wp_kses_post( implode( ' | ', $source_actions ) ); ?></div>
                    <?php endif; ?>
                </td>
                <td class="source_pt"><?php echo esc_html( $post_type_name ); ?></td>
                <td class="method"><?php echo esc_html( $method_label ); ?></td>
                <td class="date">
                    <?php
                    if ( isset( $link->created_at ) ) {
                        $date_timestamp = strtotime( $link->created_at );
                        $days_broken    = (int) floor( ( time() - $date_timestamp ) / DAY_IN_SECONDS );
                        echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date_timestamp ) );
                        echo '<br><em style="font-size:11px;color:#888;">';
                        if ( $days_broken === 0 ) {
                            echo esc_html__( 'Broken today', 'broken-link-notifier' );
                        } else {
                            echo esc_html( sprintf(
                                /* translators: %d: number of days the link has been broken */
                                _n( 'Broken for %d day', 'Broken for %d days', $days_broken, 'broken-link-notifier' ),
                                $days_broken
                            ) );
                        }
                        echo '</em>';
                    } else {
                        echo esc_html__( 'Date Unknown', 'broken-link-notifier' );
                    }
                    ?>
                </td>
                <td class="verify">
                    <span id="bln-verify-<?php echo esc_attr( $link->id ); ?>" class="bln-verify" data-type="<?php echo esc_attr( $link->type ); ?>" data-link="<?php echo esc_html( $link->link ); ?>" data-link-id="<?php echo esc_html( $link->id ); ?>" data-code="<?php echo esc_attr( $link->code ); ?>" data-source-id="<?php echo esc_attr( $source_id ); ?>" data-method="<?php echo esc_attr( $link->method ); ?>"><?php esc_html_e( 'Pending', 'broken-link-notifier' ); ?></span>
                </td>
            </tr>
        <?php endforeach;
    } // End render_rows()


    /**
     * Render the Verify/Pause and Export buttons in the right subheader
     *
     * @param string $active_tab
     * @return void
     */
    public function render_subheader_right( $active_tab ) {
        if ( $active_tab !== 'results' ) {
            return;
        }

        $user_id = get_current_user_id();
        $dismissed = get_user_meta( $user_id, 'blnotifier_verify_notice_dismissed', true );
        ?>
        <?php if ( !$dismissed ) : ?>
            <span id="bln-verify-notice-wrap">
                <span id="bln-verify-notice" class="blnotifier-scan-reminder">
                    <?php echo esc_html__( 'Links are no longer auto-verified on page load — click "Verify Link Statuses"', 'broken-link-notifier' ); ?>
                    <button type="button" id="bln-verify-notice-dismiss" aria-label="Dismiss">&times;</button>
                </span>
                <span class="bln-verify-notice-arrow" aria-hidden="true">&rarr;</span>
            </span>
        <?php endif; ?>
        <button type="button" id="bln-toggle-verification" class="blnotifier-button"><?php echo esc_html__( 'Verify Link Statuses', 'broken-link-notifier' ); ?></button>
        <a href="#" id="bln-export-results" class="blnotifier-button"><?php echo esc_html__( 'Export to CSV', 'broken-link-notifier' ); ?></a>
        <?php
    } // End render_subheader_right()


    /**
     * Ajax: dismiss the verify notice
     *
     * @return void
     */
    public function ajax_dismiss_verify_notice() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), 'blnotifier_dismiss_verify_notice' ) ) {
            wp_send_json_error( [ 'msg' => esc_html__( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => esc_html__( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        update_user_meta( get_current_user_id(), 'blnotifier_verify_notice_dismissed', 1 );

        wp_send_json_success();
    } // End ajax_dismiss_verify_notice()


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
            exit( esc_html__( 'No naughty business please.', 'broken-link-notifier' ) );
        }

        // Public endpoint: allow guests, but validate capability for logged-in users.
        if ( is_user_logged_in() && ! current_user_can( 'read' ) ) {
            $result = [
                'type' => 'error',
                'msg'  => __( 'Permission denied', 'broken-link-notifier' )
            ];
            self::send_ajax_or_redirect( $result );
        }
    
        // Get the source and links
        $source_url    = isset( $_REQUEST[ 'source_url' ] ) ? filter_var( wp_unslash( $_REQUEST[ 'source_url' ] ), FILTER_SANITIZE_URL ) : '';
        $header_links  = isset( $_REQUEST[ 'header_links' ] ) ? wp_unslash( $_REQUEST[ 'header_links' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content_links = isset( $_REQUEST[ 'content_links' ] ) ? wp_unslash( $_REQUEST[ 'content_links' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $footer_links  = isset( $_REQUEST[ 'footer_links' ] ) ? wp_unslash( $_REQUEST[ 'footer_links' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Permissions
        $user_can_manage = (new BLNOTIFIER_HELPERS)->user_can_manage_broken_links();

        // Enforce max links per page
        $max_links = absint( get_option( 'blnotifier_max_links_per_page', 200 ) );
        $total_links = count( $header_links ) + count( $content_links ) + count( $footer_links );
        if ( $total_links > $max_links ) {
            /* translators: %d: maximum number of links allowed per scan */
            $error_msg = sprintf( __( 'Too many links in one scan. Max allowed: %d.', 'broken-link-notifier' ), $max_links );

            // If the user is an admin/manager, append the instruction
            if ( $user_can_manage ) {
                $error_msg .= ' ' . __( 'You can increase this limit in the plugin settings.', 'broken-link-notifier' );
            }

            $result = [
                'type' => 'error',
                'msg'  => $error_msg
            ];
            
            self::send_ajax_or_redirect( $result );
        }

        // Rate limit per IP only for non-link-managers
        if ( !$user_can_manage ) {
            $ip = isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ 'REMOTE_ADDR' ] ) ) : '';
            $transient_key = 'bln_rate_' . md5( $ip );
            if ( get_transient( $transient_key ) ) {
                $result = [
                    'type' => 'error',
                    'msg'  => __( 'Scan rate limit exceeded', 'broken-link-notifier' )
                ];
                self::send_ajax_or_redirect( $result );
            }
            set_transient( $transient_key, 1, 10 ); // 10-second cooldown
        }

        // Make sure we have a source URL
        if ( $source_url ) {

            // Only allow webpages, not file:///, etc.
            if ( !str_starts_with( $source_url, 'http' ) ) {
                $result = [
                    'type' => 'error',
                    'msg'  => __( 'Invalid source: ', 'broken-link-notifier' ) . esc_html( $source_url )
                ];
                self::send_ajax_or_redirect( $result );
            }

            // Validate that the URL belongs to this site and exists
            $site_url = site_url();
            if ( ! str_starts_with( $source_url, $site_url ) ) {
                $result = [
                    'type' => 'error',
                    'msg'  => __( 'External source URLs are not permitted.', 'broken-link-notifier' )
                ];
                self::send_ajax_or_redirect( $result );
            }

            // Check for Post ID with full URL
            $post_id = url_to_postid( $source_url );

            // If not found, check without query parameters
            if ( ! $post_id ) {
                $clean_url = strtok( $source_url, '?' );
                $post_id  = url_to_postid( $clean_url );
            }

            // If it's not a post/page and it's not the homepage, it's likely an archive page, 404 or invalid
            if ( ! $post_id && $source_url !== trailingslashit( $site_url ) && $source_url !== $site_url ) {
                $result = [
                    'type' => 'success',
                    'msg'  => __( 'Skipping because source URL is not a valid post or page.', 'broken-link-notifier' )
                ];
                self::send_ajax_or_redirect( $result );
            }

            // Initiate helpers
            $HELPERS = new BLNOTIFIER_HELPERS;

            // Codes
            $show_good_links_in_results = get_option( 'blnotifier_enable_good_links' );
            $warnings_enabled_check = filter_var( get_option( 'blnotifier_enable_warnings' ), FILTER_VALIDATE_BOOLEAN );

            // Start timing
            $start = $HELPERS->start_timer();

            // Store the links we're going to notify
            $notify = [];
            $broken_links = [];
            $warning_links = [];
            $good_links = [];
            $count_links = 0;
            $count_notify = 0;

            // Header links
            if ( !empty( $header_links ) ) {
                foreach ( $header_links as &$header_link ) {
                    $count_links++;
                    $header_link = $HELPERS->sanitize_link( $header_link );
                    if ( $header_link === '' ) {
                        continue;
                    }
                    $status = $HELPERS->check_link( $header_link );
                    if ( $status[ 'type' ] === 'broken' ) {
                        $count_notify++;
                        $notify[ 'header' ][] = $status;
                        $broken_links[ 'header' ][] = $status;
                    } elseif ( $status[ 'type' ] === 'warning' ) {
                        if ( $warnings_enabled_check ) {
                            $count_notify++;
                            $notify[ 'header' ][] = $status;
                        }
                        $warning_links[ 'header' ][] = $status;
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
                    if ( $content_link === '' ) {
                        continue;
                    }
                    $status = $HELPERS->check_link( $content_link );
                    if ( $status[ 'type' ] === 'broken' ) {
                        $count_notify++;
                        $notify[ 'content' ][] = $status;
                        $broken_links[ 'content' ][] = $status;
                    } elseif ( $status[ 'type' ] === 'warning' ) {
                        if ( $warnings_enabled_check ) {
                            $count_notify++;
                            $notify[ 'content' ][] = $status;
                        }
                        $warning_links[ 'content' ][] = $status;
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
                    if ( $footer_link === '' ) {
                        continue;
                    }
                    $status = $HELPERS->check_link( $footer_link );
                    if ( $status[ 'type' ] === 'broken' ) {
                        $count_notify++;
                        $notify[ 'footer' ][] = $status;
                        $broken_links[ 'footer' ][] = $status;
                    } elseif ( $status[ 'type' ] === 'warning' ) {
                        if ( $warnings_enabled_check ) {
                            $count_notify++;
                            $notify[ 'footer' ][] = $status;
                        }
                        $warning_links[ 'footer' ][] = $status;
                    } else {
                        $good_links[ 'footer' ][] = $status;
                    }
                }
            }

            // Notify
            $all_links = array_merge( $header_links, $content_links, $footer_links );
            $this->notify( $notify, $count_notify, $all_links, $source_url );

            $current_user_id = get_current_user_id();

            // Add broken links
            foreach ( $broken_links as $location => $items ) {
                foreach ( $items as $status ) {
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

            // Add warning links, only if warnings are enabled
            if ( $warnings_enabled_check ) {
                foreach ( $warning_links as $location => $items ) {
                    foreach ( $items as $status ) {
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

            // Add good links, only if showing good links is enabled
            if ( $show_good_links_in_results ) {
                foreach ( $good_links as $location => $gl ) {
                    foreach ( $gl as $status ) {
                        if ( empty( $status[ 'link' ] ) || $status[ 'link' ] === 'Unknown' || $status[ 'type' ] === 'omitted' ) {
                            continue;
                        }
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
            $result[ 'scanned' ] = [
                'header'  => $header_links ?: [],
                'content' => $content_links ?: [],
                'footer'  => $footer_links ?: [],
            ];
            $result[ 'results' ] = [
                'broken'  => $broken_links,
                'warning' => $warning_links,
                'good'    => $good_links,
            ];
            $result[ 'warnings_enabled' ] = $warnings_enabled_check;
            $result[ 'status_codes' ] = [
                'broken'  => $HELPERS->get_bad_status_codes() ?: [],
                'warning' => $HELPERS->get_warning_status_codes() ?: [],
            ] ?: [];
            // translators: 1: total scan time in seconds, 2: average seconds per link.
            $result[ 'timing' ] = sprintf( __( 'Results were generated in %1$s seconds (%2$s/link)', 'broken-link-notifier' ), $total_time, $sec_per_link );

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = __( 'No source url', 'broken-link-notifier' );
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
            exit( esc_html__( 'No naughty business please.', 'broken-link-notifier' ) );
        }

        // Check permissions
        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( !$HELPERS->user_can_manage_broken_links() ) {
            exit( esc_html__( 'Unauthorized access.', 'broken-link-notifier' ) );
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
                $remove = $this->remove( $HELPERS->str_replace_on_link( $link ), $link_id );
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
                    $result[ 'msg' ] = __( 'Could not auto-remove link. Link not found in DB.', 'broken-link-notifier' );
                }

            // Source exists
            } else {

                // Check status
                $status = $HELPERS->check_link( $link );
                
                // If it's good now, remove the old post
                if ( $status[ 'type' ] == 'good' || $status[ 'type' ] == 'omitted' ) {
                    $remove = $this->remove( $HELPERS->str_replace_on_link( $link ), $link_id );
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
                    $remove = $this->remove( $HELPERS->str_replace_on_link( $link ), $link_id );
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
        if ( ! isset( $_REQUEST[ 'nonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce_replace ) ) {
            wp_send_json_error( [ 'msg' => __( 'No naughty business please.', 'broken-link-notifier' ) ] );
        }

        $HELPERS = new BLNOTIFIER_HELPERS();
        if ( ! $HELPERS->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized access.', 'broken-link-notifier' ) ] );
        }

        $link_id   = isset( $_REQUEST[ 'linkID' ] ) ? absint( wp_unslash( $_REQUEST[ 'linkID' ] ) ) : false;
        $old_link  = isset( $_REQUEST[ 'oldLink' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'oldLink' ] ) ) : false;
        $new_link  = isset( $_REQUEST[ 'newLink' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'newLink' ] ) ) : false;
        $source_id = isset( $_REQUEST[ 'sourceID' ] ) ? absint( wp_unslash( $_REQUEST[ 'sourceID' ] ) ) : false;

        if ( ! $old_link || ! $new_link || ! $source_id ) {
            wp_send_json_error( [ 'msg' => __( 'Missing required parameters.', 'broken-link-notifier' ) ] );
        }

        $post = get_post( $source_id );
        if ( ! $post ) {
            wp_send_json_error( [ 'msg' => __( 'Source post not found.', 'broken-link-notifier' ) ] );
        }

        $updated = false;
        $details = [];

        // 1. Standard WordPress Content
        $post_content = $post->post_content;
        if ( strpos( $post_content, $old_link ) !== false ) {
            $details[] = __( 'Found in standard WordPress post content.', 'broken-link-notifier' );
            $new_content = str_replace( $old_link, $new_link, $post_content );
            $result      = wp_update_post( [
                'ID'           => $source_id,
                'post_content' => $new_content,
            ] );

            if ( is_wp_error( $result ) ) {
                $details[] = __( 'Failed to update standard post content. WP_Error: ', 'broken-link-notifier' ) . $result->get_error_message();
            } else {

                // VERIFICATION: Pull fresh from DB
                $verified_content = get_post_field( 'post_content', $source_id );
                if ( strpos( $verified_content, $old_link ) === false ) {
                    $updated   = true;
                    $details[] = __( "Verified: Old link replaced in standard content.", 'broken-link-notifier' );
                } else {
                    $details[] = __( "Old link still exists in standard content after attempt to update.", 'broken-link-notifier' );
                }
            }
        } else {
            $details[] = __( 'Old link not found in standard WordPress post content.', 'broken-link-notifier' );
        }

        // 2. Cornerstone / X-Theme Data
        $cornerstone_data = get_post_meta( $source_id, '_cornerstone_data', true );
        if ( ! empty( $cornerstone_data ) ) {
            $details[] = __( 'Checking Cornerstone data...', 'broken-link-notifier' );

            $escaped_old = str_replace( '/', '\/', $old_link );
            $escaped_new = str_replace( '/', '\/', $new_link );

            if ( strpos( $cornerstone_data, $old_link ) !== false || strpos( $cornerstone_data, $escaped_old ) !== false ) {
                
                $updated_cornerstone = str_replace( $old_link, $new_link, $cornerstone_data );
                $updated_cornerstone = str_replace( $escaped_old, $escaped_new, $updated_cornerstone );

                $cs_result = update_post_meta( $source_id, '_cornerstone_data', wp_slash($updated_cornerstone) );

                if ( $cs_result ) {
                    $updated = true;
                    $details[] = __( "Verified: Link replaced in Cornerstone metadata.", 'broken-link-notifier' );
                    
                    // Cornerstone/X-Theme usually requires a cache clear or a 're-save' 
                    // to update the generated post_content.
                    if ( class_exists( 'Cornerstone_Common' ) ) {
                        delete_post_meta( $source_id, '_cornerstone_override' );
                    }
                } else {
                    $details[] = __( 'Found link in Cornerstone, but failed to update meta.', 'broken-link-notifier' );
                }
            }
        }

        // 3. Elementor Data (JSON Meta)
        if ( is_plugin_active( 'elementor/elementor.php' ) ) {
            $details[] = __( 'Checking Elementor data meta...', 'broken-link-notifier' );

            $elementor_data = get_post_meta( $source_id, '_elementor_data', true );
            if ( ! empty( $elementor_data ) ) {
                $details[] = __( 'Found in Elementor data meta.', 'broken-link-notifier' );

                $escaped_old = str_replace( '/', '\/', $old_link );
                $escaped_new = str_replace( '/', '\/', $new_link );

                $found_raw     = ( strpos( $elementor_data, $old_link ) !== false );
                $found_escaped = ( strpos( $elementor_data, $escaped_old ) !== false );

                if ( $found_raw ) {
                    $details[] = __( 'Old link found in raw form in Elementor data.', 'broken-link-notifier' );
                } elseif ( $found_escaped ) {
                    $details[] = __( 'Old link found in escaped form in Elementor data.', 'broken-link-notifier' );
                } else {
                    $details[] = __( 'Old link not found in raw or escaped form in Elementor data.', 'broken-link-notifier' );
                }

                if ( $found_raw || $found_escaped ) {
                    $data = str_replace( $old_link, $new_link, $elementor_data );
                    $data = str_replace( $escaped_old, $escaped_new, $data );

                    $meta_result = update_post_meta( $source_id, '_elementor_data', wp_slash( $data ) );

                    if ( $meta_result ) {

                        // VERIFICATION: Pull fresh meta
                        $verified_meta = get_post_meta( $source_id, '_elementor_data', true );
                        if ( strpos( $verified_meta, $old_link ) === false && strpos( $verified_meta, $escaped_old ) === false ) {
                            if ( class_exists( '\Elementor\Plugin' ) ) {
                                \Elementor\Plugin::$instance->posts_css_manager->clear_cache();
                            }
                            $updated   = true;
                            $details[] = __( "Verified: Link removed from Elementor metadata.", 'broken-link-notifier' );
                        } else {
                            $details[] = __( "Critical: Link still exists in Elementor meta after update.", 'broken-link-notifier' );
                        }
                    } else {
                        $details[] = __( 'Failed to update Elementor meta data.', 'broken-link-notifier' );
                    }
                } else {
                    $details[] = __( 'Old link not found in Elementor meta data.', 'broken-link-notifier' );
                }
            } else {
                $details[] = __( 'Old link not found in Elementor data meta.', 'broken-link-notifier' );
            }
        }

        if ( $updated ) {
            $this->remove( $HELPERS->str_replace_on_link( $old_link ), $link_id );

            wp_send_json_success( [
                'linkID'   => $link_id,
                'msg'      => __( 'Link replaced successfully.', 'broken-link-notifier' ),
                'details'  => $details
            ] );
        }

        // If we reach here, something went wrong
        wp_send_json_error( [ 'msg' => implode( "\n", $details ) ] );
    } // End ajax_replace_link()


    /**
     * Ajax call for back end
     *
     * @return void
     */
    public function ajax_delete_result() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce_delete ) ) {
            exit( esc_html__( 'No naughty business please.', 'broken-link-notifier' ) );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( !$HELPERS->user_can_manage_broken_links() ) {
            exit( esc_html__( 'Unauthorized access.', 'broken-link-notifier' ) );
        }
    
        // Remove the link
        $link = isset( $_REQUEST[ 'link' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'link' ] ) ) : false;
        $link_id = isset( $_REQUEST[ 'linkID' ] ) ? absint( wp_unslash( $_REQUEST[ 'linkID' ] ) ) : false;
        
        if ( $link ) {
            $this->remove( $HELPERS->str_replace_on_link( $link ), $link_id );
            wp_send_json_success();
        }

        // Failure
        wp_send_json_error( __( 'Failed to delete.', 'broken-link-notifier' ) );
    } // End ajax_delete_result()


    /**
     * Ajax call for back end
     *
     * @return void
     */
    public function ajax_delete_source() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce_delete ) ) {
            exit( esc_html__( 'No naughty business please.', 'broken-link-notifier' ) );
        }

        // Check permissions
        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            exit( esc_html__( 'Unauthorized access.', 'broken-link-notifier' ) );
        }

        // Make sure we are allowed to delete the source
        if ( !get_option( 'blnotifier_enable_delete_source' ) ) {
            wp_send_json_error( __( 'Deleting source is not enabled.', 'broken-link-notifier' ) );
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
        wp_send_json_error( __( 'Failed to delete.', 'broken-link-notifier' ) );
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
     * Ajax: get a filtered/paginated page of results
     *
     * @return void
     */
    public function ajax_table() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce_table ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        $filter   = isset( $_REQUEST[ 'filter' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'filter' ] ) ) : 'all';
        $page     = isset( $_REQUEST[ 'page' ] ) ? absint( wp_unslash( $_REQUEST[ 'page' ] ) ) : 1;
        $page     = max( 1, $page );
        $per_page = isset( $_REQUEST[ 'per_page' ] ) ? $this->sanitize_per_page( absint( wp_unslash( $_REQUEST[ 'per_page' ] ) ) ) : $this->sanitize_per_page( get_option( 'blnotifier_per_page', 25 ) );

        update_option( 'blnotifier_per_page', $per_page );

        // Determine type/scope from the filter key
        $type  = 'all';
        $scope = 'all';
        if ( strpos( $filter, 'internal-' ) === 0 ) {
            $scope = 'internal';
            $type = str_replace( 'internal-', '', $filter );
        } elseif ( strpos( $filter, 'external-' ) === 0 ) {
            $scope = 'external';
            $type = str_replace( 'external-', '', $filter );
        } elseif ( $filter === 'broken' || $filter === 'warning' ) {
            $type = $filter;
        }

        $where_sql = 'WHERE 1=1';
        $where_values = [];

        if ( $type === 'broken' || $type === 'warning' ) {
            $where_sql .= ' AND type = %s';
            $where_values[] = $type;
        }

        // No internal/external scope: simple SQL pagination
        if ( $scope === 'all' ) {

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $table_name is a hardcoded prefix + fixed name, not user input; $where_sql is built from a fixed literal, not user input; type value is bound via prepare() when present.
            $total = !empty( $where_values )
                ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name $where_sql", $where_values ) )
                : (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where_sql" );

            $offset = ( $page - 1 ) * $per_page;
            $query_values = array_merge( $where_values, [ $per_page, $offset ] );
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name $where_sql ORDER BY created_at ASC LIMIT %d OFFSET %d", $query_values ) );
            // phpcs:enable

        // Internal/external scope: filter in PHP, then paginate manually
        } else {

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table_name is a hardcoded prefix + fixed name, not user input; $where_sql is built from a fixed literal, not user input; type value is bound via prepare() when present.
            $all_rows = !empty( $where_values ) ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name $where_sql ORDER BY created_at ASC", $where_values ) ) : $wpdb->get_results( "SELECT * FROM $table_name $where_sql ORDER BY created_at ASC" );
            // phpcs:enable

            $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
            $filtered_rows = array_values( array_filter( $all_rows, function( $row ) use ( $LINK_BROWSER, $scope ) {
                return $LINK_BROWSER->determine_type( $row->link ) === $scope;
            } ) );

            $total = count( $filtered_rows );
            $offset = ( $page - 1 ) * $per_page;
            $rows = array_slice( $filtered_rows, $offset, $per_page );
        }

        ob_start();
        $this->render_rows( $rows );
        $rows_html = ob_get_clean();

        wp_send_json_success( [
            'rows'        => $rows_html,
            'total'       => $total,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
            'page'        => $page,
            'counts'      => $this->get_counts(),
        ] );
    } // End ajax_table()


    /**
     * Ajax: bulk action on selected result rows
     *
     * @return void
     */
    public function ajax_bulk_action() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce_bulk ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        if ( !$HELPERS->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $bulk_action = isset( $_REQUEST[ 'bulk_action' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'bulk_action' ] ) ) : '';
        $ids = isset( $_REQUEST[ 'ids' ] ) && is_array( $_REQUEST[ 'ids' ] ) ? array_map( 'absint', wp_unslash( $_REQUEST[ 'ids' ] ) ) : [];

        if ( empty( $ids ) || !$bulk_action ) {
            wp_send_json_error( [ 'msg' => __( 'Nothing selected.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE id IN ($placeholders)", $ids ) ); // phpcs:ignore

        $OMITS = new BLNOTIFIER_OMITS;

        switch ( $bulk_action ) {

            case 'clear':
                $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id IN ($placeholders)", $ids ) ); // phpcs:ignore
                break;

            case 'omit-links':
                foreach ( $rows as $row ) {
                    $OMITS->add( $row->link, 'links', 'scan-results' );
                }
                break;

            case 'omit-sources':
                $source_urls = [];
                foreach ( $rows as $row ) {
                    $source_url = remove_query_arg( $HELPERS->get_qs_to_remove_from_source(), $row->source );
                    if ( !in_array( $source_url, $source_urls, true ) ) {
                        $source_urls[] = $source_url;
                    }
                }
                foreach ( $source_urls as $source_url ) {
                    $OMITS->add( $source_url, 'pages', 'scan-results' );
                    $wpdb->delete( $table_name, [ 'source' => $source_url ], [ '%s' ] );
                }
                break;

            default:
                wp_send_json_error( [ 'msg' => __( 'Unknown bulk action.', 'broken-link-notifier' ) ] );
        }

        wp_send_json_success();
    } // End ajax_bulk_action()


    /**
     * Enqueue the admin bar node's CSS on the front-end
     *
     * @return void
     */
    public function enqueue_admin_bar_css_frontend() {
        if ( !is_admin_bar_showing() ) {
            return;
        }
        wp_enqueue_style( 'blnotifier-admin-bar', BLNOTIFIER_PLUGIN_CSS_PATH.'admin-bar.css', [], BLNOTIFIER_SCRIPT_VERSION );
    } // End enqueue_admin_bar_css_frontend()


    /**
     * Enqueue the admin bar node's CSS on the back-end
     *
     * @return void
     */
    public function enqueue_admin_bar_css_backend() {
        if ( !is_admin_bar_showing() ) {
            return;
        }
        wp_enqueue_style( 'blnotifier-admin-bar', BLNOTIFIER_PLUGIN_CSS_PATH.'admin-bar.css', [], BLNOTIFIER_SCRIPT_VERSION );
    } // End enqueue_admin_bar_css_backend()


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
        wp_enqueue_style( 'front_end_css', BLNOTIFIER_PLUGIN_CSS_PATH.'results-front.min.css', [], BLNOTIFIER_SCRIPT_VERSION );

        // Nonce
        $nonce = wp_create_nonce( $this->nonce_blinks );

        // Javascript
        $handle = 'front_end_js';
        if ( file_exists( BLNOTIFIER_PLUGIN_JS_ABSPATH.'results-front.min.js' ) ) {
            $js_path = BLNOTIFIER_PLUGIN_JS_PATH.'results-front.min.js';
        } else {
            $js_path = BLNOTIFIER_PLUGIN_JS_PATH.'results-front.js';
        }
        wp_register_script( $handle, $js_path, [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true ); 
        wp_localize_script( $handle, 'blnotifier_front_end', [
            'show_in_console' => filter_var( get_option( 'blnotifier_show_in_console' ), FILTER_VALIDATE_BOOLEAN ),
            'admin_dir'       => BLNOTIFIER_ADMIN_DIR,
            'scan_header'     => filter_var( get_option( 'blnotifier_scan_header' ), FILTER_VALIDATE_BOOLEAN ),
            'scan_footer'     => filter_var( get_option( 'blnotifier_scan_footer' ), FILTER_VALIDATE_BOOLEAN ),
            'elements'        => (new BLNOTIFIER_HELPERS)->get_html_link_sources(),
            'nonce'           => $nonce,
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'text'            => [
                'checking_paused'   => __( 'Looking for highlights; checking for broken links paused.', 'broken-link-notifier' ),
                'links_hidden'      => __( 'It looks like one or more of the links are hidden. To find them, try searching for it in your browser\'s Developer console.', 'broken-link-notifier' ),
                'glow_yellow'       => __( 'The element should glow yellow if it is visible on the page. If you do not see it on the page, then it is hidden somewhere. Check any JavaScript elements, too. You can try searching for it in your browser\'s Developer console.', 'broken-link-notifier' ),
                'plugin_name'       => BLNOTIFIER_NAME,
                'fetching_links'    => __( 'Fetching and scanning links... please wait. This may take a minute if there are a lot of links.', 'broken-link-notifier' ),
                'still_scanning'    => __( 'Still scanning. Please wait...', 'broken-link-notifier' ),
                'scan_complete'     => __( 'Scan Complete', 'broken-link-notifier' ),
                'details'           => __( 'Details', 'broken-link-notifier' ),
                'warnings_enabled'  => __( 'Warnings are currently ENABLED in Settings.', 'broken-link-notifier' ),
                'warnings_disabled' => __( 'Warnings are currently DISABLED in Settings.', 'broken-link-notifier' ),
                'unknown_error'     => __( 'Unknown error occurred.', 'broken-link-notifier' ),
                'scan_failed'       => __( 'Scan failed.', 'broken-link-notifier' ),
            ]
        ] );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $handle );
    } // End front_script_enqueuer()


    /**
     * Enque the JavaScript
     *
     * @param string $screen The current admin screen identifier.
     * @return void
     */
    public function back_script_enqueuer( $screen ) {
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();
        if ( ( $screen == $options_page && $tab == 'results' ) ) {
            wp_enqueue_style( 'blnotifier-results', BLNOTIFIER_PLUGIN_CSS_PATH.'results.css', [ 'blnotifier-theme' ], BLNOTIFIER_SCRIPT_VERSION );

            $handle = 'blnotifier_results_back_end_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'results-back.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
            wp_localize_script( $handle, 'blnotifier_back_end', [
                'nonce_rescan'  => wp_create_nonce( $this->nonce_rescan ),
                'nonce_replace' => wp_create_nonce( $this->nonce_replace ),
                'nonce_delete'  => wp_create_nonce( $this->nonce_delete ),
                'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                'text'          => [
                    'scanning_link'         => __( 'Scanning link', 'broken-link-notifier' ),
                    'saving_link'           => __( 'Saving new link', 'broken-link-notifier' ),
                    'details'               => __( 'Details', 'broken-link-notifier' ),
                    'link_replace'          => __( 'The old link has been replaced. Result will be removed after refresh.', 'broken-link-notifier' ),
                    'old_link'              => __( 'Old link', 'broken-link-notifier' ),
                    'unknown_error'         => __( 'Unknown error occurred.', 'broken-link-notifier' ),
                    'server_request_failed' => __( 'Something went wrong with the server request. Please try again.', 'broken-link-notifier' ),
                    'something_went_wrong'  => __( 'Something went wrong. Please try again.', 'broken-link-notifier' ),
                    'confirm_delete_page'   => __( 'Are you sure you want to delete the page?', 'broken-link-notifier' )
                ]
            ] );
            wp_enqueue_script( $handle );

            $counts = $this->get_counts();
            $per_page = $this->sanitize_per_page( get_option( 'blnotifier_per_page', 25 ) );

            $table_handle = 'blnotifier_results_table_script';
            wp_register_script( $table_handle, BLNOTIFIER_PLUGIN_JS_PATH.'results-table.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
            wp_localize_script( $table_handle, 'blnotifier_results_table', [
                'nonce_table'            => wp_create_nonce( $this->nonce_table ),
                'nonce_bulk'             => wp_create_nonce( $this->nonce_bulk ),
                'export_nonce'           => wp_create_nonce( 'blnotifier_export_nonce' ),
                'dismiss_notice_nonce'   => wp_create_nonce( 'blnotifier_dismiss_verify_notice' ),
                'warnings_enabled'       => filter_var( get_option( 'blnotifier_enable_warnings' ), FILTER_VALIDATE_BOOLEAN ),
                'initial_total'          => $counts[ 'total_all' ],
                'initial_total_pages'    => max( 1, (int) ceil( $counts[ 'total_all' ] / $per_page ) ),
                'ajaxurl'                => admin_url( 'admin-ajax.php' ),
                'text'                   => [
                    'loading'              => __( 'Loading...', 'broken-link-notifier' ),
                    'first_page'           => __( 'First page', 'broken-link-notifier' ),
                    'previous_page'        => __( 'Previous page', 'broken-link-notifier' ),
                    'next_page'            => __( 'Next page', 'broken-link-notifier' ),
                    'last_page'            => __( 'Last page', 'broken-link-notifier' ),
                    'clear_results'        => __( 'Clear the selected results? This does not fix the links on your site.', 'broken-link-notifier' ),
                    'omit_items'           => __( 'Are you sure? This will add the selected items to your omit list.', 'broken-link-notifier' ),
                    'applying'             => __( 'Applying...', 'broken-link-notifier' ),
                    'bulk_action_failed'   => __( 'Bulk action failed.', 'broken-link-notifier' ),
                    'verifying'            => __( 'Verifying...', 'broken-link-notifier' ),
                    'no_source'            => __( 'Source no longer exists, removed from list...', 'broken-link-notifier' ),
                    'link_good'            => __( 'Link is good, removed from list...', 'broken-link-notifier' ),
                    'link_omitted'         => __( 'Link is omitted, removed from list...', 'broken-link-notifier' ),
                    'failed_to_remove'     => __( 'Failed to remove link.', 'broken-link-notifier' ),
                    'diff_code'            => __( 'Link is still bad, but showing a different code.', 'broken-link-notifier' ),
                    'diff_type'            => __( 'Link is still bad, but showing a different type.', 'broken-link-notifier' ),
                    'old_code'             => __( 'Old code: ', 'broken-link-notifier' ),
                    'old_type'             => __( 'Old type: ', 'broken-link-notifier' ),
                    'new_code'             => __( 'New code: ', 'broken-link-notifier' ),
                    'new_type'             => __( 'New type: ', 'broken-link-notifier' ),
                    'code'                 => __( 'Code', 'broken-link-notifier' ),
                    'verify_link_statuses' => __( 'Verify Link Statuses', 'broken-link-notifier' ),
                    'pause_verification'   => __( 'Pause Verification', 'broken-link-notifier' ),
                ]
            ] );
            wp_enqueue_script( $table_handle );

            wp_enqueue_script( 'jquery' );
        }
    } // End back_script_enqueuer()
}