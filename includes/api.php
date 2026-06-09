<?php
/**
 * REST API class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Initiate the class
 */
new BLNOTIFIER_API();


/**
 * Main plugin class.
 */
class BLNOTIFIER_API {

    /**
     * The REST API namespace
     *
     * @var string
     */
    private $namespace = 'blnotifier/v1';


    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    } // End __construct()


    /**
     * Check if the REST API is enabled
     *
     * @return boolean
     */
    public function is_enabled() {
        return (bool) get_option( 'blnotifier_enable_rest_api', false );
    } // End is_enabled()


    /**
     * Get the API key from the request
     *
     * @param WP_REST_Request $request
     * @return string
     */
    private function get_request_api_key( $request ) {
        $header = $request->get_header( 'X-API-Key' );
        if ( $header ) {
            return sanitize_text_field( $header );
        }

        $param = $request->get_param( 'api_key' );
        if ( $param ) {
            return sanitize_text_field( $param );
        }

        return '';
    } // End get_request_api_key()


    /**
     * Permission callback for all routes
     *
     * @param WP_REST_Request $request
     * @return boolean|WP_Error
     */
    public function permission_callback( $request ) {
        if ( !$this->is_enabled() ) {
            return new WP_Error( 'rest_disabled', __( 'The Broken Link Notifier REST API is disabled.', 'broken-link-notifier' ), [ 'status' => 403 ] );
        }

        $saved_key = get_option( 'blnotifier_api_key', '' );
        if ( !$saved_key ) {
            return new WP_Error( 'rest_no_key', __( 'No API key has been configured.', 'broken-link-notifier' ), [ 'status' => 403 ] );
        }

        if ( !hash_equals( $saved_key, $this->get_request_api_key( $request ) ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Invalid API key.', 'broken-link-notifier' ), [ 'status' => 401 ] );
        }

        return true;
    } // End permission_callback()


    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/results', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_results' ],
            'permission_callback' => [ $this, 'permission_callback' ],
            'args'                => [
                'type' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function( $value ) {
                        return in_array( $value, [ 'all', 'broken', 'warning' ] );
                    },
                ],
                'per_page' => [
                    'default'           => 100,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function( $value ) {
                        return $value > 0 && $value <= 500;
                    },
                ],
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function( $value ) {
                        return $value > 0;
                    },
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/results/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_result' ],
            'permission_callback' => [ $this, 'permission_callback' ],
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/results/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'delete_result' ],
            'permission_callback' => [ $this, 'permission_callback' ],
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    } // End register_routes()


    /**
     * GET /results
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_results( $request ) {
        global $wpdb;

        $table    = $wpdb->prefix . 'blnotifier_results';
        $type     = $request->get_param( 'type' );
        $per_page = $request->get_param( 'per_page' );
        $page     = $request->get_param( 'page' );
        $offset   = ( $page - 1 ) * $per_page;

        $where = '';
        $args  = [];

        if ( $type !== 'all' ) {
            $where = 'WHERE type = %s';
            $args[] = $type;
        }

        $args[] = $per_page;
        $args[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, link, text, type, code, source, location, method, created_at FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", $args ),
            ARRAY_A
        );

        $total = (int) $wpdb->get_var(
            $type !== 'all'
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s", $type )
                : "SELECT COUNT(*) FROM {$table}"
        );

        $source_cache = [];

        $results = array_map( function( $row ) use ( &$source_cache ) {
            $source_url = $row[ 'source' ];

            if ( !isset( $source_cache[ $source_url ] ) ) {
                $source_cache[ $source_url ] = url_to_postid( $source_url );
            }

            return [
                'id'             => absint( $row[ 'id' ] ),
                'link'           => esc_url( $row[ 'link' ] ),
                'text'           => sanitize_text_field( $row[ 'text' ] ),
                'type'           => sanitize_key( $row[ 'type' ] ),
                'code'           => absint( $row[ 'code' ] ),
                'source_url'     => esc_url( $source_url ),
                'source_post_id' => $source_cache[ $source_url ] ?: null,
                'location'       => sanitize_key( $row[ 'location' ] ),
                'method'         => sanitize_key( $row[ 'method' ] ),
                'created_at'     => sanitize_text_field( $row[ 'created_at' ] ),
            ];
        }, $rows );

        $response = new WP_REST_Response( $results, 200 );
        $response->header( 'X-Total', $total );
        $response->header( 'X-Total-Pages', (int) ceil( $total / $per_page ) );
        $response->header( 'X-Page', $page );

        return $response;
    } // End get_results()


    /**
     * GET /results/{id}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_result( $request ) {
        global $wpdb;

        $table = $wpdb->prefix . 'blnotifier_results';
        $id    = $request->get_param( 'id' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, link, text, type, code, source, location, method, created_at FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( !$row ) {
            return new WP_REST_Response( [ 'message' => __( 'Result not found.', 'broken-link-notifier' ) ], 404 );
        }

        return new WP_REST_Response( [
            'id'             => absint( $row[ 'id' ] ),
            'link'           => esc_url( $row[ 'link' ] ),
            'text'           => sanitize_text_field( $row[ 'text' ] ),
            'type'           => sanitize_key( $row[ 'type' ] ),
            'code'           => absint( $row[ 'code' ] ),
            'source_url'     => esc_url( $row[ 'source' ] ),
            'source_post_id' => url_to_postid( $row[ 'source' ] ) ?: null,
            'location'       => sanitize_key( $row[ 'location' ] ),
            'method'         => sanitize_key( $row[ 'method' ] ),
            'created_at'     => sanitize_text_field( $row[ 'created_at' ] ),
        ], 200 );
    } // End get_result()


    /**
     * DELETE /results/{id}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_result( $request ) {
        global $wpdb;

        $table   = $wpdb->prefix . 'blnotifier_results';
        $id      = $request->get_param( 'id' );
        $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        if ( !$deleted ) {
            return new WP_REST_Response( [ 'message' => __( 'Result not found.', 'broken-link-notifier' ) ], 404 );
        }

        return new WP_REST_Response( [ 'message' => __( 'Result deleted.', 'broken-link-notifier' ), 'id' => $id ], 200 );
    } // End delete_result()

}