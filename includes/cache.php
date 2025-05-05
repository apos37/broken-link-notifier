<?php
/**
 * Cache class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initialize the class
 */

new BLNOTIFIER_CACHE();


/**
 * Main plugin class.
 */
class BLNOTIFIER_CACHE {

    /**
     * The table name
     *
     * @var string
     */
    protected $table;


    /**
     * Length of time to cache links
     *
     * @var int
     */
    public $cache_time_in_seconds;


    /**
     * Whether to mark (cached) at the end of the text response
     *
     * @var boolean
     */
    private $mark_cached_in_text = false;


    /**
	 * Constructor
	 */
	public function __construct() {

        $this->cache_time_in_seconds = absint( get_option( 'blnotifier_cache', 0 ) );

        global $wpdb;
        $this->table = $wpdb->prefix . 'blnotifier_cache';
        $this->maybe_create_table();
        $this->maybe_cleanup_cache();
        $this->maybe_delete_table_if_disabled();
        
	} // End __construct()


    /**
     * Create the cache table if it doesn't already exist.
     */
    public function maybe_create_table() {
        if ( $this->cache_time_in_seconds === 0 ) {
            return;
        }

        global $wpdb;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->table}'" ) !== $this->table ) { // phpcs:ignore 
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                link TEXT NOT NULL,
                http_code SMALLINT UNSIGNED NOT NULL,
                type VARCHAR(20) NOT NULL,
                status_text TEXT,
                last_checked DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY link_unique (link(191))
            ) $charset_collate;";

            dbDelta( $sql );
        }
    } // End maybe_create_table()


    /**
     * Delete the table if cache is disabled
     *
     * @return void
     */
    public function maybe_delete_table_if_disabled() {
        if ( $this->cache_time_in_seconds === 0 ) {
            $this->delete_cache_table();
        }
    } // End maybe_delete_table_if_disabled()


    /**
     * Delete the cache table from the database.
     */
    public function delete_cache_table() {
        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS {$this->table}" ); // phpcs:ignore 
    } // End delete_cache_table()


    /**
     * Remove expired cache entries older than 8 hours.
     */
    public function maybe_cleanup_cache() {
        if ( $this->cache_time_in_seconds === 0 ) {
            return;
        }

        global $wpdb;

        $expiration_time = gmdate( 'Y-m-d H:i:s', time() - $this->cache_time_in_seconds );

        $wpdb->query( $wpdb->prepare( // phpcs:ignore 
            "DELETE FROM {$this->table} WHERE last_checked < %s",
            $expiration_time
        ) );
    } // End maybe_cleanup_cache()


    /**
     * Retrieve a cached link if it's still valid.
     *
     * @param string $link The URL to look up.
     * @return array|false The cached status or false if not found or expired.
     */
    public function get_cached_link( $link ) {
        if ( $this->cache_time_in_seconds === 0 ) {
            return false;
        }

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore 
            "SELECT * FROM {$this->table} WHERE link = %s AND last_checked >= %s LIMIT 1",
            $link,
            gmdate( 'Y-m-d H:i:s', time() - $this->cache_time_in_seconds )
        ), ARRAY_A );

        if ( $row ) {
            $mark_cached = $this->mark_cached_in_text ?  ' (cached)' : '';
            return [
                'type' => $row[ 'type' ],
                'code' => (int) $row[ 'http_code' ],
                'text' => $row[ 'status_text' ] . $mark_cached,
                'link' => $row[ 'link' ]
            ];
        }

        return false;
    } // End get_cached_link()


    /**
     * Store a link in the cache if it's good (not broken or warning).
     *
     * @param array $status The link status array.
     */
    public function set_cached_link( $status ) {
        if ( $this->cache_time_in_seconds === 0 ) {
            return;
        }
        
        if ( ! is_array( $status ) || ! isset( $status[ 'type' ], $status[ 'code' ], $status[ 'text' ], $status[ 'link' ] ) ) {
            return;
        }

        if ( in_array( $status[ 'type' ], [ 'broken', 'warning' ], true ) ) {
            return;
        }

        global $wpdb;

        $wpdb->replace( // phpcs:ignore 
            $this->table,
            [
                'link'         => $status[ 'link' ],
                'http_code'    => absint( $status[ 'code' ] ),
                'type'         => sanitize_text_field( $status[ 'type' ] ),
                'status_text'  => sanitize_text_field( $status[ 'text' ] ),
                'last_checked' => current_time( 'mysql', 1 )
            ],
            [
                '%s',
                '%d',
                '%s',
                '%s',
                '%s'
            ]
        );
    } // End set_cached_link()

}