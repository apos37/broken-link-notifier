<?php
/**
 * Omits Class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
add_action( 'init', function() {
    (new BLNOTIFIER_OMITS)->init();
} );


/**
 * Main plugin class.
 */
class BLNOTIFIER_OMITS {

    /**
     * Taxonomies
     *
     * @var array
     */
    public $taxonomies = [ 
        'omit-links' => 'Omitted Link', 
        'omit-pages' => 'Omitted Page'
    ];


    /**
     * The key that is used to identify the ajax response
     *
     * @var string
     */
    private $ajax_key = 'blnotifier_omit';


    /**
     * Name of nonce used for ajax call
     *
     * @var string
     */
    private $nonce = 'blnotifier_omit_something';


    /**
     * Load on init
     */
    public function init() {

        // Iter taxonomies
        foreach ( $this->taxonomies as $taxonomy => $label ) {

            // Register
            $this->register_taxonomy( $taxonomy );
        }

        // Add instructions to top of page
        add_action( 'admin_notices', [ $this, 'description_notice' ] );

        // Edit taxonomy form
        add_action( 'admin_head', [ $this, 'form_fields' ] );

        // Update admin columns
        $taxonomies = array_keys( $this->taxonomies );
        add_filter( 'manage_edit-'.$taxonomies[0].'_columns', [ $this, 'admin_columns_links' ] );
        add_filter( 'manage_edit-'.$taxonomies[1].'_columns', [ $this, 'admin_columns_pages' ] );
        add_filter( 'manage_'.$taxonomies[1].'_custom_column', [ $this, 'admin_column_content_pages' ], 10, 3);

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key, [ $this, 'ajax' ] );
        add_action( 'wp_ajax_nopriv_'.$this->ajax_key, [ $this, 'must_login' ] );
        
        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


    /**
     * Register taxonomy
     *
     * @param string $taxonomy
     * @param array $labels
     * @return void
     */
    public function register_taxonomy( $taxonomy ) {
        // Labels
        if ( $taxonomy == 'omit-links' ) {
            $labels = [
                'name'              => _x( 'Omitted Links', 'taxonomy general name', 'broken-link-notifier' ),
                'singular_name'     => _x( 'Omitted Link', 'taxonomy singular name', 'broken-link-notifier' ),
                'search_items'      => __( 'Search Omitted Links', 'broken-link-notifier' ),
                'all_items'         => __( 'Add to Omitted Link', 'broken-link-notifier' ),
                'edit_item'         => __( 'Edit Omitted Link', 'broken-link-notifier' ),
                'update_item'       => __( 'Update Omitted Link', 'broken-link-notifier' ),
                'add_new_item'      => __( 'Add New Omitted Link', 'broken-link-notifier' ),
                'new_item_name'     => __( 'New Omitted Link Name', 'broken-link-notifier' ),
                'menu_name'         => __( 'Omitted Links', 'broken-link-notifier' ),
                'not_found'         => __( 'No omitted links found.', 'broken-link-notifier' ),
            ]; 	
        } elseif ( $taxonomy == 'omit-pages' ) {
            $labels = [
                'name'              => _x( 'Omitted Pages', 'taxonomy general name', 'broken-link-notifier' ),
                'singular_name'     => _x( 'Omitted Page', 'taxonomy singular name', 'broken-link-notifier' ),
                'search_items'      => __( 'Search Omitted Pages', 'broken-link-notifier' ),
                'all_items'         => __( 'Add to Omitted Page', 'broken-link-notifier' ),
                'edit_item'         => __( 'Edit Omitted Page', 'broken-link-notifier' ),
                'update_item'       => __( 'Update Omitted Page', 'broken-link-notifier' ),
                'add_new_item'      => __( 'Add New Omitted Page', 'broken-link-notifier' ),
                'new_item_name'     => __( 'New Omitted Page Name', 'broken-link-notifier' ),
                'menu_name'         => __( 'Omitted Pages', 'broken-link-notifier' ),
                'not_found'         => __( 'No omitted pages found.', 'broken-link-notifier' ),
            ]; 	
        }

        // Register it as a new taxonomy
        register_taxonomy( $taxonomy, 'blnotifier-results', [
            // 'hierarchical'       => false,
            'labels'             => $labels,
            'show_ui'            => true,
            'show_in_rest'       => false,
            'show_admin_column'  => false,
            'show_in_quick_edit' => false,
            // 'query_var'          => true,
            'public'             => false,
            'rewrite'            => [ 'slug' => $taxonomy, 'with_front' => false ],
        ] );
    } // End register_taxonomy()


    /**
     * Undocumented function
     * style="background-color: ; color: ; border-left-color: ;"
     * style="color: '.esc_attr( $args[ 'color_ti' ] ).' !important; margin-top: 10px !important;"
     * 
     * @return void
     */
    public function description_notice() {
        global $current_screen;
        $taxonomies = array_keys( $this->taxonomies );
        if ( $current_screen->id == 'edit-'.$taxonomies[0] ) {
            echo '<div class="notice notice-info" >
                <p>These links will be skipped during scanning and not be checked for validity.</p>
            </div>';
        } elseif ( $current_screen->id == 'edit-'.$taxonomies[1] ) {
            echo '<div class="notice notice-info" >
                <p>These pages will not be scanned for broken links.</p>
            </div>';
        }
    } // End description_notice()


    /**
     * Edit form fields
     *
     * @param WP_Term $tag
     * @param string $taxonomy
     * @return void
     */
    public function form_fields() {
        global $current_screen;
        $taxonomies = array_keys( $this->taxonomies );
        if ( $current_screen->id == 'edit-'.$taxonomies[0] || $current_screen->id == 'edit-'.$taxonomies[1] ) { ?>
            <style>
            #col-container, 
            .form-field.term-slug-wrap,
            #edittag {
                display: none;
            }
            </style>
            <script type="text/javascript">
            jQuery( $ => { 
                $( 'label[for="tag-name"], label[for="name"]' ).text( 'URL' );
                $( '#name-description' ).html( 'The link URL.<br>Accepts wildcards <strong>*</strong> (ie. <strong><?php echo esc_url( home_url() ); ?>/account/*</strong> will include all links that start with this url).' );
                $( 'label[for="tag-description"], label[for="description"]' ).text( 'Notes' );
                $( '#description-description' ).text( 'Just a place to keep notes if you need them.' );
                $( '#col-container, #edittag' ).show();
            } )
            </script>
            <?php
        }
    } // End form_fields()


    /**
     * Update admin columns for links
     *
     * @param array $columns
     * @return array
     */
    public function admin_columns_links( $columns ) {
        // Remove taxonomy column
        unset( $columns[ 'slug' ] );
        unset( $columns[ 'posts' ] );

        // Change names
        $columns[ 'name' ] = __( 'URL', 'broken-link-notifier' );
        $columns[ 'description' ] = __( 'Notes', 'broken-link-notifier' );
        return $columns;
    } // End admin_columns_links()


    /**
     * Update admin columns for pages
     *
     * @param array $columns
     * @return array
     */
    public function admin_columns_pages( $columns ) {
        // Remove taxonomy column
        unset( $columns[ 'slug' ] );
        unset( $columns[ 'posts' ] );

        // Change names and add title column
        $columns[ 'name' ] = __( 'URL', 'broken-link-notifier' );
        $columns[ 'bln-title' ] = __( 'Post/Page Title', 'broken-link-notifier' );
        $columns[ 'description' ] = __( 'Notes', 'broken-link-notifier' );
        return $columns;
    } // End admin_columns_pages()


    /**
     * Title column content
     *
     * @param [type] $value
     * @param [type] $column_name
     * @param int $term_id
     * @return string|void
     */
    public function admin_column_content_pages( $value, $column_name, $term_id ) {
        $term = get_term( $term_id, array_keys( $this->taxonomies )[1] );
        $link = sanitize_text_field( $term->name );
        if ( $post_id = url_to_postid( $link ) ) {
            return get_the_title( $post_id );
        }
        return;
    } // End admin_column_content_pages()


    /**
     * Add a link to the omits
     *
     * @param string $link
     * @param string $type Accepts links|pages
     * @return void
     */
    public function add( $link, $type, $page ) {
        // Make sure the type is legit
        if ( $type != 'links' && $type != 'pages' ) {
            return false;
        }

        // User
        $user_id = get_current_user_id();
        $user = get_user_by( 'ID', $user_id );

        // Add the taxonomy
        $omit = wp_insert_term(
            $link,
            'omit-'.$type,
            [
                'description' => 'Added by '.$user->display_name.' on '.(new BLNOTIFIER_HELPERS)->convert_timezone(),
            ]
        );
        if ( !is_wp_error( $omit ) ) {

            // Also delete it from results
            if ( $page && $page == 'scan-results' ) {
                if ( $post_id = post_exists( $link ) ) {
                    wp_delete_post( $post_id, true );
                }
            }
            return true;
        } else {
            return $omit->get_error_message();
        }
    } // End add()


    /**
     * Get omitted links or pages
     *
     * @param string $type Accepts links|pages
     * @return array
     */
    public function get( $type ) {
        $type = sanitize_key( $type );
        $omit_urls = [];
        $omits = get_terms( [
            'taxonomy'   => 'omit-'.$type ,
            'hide_empty' => false,
        ] );
        if ( !empty( $omits ) ) {
            foreach ( $omits as $omit ) {
                $omit_urls[] = $omit->name;
            }
        }
        $filtered_urls = apply_filters( 'blnotifier_omitted_' . $type, $omit_urls );
        return array_map( 'sanitize_text_field', $filtered_urls );
    } // End get()


    /**
     * Check if a page is omitted
     *
     * @param string $link
     * @return boolean
     */
    public function is_omitted( $link, $type ) {
        // Get the omits
        $omits = $this->get( $type );

        // First simple check
        if ( in_array( $link, $omits ) ) {
            return true;

        // Otherwise, 
        } else {

            // Use regex
            foreach ( $omits as $omit ) {
                $pattern = '/'.preg_quote( $omit, '/' ).'/'; 
                if ( strpos( $omit, '*' ) !== false ) {
                    $pattern = str_replace( '\*', '(.*)', $pattern );
                    if ( preg_match( $pattern, $link, $match ) ) {
                        return true;
                    }
                }
            }
        }
        return false;
    } // End is_omitted()


    /**
     * Ajax call
     *
     * @return void
     */
    public function ajax() {
        // Verify nonce
        if ( !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }
    
        // Get the ID
        $link = sanitize_text_field( $_REQUEST[ 'link' ] );
        $type = sanitize_key( $_REQUEST[ 'type' ] );
        $page = sanitize_text_field( $_REQUEST[ 'page' ] );

        // Make sure we have a source URL
        if ( $link && $type ) {

            // Add it
            $omit = $this->add( $link, $type, $page );
            if ( $omit ) {
                $result[ 'type' ] = 'success';
            } else {
                $result[ 'type' ] = 'error';
                $result[ 'msg' ] = 'Could not add taxonomy. '.$omit;
            }

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'Missing data';
        }
    
        // Echo the result or redirect
        if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( sanitize_key( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) ) == 'xmlhttprequest' ) {
            echo wp_json_encode( $result );
        } else {
            header( 'Location: '.filter_var( $_SERVER[ 'HTTP_REFERER' ], FILTER_SANITIZE_URL ) );
        }
    
        // We're done here
        die();
    } // End ajax()


    /**
     * What to do if they are not logged in
     *
     * @return void
     */
    public function must_login() {
        die();
    } // End must_login()


    /**
     * Enqueue script
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        // Only on these pages
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();
        $post_type = get_post_type();
        if ( ( $screen == $options_page && $tab == 'scan-single' ) || ( $screen == 'edit.php' && ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_blinks' ) && isset( $_GET[ 'blinks' ] ) && sanitize_key( $_GET[ 'blinks' ] ) == 'true' )  || $post_type == 'blnotifier-results' ) ) {
            if ( !$tab && $post_type == 'blnotifier-results' ) {
                $tab = 'scan-results';
            } elseif ( !$tab ) {
                $tab = 'scan-multi';
            }
            $nonce = wp_create_nonce( $this->nonce );
            $handle = 'blnotifier_omits_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'omits.js', [ 'jquery' ], BLNOTIFIER_VERSION );
            wp_localize_script( $handle, 'blnotifier_omit', [
                'scan_type' => $tab,
                'nonce'     => $nonce,
                'ajaxurl'   => admin_url( 'admin-ajax.php' ) 
            ] );
            wp_enqueue_script( $handle );
            wp_enqueue_script( 'jquery' );
        }
    } // End enqueue_scripts()
    
}