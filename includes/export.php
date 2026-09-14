<?php
/**
 * Export class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initialize the class
 */
new BLNOTIFIER_EXPORT();


/**
 * Main plugin class.
 */
class BLNOTIFIER_EXPORT {

    /**
     * Nonce used for export requests
     *
     * @var string
     */
    public $nonce = 'blnotifier_export_nonce';


    /**
	 * Constructor
	 */
	public function __construct() {

        // Handler
        add_action( 'admin_init', [ $this, 'handler' ] );
        
	} // End __construct()


    /**
     * Handle export request for different types of links.
     */
    public function handler() {
        if ( !isset( $_GET[ 'export' ], $_GET[ '_wpnonce' ] ) ) {
            return;
        }

        // Capability check: only allow admins or users with manage_options capability
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to export links.', 'broken-link-notifier' ) );
        }

        if ( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ '_wpnonce' ] ) ), $this->nonce ) ) {
            wp_die( __( 'Security check failed.', 'broken-link-notifier' ) );
        }

        $type = sanitize_key( $_GET[ 'export' ] );

        if ( $type === 'results' ) {
            $filter = isset( $_GET[ 'filter' ] ) ? sanitize_key( wp_unslash( $_GET[ 'filter' ] ) ) : 'all';
            $links = $this->get_filtered_results( $filter );
            $headers = $this->get_results_headers();

        } elseif ( $type === 'link-browser' ) {
            $lb_filter = isset( $_GET[ 'filter' ] ) ? sanitize_key( wp_unslash( $_GET[ 'filter' ] ) ) : 'all';
            $lb_kind = isset( $_GET[ 'kind' ] ) ? sanitize_key( wp_unslash( $_GET[ 'kind' ] ) ) : 'all';
            $lb_search = isset( $_GET[ 'search' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) ) : '';
            $links = $this->get_all_discovered_links( $lb_filter, $lb_kind, $lb_search );
            $headers = $this->get_link_browser_headers();

        } else {
            $links = (new BLNOTIFIER_HELPERS())->get_links( $type );
            $headers = $this->get_results_headers();
        }

        if ( empty( $links ) ) {
            wp_die( __( 'No links found to export.', 'broken-link-notifier' ) );
        }

        $this->send_csv_headers( $type );
        $this->output_csv( $links, $headers );
        exit;
    } // End handler()


    /**
     * Get the CSV column headers for Broken/Cached/Results exports
     *
     * @return array
     */
    protected function get_results_headers() {
        $headers = [
            'date'            => __( 'Date', 'broken-link-notifier' ),
            'type'            => __( 'Type', 'broken-link-notifier' ),
            'code'            => __( 'Code', 'broken-link-notifier' ),
            'message'         => __( 'Message', 'broken-link-notifier' ),
            'link'            => __( 'Link', 'broken-link-notifier' ),
            'source_name'     => __( 'Source Name', 'broken-link-notifier' ),
            'source_link'     => __( 'Source URL', 'broken-link-notifier' ),
            'source_posttype' => __( 'Source Post Type', 'broken-link-notifier' ),
            'location'        => __( 'Location', 'broken-link-notifier' ),
            'User'            => __( 'User', 'broken-link-notifier' ),
            'Method'          => __( 'Method', 'broken-link-notifier' ),
        ];

        return apply_filters( 'blnotifier_export_results_headers', $headers );
    } // End get_results_headers()


    /**
     * Get the CSV column headers for the Link Browser export
     *
     * @return array
     */
    protected function get_link_browser_headers() {
        $headers = [
            'link'        => __( 'Link', 'broken-link-notifier' ),
            'type'        => __( 'Type', 'broken-link-notifier' ),
            'kind'        => __( 'Kind', 'broken-link-notifier' ),
            'source_name' => __( 'Pages Found On', 'broken-link-notifier' ),
            'source_link' => __( 'Page URLs', 'broken-link-notifier' ),
        ];

        return apply_filters( 'blnotifier_export_link_browser_headers', $headers );
    } // End get_link_browser_headers()


    /**
     * Get results rows filtered to match whatever is currently shown on the Results page
     *
     * @param string $filter
     * @return array
     */
    protected function get_filtered_results( $filter ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blnotifier_results';

        $type = 'all';
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

        $rows = !empty( $where_values )
            ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name $where_sql ORDER BY created_at ASC", $where_values ), ARRAY_A ) // phpcs:ignore
            : $wpdb->get_results( "SELECT * FROM $table_name $where_sql ORDER BY created_at ASC", ARRAY_A ); // phpcs:ignore

        if ( $scope !== 'all' && !empty( $rows ) ) {
            $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
            $rows = array_values( array_filter( $rows, function( $row ) use ( $LINK_BROWSER, $scope ) {
                return $LINK_BROWSER->determine_type( $row[ 'link' ] ) === $scope;
            } ) );
        }

        $methods = [
            'visit'     => __( 'Front-End Visit', 'broken-link-notifier' ),
            'multi'     => __( 'Multi-Scan', 'broken-link-notifier' ),
            'single'    => __( 'Page Scan', 'broken-link-notifier' ),
            'site-scan' => __( 'Site Scan', 'broken-link-notifier' ),
        ];

        $links = [];
        $source_cache = [];

        foreach ( $rows as $row ) {
            $source_url = $row[ 'source' ];

            if ( isset( $source_cache[ $source_url ] ) ) {
                $source_title = $source_cache[ $source_url ];
            } else {
                $source_post_id = url_to_postid( $source_url );
                $source_title = $source_post_id ? get_the_title( $source_post_id ) : __( 'Unknown', 'broken-link-notifier' );
                $source_cache[ $source_url ] = $source_title;
            }

            $user = $row[ 'author_id' ] ? get_userdata( $row[ 'author_id' ] ) : null;

            $links[] = [
                'date'        => mysql2date( 'Y-m-d H:i:s', $row[ 'created_at' ] ),
                'type'        => ucwords( sanitize_text_field( $row[ 'type' ] ) ),
                'code'        => absint( $row[ 'code' ] ),
                'message'     => sanitize_text_field( $row[ 'text' ] ),
                'link'        => esc_url( $row[ 'link' ] ),
                'source_name' => $source_title,
                'source_link' => esc_url( $source_url ),
                'location'    => ucwords( sanitize_key( $row[ 'location' ] ) ),
                'user'        => $user ? $user->display_name : __( 'Guest', 'broken-link-notifier' ),
                'method'      => isset( $methods[ $row[ 'method' ] ] ) ? $methods[ $row[ 'method' ] ] : __( 'Unknown', 'broken-link-notifier' ),
            ];
        }

        return apply_filters( 'blnotifier_export_results_rows', $links, $filter );
    } // End get_filtered_results()


    /**
     * Get every link currently discovered by Link Browser/Site Scan
     *
     * @param string $filter Filter by 'all', 'internal', or 'external'.
     * @param string $kind   Filter by 'all', 'link', 'image', or 'file'.
     * @param string $search Search term for the link URL.
     * @return array
     */
    protected function get_all_discovered_links( $filter = 'all', $kind = 'all', $search = '' ) {
        global $wpdb;
        $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
        if ( !$LINK_BROWSER->table_exists() ) {
            return [];
        }

        $table_name = $wpdb->prefix . 'blnotifier_links';

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

        $order_by = "ORDER BY CASE WHEN link LIKE 'http://%' THEN 1 WHEN link LIKE 'https://%' THEN 2 ELSE 0 END ASC, link ASC";

        $rows = !empty( $where_values )
            ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name $where_sql $order_by", $where_values ) ) // phpcs:ignore
            : $wpdb->get_results( "SELECT * FROM $table_name $where_sql $order_by" ); // phpcs:ignore

        $links = [];
        foreach ( $rows as $row ) {
            $sources = json_decode( $row->sources, true );
            $sources = is_array( $sources ) ? $sources : [];

            $source_names = [];
            $source_links = [];

            foreach ( $sources as $source ) {
                if ( is_string( $source ) && strpos( $source, 'menu:' ) === 0 ) {
                    $menu_id = (int) str_replace( 'menu:', '', $source );
                    $menu_obj = wp_get_nav_menu_object( $menu_id );
                    if ( $menu_obj ) {
                        $source_names[] = $menu_obj->name . ' (Menu)';
                        $source_links[] = admin_url( 'nav-menus.php?action=edit&menu='.$menu_id );
                    }
                } else {
                    $post = get_post( $source );
                    if ( $post ) {
                        $source_names[] = $post->post_title;
                        $source_links[] = get_the_permalink( $source );
                    }
                }
            }

            $links[] = [
                'link'        => $row->link,
                'type'        => ucfirst( $row->type ),
                'kind'        => ucfirst( $row->kind ),
                'source_name' => implode( '; ', $source_names ),
                'source_link' => implode( '; ', $source_links ),
            ];
        }

        return apply_filters( 'blnotifier_export_link_browser_rows', $links, $filter, $kind, $search );
    } // End get_all_discovered_links()


    /**
     * Output CSV headers.
     *
     * @param string $type
     */
    protected function send_csv_headers( $type ) {
        $filename = "blnotifier-{$type}-export-" . gmdate( 'Y-m-d-H-i-s' ) . ".csv";

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
    } // End send_csv_headers()


    /**
     * Escape CSV values to prevent injection (Excel formula injection).
     *
     * @param string $value
     * @return string
     */
    protected function escape_csv_value( $value ) {
        $value = trim( (string) $value );
        if ( preg_match( '/^[=+\-@]/', $value ) ) {
            return "'" . $value;
        }
        return $value;
    } // End escape_csv_value()


    /**
     * Output CSV content to browser.
     *
     * @param array $data
     */
    protected function output_csv( array $data, array $headers ) {
        $output = fopen( 'php://output', 'w' );

        // Convert the headers
        $header_keys = array_keys( $data[0] );
        $header_row = [];
        foreach ( $header_keys as $key ) {
            if ( isset( $headers[ $key ] ) ) {
                $header_row[] = $headers[ $key ];
            } else {
                $header_row[] = ucwords( $key );
            }
        }

        // Add headers from array keys
        fputcsv( $output, $header_row );

        foreach ( $data as $row ) {
            $escaped_row = [];

            foreach ( $header_keys as $key ) {
                $value = isset( $row[ $key ] ) ? $row[ $key ] : '';
                $escaped_row[] = $this->escape_csv_value( $value );
            }

            fputcsv( $output, $escaped_row );
        }

        fclose( $output );
    } // End output_csv()

}