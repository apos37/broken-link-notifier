<?php
/**
 * Link Browser class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
add_action( 'init', function() {
    (new BLNOTIFIER_LINK_BROWSER)->init();
} );


/**
 * Main plugin class.
 */
class BLNOTIFIER_LINK_BROWSER {

    /**
     * The table name
     *
     * @var string
     */
    private $table_name = 'blnotifier_links';


    /**
     * Nonce used for all Link Browser ajax calls
     *
     * @var string
     */
    private $nonce = 'blnotifier_link_browser';


    /**
	 * Load on init
	 */
	public function init() {

        // Render subheader actions
        add_action( 'blnotifier_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'blnotifier_subheader_right', [ $this, 'render_subheader_right' ] );

        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Ajax
        add_action( 'wp_ajax_blnotifier_link_browser_get_queue', [ $this, 'ajax_get_queue' ] );
        add_action( 'wp_ajax_blnotifier_link_browser_scan_post', [ $this, 'ajax_scan_post' ] );
        add_action( 'wp_ajax_blnotifier_link_browser_finish', [ $this, 'ajax_finish_scan' ] );
        add_action( 'wp_ajax_blnotifier_link_browser_table', [ $this, 'ajax_table' ] );
        add_action( 'wp_ajax_blnotifier_link_browser_omit_link', [ $this, 'ajax_omit_link' ] );

	} // End init()


    /**
     * Render the scan button and progress message in the left subheader
     *
     * @param string $active_tab
     * @return void
     */
    public function render_subheader_left( $active_tab ) {
        if ( $active_tab !== 'link-browser' ) {
            return;
        }

        $last_scan = get_option( 'blnotifier_link_browser_last_scan', [] );
        if ( !empty( $last_scan[ 'time' ] ) ) {
            $scan_user = get_userdata( absint( $last_scan[ 'user_id' ] ) );
            $scan_user_name = $scan_user ? $scan_user->display_name : __( 'Unknown', 'broken-link-notifier' );
            $last_scanned_text = sprintf(
                // translators: %1$s is the date, %2$s is the user's display name
                __( 'Last discovered on %1$s by %2$s', 'broken-link-notifier' ),
                (new BLNOTIFIER_HELPERS)->convert_timezone( $last_scan[ 'time' ] ),
                $scan_user_name
            );
        } else {
            $last_scanned_text = __( 'Not scanned yet', 'broken-link-notifier' );
        }
        ?>
        <button type="button" id="bln-run-link-browser-scan" class="blnotifier-button"><?php esc_html_e( 'Discover Links', 'broken-link-notifier' ); ?></button>
        <span class="bln-spinner" id="bln-scan-spinner" style="display:none;"></span>
        <span id="bln-link-browser-progress" style="display:none;">
            <em><?php esc_html_e( 'Scanning', 'broken-link-notifier' ); ?> <span id="bln-progress-done">0</span>/<span id="bln-progress-total">0</span> <?php esc_html_e( 'pages...', 'broken-link-notifier' ); ?></em>
        </span>
        <span id="bln-last-scanned"><?php echo esc_html( $last_scanned_text ); ?></span>
        <?php
    } // End render_subheader_left()


    /**
     * Render the right subheader
     *
     * @param string $active_tab
     * @return void
     */
    public function render_subheader_right( $active_tab ) {
        if ( $active_tab !== 'link-browser' ) {
            return;
        }
        ?>
        <a href="<?php echo esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'site-scan' ) ); ?>" class="blnotifier-button"><?php esc_html_e( 'Go to Site Scan', 'broken-link-notifier' ); ?></a>
        <a href="#" id="bln-export-link-browser" class="blnotifier-button"><?php esc_html_e( 'Export to CSV', 'broken-link-notifier' ); ?></a>
        <?php
    } // End render_subheader_right()


    /**
     * Does the current user have access to this feature?
     *
     * @return boolean
     */
    public function has_access() {
        return (new BLNOTIFIER_HELPERS)->user_can_manage_broken_links();
    } // End has_access()


    /**
     * Enqueue script
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();

        if ( $screen !== $options_page || $tab !== 'link-browser' ) {
            return;
        }

        wp_enqueue_style( 'blnotifier-link-browser', BLNOTIFIER_PLUGIN_CSS_PATH.'link-browser.css', [ 'blnotifier-theme' ], BLNOTIFIER_SCRIPT_VERSION );

        $handle = 'blnotifier_link_browser_script';
        wp_enqueue_script( 'jquery' );
        wp_register_script( $handle, site_url().BLNOTIFIER_PLUGIN_JS_PATH.'link-browser.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
        wp_localize_script( $handle, 'blnotifier_link_browser', [
            'nonce'         => wp_create_nonce( $this->nonce ),
            'check_nonce'   => wp_create_nonce( 'blnotifier_scan' ),
            'export_nonce'  => wp_create_nonce( 'blnotifier_export_nonce' ),
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'scan_delay_ms' => absint( get_option( 'blnotifier_scan_delay_ms', 0 ) ),
            'text'          => [
                'loading'        => __( 'Loading', 'broken-link-notifier' ),
                'first_page'     => __( 'First page', 'broken-link-notifier' ),
                'previous_page'  => __( 'Previous page', 'broken-link-notifier' ),
                'next_page'      => __( 'Next page', 'broken-link-notifier' ),
                'last_page'      => __( 'Last page', 'broken-link-notifier' ),
                'scan_complete'  => __( 'Scan complete', 'broken-link-notifier' ),
                'no_scan'        => __( 'Could not start scan.', 'broken-link-notifier' ),
                'discovering'    => __( 'Discovering...', 'broken-link-notifier' ),
                'discover_links' => __( 'Discover Links', 'broken-link-notifier' ),
                'no_links_found' => __( 'No links found', 'broken-link-notifier' ),
                'error'          => __( 'Error', 'broken-link-notifier' ),
                'server_error'   => __( 'Server error', 'broken-link-notifier' ),
                'error_link'     => __( 'Error checking link', 'broken-link-notifier' ),
                'omit_link'      => __( 'Omit this link from all future scans?', 'broken-link-notifier' ),
                'no_omit'        => __( 'Could not omit link.', 'broken-link-notifier' )
            ]
        ] );
        wp_enqueue_script( $handle );
    } // End enqueue_scripts()


    /**
     * Check if the table exists
     *
     * @return boolean
     */
    public function table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        return $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name; // phpcs:ignore
    } // End table_exists()


    /**
     * Create the table if it doesn't exist. Only ever called when a scan runs.
     *
     * @return void
     */
    public function maybe_create_db() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            link varchar(2048) NOT NULL,
            link_hash char(32) NOT NULL,
            type varchar(10) NOT NULL,
            kind varchar(10) NOT NULL DEFAULT 'link',
            sources longtext NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY link_hash (link_hash),
            KEY type (type),
            KEY kind (kind)
        ) $charset_collate;";

        dbDelta( $sql );
    } // End maybe_create_db()


    /**
     * Get total / internal / external counts
     *
     * @return array
     */
    public function get_counts() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        if ( !$this->table_exists() ) {
            return [ 'total' => 0, 'internal' => 0, 'external' => 0 ];
        }

        $total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" ); // phpcs:ignore
        $internal = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE type = %s", 'internal' ) ); // phpcs:ignore
        $external = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE type = %s", 'external' ) ); // phpcs:ignore

        return [ 'total' => $total, 'internal' => $internal, 'external' => $external ];
    } // End get_counts()


    /**
     * Render the status count filter links, matching the Results page style
     *
     * @param array $counts
     * @param string $current_filter
     * @return void
     */
    public function render_status_counts( $counts, $current_filter ) {
        $links = [
            'all'      => [ 'label' => __( 'All', 'broken-link-notifier' ), 'count' => $counts[ 'total' ] ],
            'internal' => [ 'label' => __( 'Internal', 'broken-link-notifier' ), 'count' => $counts[ 'internal' ] ],
            'external' => [ 'label' => __( 'External', 'broken-link-notifier' ), 'count' => $counts[ 'external' ] ],
        ];

        $keys = array_keys( $links );
        $last_key = end( $keys );

        echo '<ul class="subsubsub">';
        foreach ( $links as $key => $link ) {
            $is_current = ( $current_filter === $key );
            $aria = $is_current ? ' aria-current="page"' : '';
            echo '<li class="'.esc_attr( $key ).'">';
            echo '<a href="#" data-filter="'.esc_attr( $key ).'" class="bln-lb-status-filter'.( $is_current ? ' current' : '' ).'"'.esc_attr( $aria ).'>'.esc_html( $link[ 'label' ] ).' <span class="count">('.absint( $link[ 'count' ] ).')</span></a>';
            if ( $key !== $last_key ) {
                echo ' |';
            }
            echo '</li>';
        }
        echo '</ul>';
    } // End render_status_counts()


    /**
     * Determine internal vs external
     *
     * @param string $link
     * @return string
     */
    public function determine_type( $link ) {
        $scheme = wp_parse_url( $link, PHP_URL_SCHEME );

        if ( $scheme && !in_array( strtolower( $scheme ), [ 'http', 'https' ], true ) ) {
            $type = 'external';
        } else {
            $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
            $link_host = wp_parse_url( $link, PHP_URL_HOST );

            $type = ( !$link_host || strcasecmp( $link_host, $home_host ) === 0 ) ? 'internal' : 'external';
        }

        return apply_filters( 'blnotifier_link_type', $type, $link );
    } // End determine_type()


    /**
     * Determine link kind by file extension
     *
     * @param string $link
     * @return string
     */
    public function determine_kind( $link ) {
        $path = wp_parse_url( $link, PHP_URL_PATH );
        if ( !$path ) {
            return apply_filters( 'blnotifier_link_kind', 'link', $link, '' );
        }

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        $image_exts = apply_filters( 'blnotifier_link_kind_image_extensions', [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif', 'tiff' ] );
        $file_exts  = apply_filters( 'blnotifier_link_kind_file_extensions', [ 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'csv', 'txt', 'rtf', 'odt', 'ods', 'epub', 'mobi', 'mp3', 'wav', 'mp4', 'mov', 'avi', 'webm' ] );

        if ( in_array( $ext, $image_exts, true ) ) {
            $kind = 'image';
        } elseif ( in_array( $ext, $file_exts, true ) ) {
            $kind = 'file';
        } else {
            $kind = 'link';
        }

        return apply_filters( 'blnotifier_link_kind', $kind, $link, $ext );
    } // End determine_kind()


    /**
     * Get the queue of post IDs to scan
     *
     * @return array
     */
    public function get_queue_post_ids() {
        $OMITS = new BLNOTIFIER_OMITS;

        $post_types = get_option( 'blnotifier_post_types' );
        $post_types = !empty( $post_types ) ? array_keys( $post_types ) : [ 'post', 'page' ];

        $post_ids = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        $posts_page_id = absint( get_option( 'page_for_posts' ) );
        $queue = [];

        foreach ( $post_ids as $post_id ) {
            if ( $posts_page_id && $post_id == $posts_page_id ) {
                continue;
            }

            $permalink = get_the_permalink( $post_id );
            if ( $OMITS->is_omitted( $permalink, 'pages' ) ) {
                continue;
            }

            $queue[] = $post_id;
        }

        return $queue;
    } // End get_queue_post_ids()


    /**
     * Store or merge a link into the table
     *
     * @param string $link
     * @param string $source
     * @return boolean
     */
    public function store_link( $link, $source ) {
        $link = trim( $link );

        if ( $link === '' || $link[0] === '#' || $link[0] === '?' ) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $link_hash = md5( strtolower( $link ) );
        $type = $this->determine_type( $link );
        $kind = $this->determine_kind( $link );

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, sources FROM $table_name WHERE link_hash = %s", $link_hash ) ); // phpcs:ignore

        if ( $existing ) {
            $sources = json_decode( $existing->sources, true );
            $sources = is_array( $sources ) ? $sources : [];

            if ( !in_array( $source, $sources, true ) ) {
                $sources[] = $source;
                $wpdb->update(
                    $table_name,
                    [ 'sources' => wp_json_encode( $sources ), 'updated_at' => current_time( 'mysql' ) ],
                    [ 'id' => $existing->id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
            }
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'link'       => $link,
                    'link_hash'  => $link_hash,
                    'type'       => $type,
                    'kind'       => $kind,
                    'sources'    => wp_json_encode( [ $source ] ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
        }

        return true;
    } // End store_link()


    /**
     * Ajax: get the queue of post IDs and reset the table for a fresh scan
     *
     * @return void
     */
    public function ajax_get_queue() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $this->maybe_create_db();
        $table_name = $wpdb->prefix . $this->table_name;
        $wpdb->query( "TRUNCATE TABLE $table_name" ); // phpcs:ignore

        $queue = $this->get_queue_post_ids();

        wp_send_json_success( [ 'post_ids' => $queue, 'total' => count( $queue ) ] );
    } // End ajax_get_queue()


    /**
     * Ajax: scan a single post for links
     *
     * @return void
     */
    public function ajax_scan_post() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $post_id = isset( $_REQUEST[ 'postID' ] ) ? absint( wp_unslash( $_REQUEST[ 'postID' ] ) ) : 0;
        if ( !$post_id ) {
            wp_send_json_error( [ 'msg' => __( 'No post ID provided.', 'broken-link-notifier' ) ] );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        $get_the_content = get_the_content( null, false, $post_id );
        $count_found = 0;
        $redirect_detected = false;

        if ( $get_the_content ) {

            if ( strpos( $get_the_content, '[redirect_this_page' ) !== false ) {
                $redirect_detected = true;

            } else {

                add_filter( 'wp_redirect', '__return_false', 1 );
                add_filter( 'wp_safe_redirect', '__return_false', 1 );

                global $post;
                $scanned_post = get_post( $post_id );
                $original_post = $post;
                if ( $scanned_post ) {
                    $post = $scanned_post;
                    setup_postdata( $post );
                }

                ob_start();
                try {
                    $content = apply_filters( 'the_content', $get_the_content );
                } catch ( Exception $e ) {
                    error_log( 'Error processing shortcodes: ' . $e->getMessage() ); // phpcs:ignore
                    $content = '';
                    $redirect_detected = true;
                }
                ob_end_clean();

                if ( $scanned_post ) {
                    $post = $original_post;
                    wp_reset_postdata();
                }

                remove_filter( 'wp_redirect', '__return_false', 1 );
                remove_filter( 'wp_safe_redirect', '__return_false', 1 );

                if ( $content ) {
                    $links = $HELPERS->extract_links( $content );

                    if ( filter_var( get_option( 'blnotifier_remote_fetch_links' ), FILTER_VALIDATE_BOOLEAN ) ) {
                        $remote_links = $HELPERS->get_remote_page_links( $post_id );
                        $links = $HELPERS->merge_and_dedupe_links( $links, $remote_links );
                    }

                    foreach ( $links as $link ) {
                        if ( $this->store_link( $link, $post_id ) ) {
                            $count_found++;
                        }
                    }
                }
            }
        }

        if ( $redirect_detected ) {
            $auto_omit_redirects = apply_filters( 'blnotifier_auto_omit_redirects', true, $post_id );

            if ( $auto_omit_redirects ) {
                $permalink = get_the_permalink( $post_id );
                $OMITS = new BLNOTIFIER_OMITS;
                if ( $permalink && !$OMITS->is_omitted( $permalink, 'pages' ) ) {
                    $note = apply_filters(
                        'blnotifier_auto_omit_redirect_note',
                        sprintf(
                            /* translators: %s: date and time the page was auto-omitted */
                            __( 'Automatically omitted on %s — this page appears to redirect elsewhere, which prevents its links from being scanned.', 'broken-link-notifier' ),
                            $HELPERS->convert_timezone()
                        ),
                        $post_id,
                        $permalink
                    );
                    $OMITS->add( $permalink, 'pages', 'link-browser', $note );
                }
            }
        }

        wp_send_json_success( [ 'post_id' => $post_id, 'links_found' => $count_found, 'redirect_detected' => $redirect_detected ] );
    } // End ajax_scan_post()


    /**
     * Ajax: finish the scan and record who ran it
     *
     * @return void
     */
    public function ajax_finish_scan() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $HELPERS = new BLNOTIFIER_HELPERS;
        $now = current_time( 'mysql' );

        update_option( 'blnotifier_link_browser_last_scan', [
            'time'    => $now,
            'user_id' => get_current_user_id(),
        ] );

        $last_scanned_text = sprintf(
            // translators: %1$s is the date, %2$s is the user's display name
            __( 'Last scanned on %1$s by %2$s', 'broken-link-notifier' ),
            $HELPERS->convert_timezone( $now ),
            wp_get_current_user()->display_name
        );

        wp_send_json_success( [ 'counts' => $this->get_counts(), 'last_scanned_text' => $last_scanned_text ] );
    } // End ajax_finish_scan()


    /**
     * Ajax: get a page of the links table
     *
     * @return void
     */
    public function ajax_table() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        $search   = isset( $_REQUEST[ 'search' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'search' ] ) ) : '';
        $filter   = isset( $_REQUEST[ 'filter' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'filter' ] ) ) : 'all';
        $kind     = isset( $_REQUEST[ 'kind' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'kind' ] ) ) : 'all';
        $page     = isset( $_REQUEST[ 'page' ] ) ? absint( wp_unslash( $_REQUEST[ 'page' ] ) ) : 1;
        $per_page = isset( $_REQUEST[ 'per_page' ] ) ? absint( wp_unslash( $_REQUEST[ 'per_page' ] ) ) : 25;
        $page     = max( 1, $page );
        $per_page = $per_page > 0 ? $per_page : 25;

        if ( !$this->table_exists() ) {
            ob_start();
            echo '<tr><td colspan="5"><em>' . esc_html__( 'Run a scan to see results.', 'broken-link-notifier' ) . '</em></td></tr>'; // phpcs:ignore
            $rows_html = ob_get_clean();

            wp_send_json_success( [
                'rows'        => $rows_html,
                'total'       => 0,
                'total_pages' => 1,
                'page'        => 1,
                'counts'      => [ 'total' => 0, 'internal' => 0, 'external' => 0 ],
            ] );
        }

        $where_sql = 'WHERE 1=1';
        $where_values = [];

        if ( $search !== '' ) {
            $where_sql .= ' AND link LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        if ( $filter === 'internal' || $filter === 'external' ) {
            $where_sql .= ' AND type = %s';
            $where_values[] = $filter;
        }

        if ( $kind === 'link' || $kind === 'image' || $kind === 'file' ) {
            $where_sql .= ' AND kind = %s';
            $where_values[] = $kind;
        }

        $count_query = "SELECT COUNT(*) FROM $table_name $where_sql";
        $total = !empty( $where_values ) ? (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) ) : (int) $wpdb->get_var( $count_query ); // phpcs:ignore

        $offset = ( $page - 1 ) * $per_page;
        $order_by = "ORDER BY CASE WHEN link LIKE 'http://%' THEN 1 WHEN link LIKE 'https://%' THEN 2 ELSE 0 END ASC, link ASC";

        $query_values = array_merge( $where_values, [ $per_page, $offset ] );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name $where_sql $order_by LIMIT %d OFFSET %d", $query_values ) ); // phpcs:ignore

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
     * Render table rows
     *
     * @param array $rows
     * @return void
     */
    public function render_rows( $rows ) {
        if ( empty( $rows ) ) {
            echo '<tr><td colspan="5"><em>' . esc_html__( 'No links found.', 'broken-link-notifier' ) . '</em></td></tr>'; // phpcs:ignore
            return;
        }

        foreach ( $rows as $row ) {
            $sources = json_decode( $row->sources, true );
            $sources = is_array( $sources ) ? $sources : [];

            $valid_pages = [];
            $first_permalink = '';
            $first_source_id = 0;
            $first_is_menu = false;

            foreach ( $sources as $source ) {
                if ( is_string( $source ) && strpos( $source, 'menu:' ) === 0 ) {
                    $menu_id = (int) str_replace( 'menu:', '', $source );
                    $menu_obj = wp_get_nav_menu_object( $menu_id );
                    if ( $menu_obj ) {
                        $menu_edit_link = admin_url( 'nav-menus.php?action=edit&menu='.$menu_id );
                        if ( !$first_permalink ) {
                            $first_permalink = $menu_edit_link;
                            $first_source_id = 0;
                            $first_is_menu = true;
                        }
                        $valid_pages[] = '<a href="'.esc_url( $menu_edit_link ).'">'.esc_html( $menu_obj->name ).' (' . esc_html__( 'Menu', 'broken-link-notifier' ) . ')</a>';
                    }
                } else {
                    $post = get_post( $source );
                    if ( $post ) {
                        if ( !$first_permalink ) {
                            $first_permalink = get_the_permalink( $source );
                            $first_source_id = $source;
                        }
                        $valid_pages[] = '<a href="' . esc_url( get_edit_post_link( $source ) ) . '">' . esc_html( $post->post_title ) . '</a>';
                    }
                }
            }

            $page_count = count( $valid_pages );
            $visible_pages = array_slice( $valid_pages, 0, 2 );
            $hidden_pages = array_slice( $valid_pages, 2 );

            $pages_html = implode( '<br>', $visible_pages );
            if ( !empty( $hidden_pages ) ) {
                $pages_html .= '<div class="pages-list">' . implode( '<br>', $hidden_pages ) . '</div>';
                $pages_html .= '<br><span class="pages-toggle" data-more-text="' . esc_attr__( 'View More', 'broken-link-notifier' ) . '" data-less-text="' . esc_attr__( 'View Less', 'broken-link-notifier' ) . '">' . esc_html__( 'View More', 'broken-link-notifier' ) . '</span>';
            }

            $type_class = 'type-' . sanitize_html_class( $row->type );
            $kind_class = 'kind-' . sanitize_html_class( $row->kind );
            $scan_nonce = wp_create_nonce( 'blnotifier_scan' );

            if ( $first_permalink && $first_is_menu ) {
                $show_me_link = '<a href="' . esc_url( $first_permalink ) . '" target="_blank">' . esc_html__( 'Show Me', 'broken-link-notifier' ) . '</a>';
            } elseif ( $first_permalink ) {
                $show_me_link = '<a href="' . esc_url( add_query_arg( 'blink', $row->link, $first_permalink ) ) . '" target="_blank">' . esc_html__( 'Show Me', 'broken-link-notifier' ) . '</a>';
            } else {
                $show_me_link = '<span class="disabled">' . esc_html__( 'Show Me', 'broken-link-notifier' ) . '</span>';
            }
            ?>
            <tr class="link-row" data-link-id="<?php echo absint( $row->id ); ?>">
                <td class="link">
                    <a href="<?php echo esc_url( $row->link ); ?>" target="_blank"><?php echo esc_html( $row->link ); ?></a>
                    <div class="bln-status-result"></div>
                </td>
                <td class="page-count"><?php echo absint( $page_count ); ?></td>
                <td class="pages"><?php echo wp_kses_post( $pages_html ); ?></td>
                <td class="type">
                    <span class="<?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( ucfirst( $row->type ) ); ?></span><br>
                    <span class="<?php echo esc_attr( $kind_class ); ?>"><?php echo esc_html( ucfirst( $row->kind ) ); ?></span>
                </td>
                <td class="actions">
                    <?php echo wp_kses_post( $show_me_link ); ?> |
                    <a href="#" class="check-status" data-link="<?php echo esc_attr( $row->link ); ?>" data-post-id="<?php echo absint( $first_source_id ); ?>" data-nonce="<?php echo esc_attr( $scan_nonce ); ?>"><?php echo esc_html__( 'Check Status', 'broken-link-notifier' ); ?></a> |
                    <a href="<?php echo esc_url( add_query_arg( [
                        'page'     => BLNOTIFIER_TEXTDOMAIN,
                        'tab'      => 'link-search',
                        'search'   => $row->link,
                        '_wpnonce' => wp_create_nonce( 'blnotifier_link_search' ),
                    ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Search All Pages', 'broken-link-notifier' ); ?></a> |
                    <a href="#" class="omit-link" data-link="<?php echo esc_attr( $row->link ); ?>" data-link-id="<?php echo absint( $row->id ); ?>"><?php echo esc_html__( 'Omit Link', 'broken-link-notifier' ); ?></a>
                    <span class="bln-spinner" style="display:none;"></span>
                </td>
            </tr>
            <?php
        }
    } // End render_rows()


    /**
     * Ajax: omit a link and remove it from the table
     *
     * @return void
     */
    public function ajax_omit_link() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !$this->has_access() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $link = isset( $_REQUEST[ 'link' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'link' ] ) ) : '';
        $link_id = isset( $_REQUEST[ 'linkID' ] ) ? absint( wp_unslash( $_REQUEST[ 'linkID' ] ) ) : 0;

        if ( !$link || !$link_id ) {
            wp_send_json_error( [ 'msg' => __( 'Missing data.', 'broken-link-notifier' ) ] );
        }

        $OMITS = new BLNOTIFIER_OMITS;
        $omitted = $OMITS->add( $link, 'links', 'link-browser' );

        if ( $omitted !== true ) {
            wp_send_json_error( [ 'msg' => __( 'Could not omit link.', 'broken-link-notifier' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $wpdb->delete( $table_name, [ 'id' => $link_id ], [ '%d' ] );

        wp_send_json_success();
    } // End ajax_omit_link()

}