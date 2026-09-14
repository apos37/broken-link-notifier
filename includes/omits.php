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
     * Default omitted links
     *
     * @return array
     */
    public function default_omitted_links() {
        return [
            home_url( '/category/*' ),
            home_url( '/tag/*' ),
            home_url( '/wp-login.php*' ),
            home_url( '/wp-admin/*' ),
        ];
    } // End default_omitted_links()


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

        // Add the header to the top of the Omitted Links/Pages list screens
        add_action( 'load-edit-tags.php', [ $this, 'add_header' ] );

        // Move search box to the right subheader
        add_action( 'blnotifier_subheader_right', [ $this, 'render_search_box' ] );

        // Remove the Screen Options tab on these screens
        add_filter( 'screen_options_show_screen', [ $this, 'hide_screen_options' ], 10, 2 );

        // Rename "Name"/"Description" to "URL"/"Notes" on the Omitted Links/Pages screens
        add_filter( 'gettext', [ $this, 'rename_field_labels' ], 10, 3 );
        add_filter( 'gettext_with_context', [ $this, 'rename_field_labels_with_context' ], 10, 4 );
        
        // Render the page picker above the URL field on Omitted Pages' Add New form
        add_action( 'omit-pages_add_form_fields', [ $this, 'render_page_picker' ] );

        // Update admin columns
        $taxonomies = array_keys( $this->taxonomies );
        add_filter( 'manage_edit-'.$taxonomies[0].'_columns', [ $this, 'admin_columns_links' ] );
        add_filter( 'manage_edit-'.$taxonomies[1].'_columns', [ $this, 'admin_columns_pages' ] );
        add_filter( 'manage_'.$taxonomies[1].'_custom_column', [ $this, 'admin_column_content_pages' ], 10, 3 );
        add_filter( $taxonomies[0].'_row_actions', [ $this, 'modify_term_actions' ], 10, 2 );
        add_filter( $taxonomies[1].'_row_actions', [ $this, 'modify_term_actions' ], 10, 2 );

        // Ajax
        add_action( 'wp_ajax_'.$this->ajax_key, [ $this, 'ajax' ] );
        add_action( 'wp_ajax_blnotifier_omits_get_posts', [ $this, 'ajax_get_posts_for_type' ] );
        add_action( 'wp_ajax_blnotifier_omits_search_links', [ $this, 'ajax_search_discovered_links' ] );
        
        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_quick_add_script' ] );
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
                'name'                      => _x( 'Omitted Links', 'taxonomy general name', 'broken-link-notifier' ),
                'singular_name'             => _x( 'Omitted Link', 'taxonomy singular name', 'broken-link-notifier' ),
                'search_items'              => __( 'Search Omitted Links', 'broken-link-notifier' ),
                'all_items'                 => __( 'Add to Omitted Link', 'broken-link-notifier' ),
                'edit_item'                 => __( 'Edit Omitted Link', 'broken-link-notifier' ),
                'update_item'               => __( 'Update Omitted Link', 'broken-link-notifier' ),
                'add_new_item'              => __( 'Add New Omitted Link', 'broken-link-notifier' ),
                'new_item_name'             => __( 'New Omitted Link Name', 'broken-link-notifier' ),
                'menu_name'                 => __( 'Omitted Links', 'broken-link-notifier' ),
                'not_found'                 => __( 'No omitted links found.', 'broken-link-notifier' ),
                'name_field_description'    => __( 'The link URL.<br>Accepts wildcards <strong>*</strong> (ie. <strong>'.esc_url( home_url() ).'/account/*</strong> will include all links that start with this url).', 'broken-link-notifier' ),
                'desc_field_description'    => __( 'Just a place to keep notes if you need them.', 'broken-link-notifier' ),
            ]; 	
        } elseif ( $taxonomy == 'omit-pages' ) {
            $labels = [
                'name'                      => _x( 'Omitted Pages', 'taxonomy general name', 'broken-link-notifier' ),
                'singular_name'             => _x( 'Omitted Page', 'taxonomy singular name', 'broken-link-notifier' ),
                'search_items'              => __( 'Search Omitted Pages', 'broken-link-notifier' ),
                'all_items'                 => __( 'Add to Omitted Page', 'broken-link-notifier' ),
                'edit_item'                 => __( 'Edit Omitted Page', 'broken-link-notifier' ),
                'update_item'               => __( 'Update Omitted Page', 'broken-link-notifier' ),
                'add_new_item'              => __( 'Add New Omitted Page', 'broken-link-notifier' ),
                'new_item_name'             => __( 'New Omitted Page Name', 'broken-link-notifier' ),
                'menu_name'                 => __( 'Omitted Pages', 'broken-link-notifier' ),
                'not_found'                 => __( 'No omitted pages found.', 'broken-link-notifier' ),
                'name_field_description'    => __( 'The link URL.<br>Accepts wildcards <strong>*</strong> (ie. <strong>'.esc_url( home_url() ).'/account/*</strong> will include all links that start with this url).', 'broken-link-notifier' ),
                'desc_field_description'    => __( 'Just a place to keep notes if you need them.', 'broken-link-notifier' ),
            ]; 	
        } else {
            $labels = [];
        }

        // Register it as a new taxonomy
        register_taxonomy( $taxonomy, [], [
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
     * Add the header to the top of the Omitted Links/Pages admin list screens
     *
     * @return void
     */
    public function add_header() {
        $screen = get_current_screen();
        if ( isset( $screen->id ) && ( $screen->id === 'edit-omit-links' || $screen->id === 'edit-omit-pages' ) ) {
            add_action( 'in_admin_header', function() {
                include BLNOTIFIER_PLUGIN_INCLUDES_PATH.'header.php';
            } );
        }
    } // End add_header()


    /**
     * Render a search box in the right subheader on the Omitted Links/Pages screens
     *
     * @param string $active_tab
     * @return void
     */
    public function render_search_box( $active_tab ) {
        if ( $active_tab !== 'omit-links' && $active_tab !== 'omit-pages' ) {
            return;
        }

        $screen = get_current_screen();
        if ( isset( $screen->base ) && $screen->base === 'term' ) {
            return;
        }

        $search_value = isset( $_GET[ 's' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 's' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <form method="get" class="blnotifier-tax-search">
            <?php foreach ( $_GET as $key => $value ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( in_array( $key, [ 's', 'action', 'paged' ], true ) ) continue;
            ?>
                <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
            <?php endforeach; ?>

            <input type="search"
                name="s"
                value="<?php echo esc_attr( $search_value ); ?>"
                placeholder="<?php echo esc_attr( $active_tab === 'omit-links' ? 'Search Omitted Links' : 'Search Omitted Pages' ); ?>"
                class="blnotifier-search-input" />

            <input type="submit"
                class="blnotifier-button"
                value="Search" />
        </form>
        <?php
    } // End render_search_box()


    /**
     * Hide the Screen Options tab on the Omitted Links/Pages screens
     *
     * @param boolean $show_screen
     * @param WP_Screen $screen
     * @return boolean
     */
    public function hide_screen_options( $show_screen, $screen ) {
        if ( isset( $screen->id ) && ( $screen->id === 'edit-omit-links' || $screen->id === 'edit-omit-pages' ) ) {
            return false;
        }
        return $show_screen;
    } // End hide_screen_options()


    /**
     * Rename form field labels/descriptions on the Omitted Links/Pages screens
     *
     * @param string $translation
     * @param string $text
     * @param string $domain
     * @return string
     */
    public function rename_field_labels( $translation, $text, $domain ) {
        if ( !function_exists( 'get_current_screen' ) || !is_admin() ) {
            return $translation;
        }

        $result = $this->maybe_rename_label( $text );
        return $result !== null ? $result : $translation;
    } // End rename_field_labels()


    /**
     * Rename form field labels/descriptions that WP core wraps with context
     *
     * @param string $translation
     * @param string $text
     * @param string $context
     * @param string $domain
     * @return string
     */
    public function rename_field_labels_with_context( $translation, $text, $context, $domain ) {
        if ( !function_exists( 'get_current_screen' ) || !is_admin() ) {
            return $translation;
        }

        $result = $this->maybe_rename_label( $text );
        return $result !== null ? $result : $translation;
    } // End rename_field_labels_with_context()


    /**
     * Shared logic: check if this string is one we rename, and if we're on the right screen
     *
     * @param string $text
     * @return string|null Null if this string/screen doesn't apply
     */
    private function maybe_rename_label( $text ) {
        $relevant_strings = [
            'Name',
            'Description',
        ];
        if ( !in_array( $text, $relevant_strings, true ) ) {
            return null;
        }

        $screen = get_current_screen();
        if ( !$screen ) {
            return null;
        }

        $is_taxonomy_screen = ( isset( $screen->id ) && ( $screen->id === 'edit-omit-links' || $screen->id === 'edit-omit-pages' ) )
            || ( isset( $screen->taxonomy ) && in_array( $screen->taxonomy, [ 'omit-links', 'omit-pages' ], true ) );

        if ( !$is_taxonomy_screen ) {
            return null;
        }

        switch ( $text ) {
            case 'Name':
                return 'URL';
            case 'Description':
                return 'Notes';
            default:
                return null;
        }
    } // End maybe_rename_label()


    /**
     * Render the post-type/page picker above the URL field on Omitted Pages' Add New form
     *
     * @param string $taxonomy
     * @return void
     */
    public function render_page_picker( $taxonomy ) {
        if ( $taxonomy !== 'omit-pages' ) {
            return;
        }

        $post_types = $this->get_scannable_post_type_choices();
        ?>
        <div class="form-field" id="bln-quick-add-field">
            <label for="bln-quick-add-post-type"><?php esc_html_e( 'Quick Add', 'broken-link-notifier' ); ?></label>
            <select name="bln_quick_add_post_type" id="bln-quick-add-post-type" class="blnotifier-select-field">
                <option value=""><?php esc_html_e( 'Choose a Post Type...', 'broken-link-notifier' ); ?></option>
                <?php foreach ( $post_types as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="bln_quick_add_post" id="bln-quick-add-post" class="blnotifier-select-field" disabled>
                <option value=""><?php esc_html_e( 'Choose a Post Type First...', 'broken-link-notifier' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Optionally pick a page to auto-fill the URL field below.', 'broken-link-notifier' ); ?></p>
        </div>
        <?php
    } // End render_page_picker()


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
     * Modify the action links on taxonomy term rows.
     *
     * @param array   $actions Array of action links.
     * @param WP_Term $term    The current term object.
     *
     * @return array
     */
    public function modify_term_actions( $actions, $term ) {
        if ( isset( $actions[ 'delete' ] ) ) {
            $actions[ 'delete' ] = str_replace( 'Delete', 'Remove Omission', $actions[ 'delete' ] );
        }

        return $actions;
    } // End modify_term_actions()


    /**
     * Add a link to the omits
     *
     * @param string $link
     * @param string $type Accepts links|pages
     * @param string $page
     * @param string|null $note_override Optional custom note instead of the default "Added by user on date"
     * @return boolean|string
     */
    public function add( $link, $type, $page, $note_override = null ) {
        // Make sure the type is legit
        if ( $type != 'links' && $type != 'pages' ) {
            return false;
        }

        if ( $note_override !== null ) {
            $description = $note_override;
        } else {
            // User
            $user_id = get_current_user_id();
            $user = get_user_by( 'ID', $user_id );
            $description = 'Added by '.$user->display_name.' on '.(new BLNOTIFIER_HELPERS())->convert_timezone();
        }

        // Add the taxonomy
        $omit = wp_insert_term(
            $link,
            'omit-'.$type,
            [
                'description' => $description,
            ]
        );
        if ( !is_wp_error( $omit ) ) {

            // Also delete it from results
            if ( $page && $page == 'scan-results' ) {
                (new BLNOTIFIER_RESULTS)->remove( $link );
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

        if ( $type === 'links' ) {
            $omit_urls = $this->default_omitted_links();
        }

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
     * @param string $type Accepts links|pages
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
        $nonce = isset( $_REQUEST[ 'nonce' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ) : '';
        if ( !wp_verify_nonce( $nonce, $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }
        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            exit( 'Unauthorized access.' );
        }
    
        // Get parameters safely
        $link = isset( $_REQUEST[ 'link' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'link' ] ) ) : '';
        $type = isset( $_REQUEST[ 'type' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'type' ] ) ) : '';
        $page = isset( $_REQUEST[ 'page' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'page' ] ) ) : '';
    
        // Make sure we have a source URL
        if ( $link && $type ) {
    
            // Add it
            $omit = $this->add( $link, $type, $page );
            if ( $omit ) {
                $result[ 'type' ] = 'success';
            } else {
                $result[ 'type' ] = 'error';
                $result[ 'msg' ] = 'Could not add taxonomy. ' . $omit;
            }
    
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'Missing data';
        }
    
        // Echo the result or redirect
        if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( sanitize_key( wp_unslash( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) ) ) === 'xmlhttprequest' ) {
            echo wp_json_encode( $result );
        } else {
            $referer = isset( $_SERVER[ 'HTTP_REFERER' ] ) ? filter_var( wp_unslash( $_SERVER[ 'HTTP_REFERER' ] ), FILTER_SANITIZE_URL ) : '';
            header( 'Location: ' . $referer );
        }
    
        die();
    } // End ajax()    


    /**
     * Get post type choices limited to those actually enabled for scanning
     *
     * @return array
     */
    public function get_scannable_post_type_choices() {
        $HELPERS = new BLNOTIFIER_HELPERS;
        $results = [];
        foreach ( $HELPERS->get_allowed_multiscan_post_types() as $post_type ) {
            $results[ $post_type ] = $HELPERS->get_post_type_name( $post_type );
        }
        return apply_filters( 'blnotifier_omit_quick_add_post_types', $results );
    } // End get_scannable_post_type_choices()


    /**
     * Ajax: get posts of a given post type, for the quick-add dropdown
     *
     * @return void
     */
    public function ajax_get_posts_for_type() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid nonce.' ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => 'Unauthorized.' ] );
        }

        $post_type = isset( $_REQUEST[ 'post_type' ] ) ? sanitize_key( wp_unslash( $_REQUEST[ 'post_type' ] ) ) : '';
        $allowed_types = (new BLNOTIFIER_HELPERS)->get_allowed_multiscan_post_types();

        if ( !$post_type || !in_array( $post_type, $allowed_types, true ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid post type.' ] );
        }

        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'private', 'draft', 'pending' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );

        $items = [];
        foreach ( $posts as $post_id ) {
            $items[] = [
                'id'    => $post_id,
                'title' => get_the_title( $post_id ) ?: '(no title)',
                'url'   => get_the_permalink( $post_id ),
            ];
        }

        wp_send_json_success( [ 'items' => $items ] );
    } // End ajax_get_posts_for_type()


    /**
     * Ajax: search discovered links (from Link Browser/Site Scan) for the Omitted Links autocomplete
     *
     * @return void
     */
    public function ajax_search_discovered_links() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid nonce.' ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => 'Unauthorized.' ] );
        }

        $search = isset( $_REQUEST[ 'search' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'search' ] ) ) : '';
        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( [ 'items' => [] ] );
        }

        $LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
        if ( !$LINK_BROWSER->table_exists() ) {
            wp_send_json_success( [ 'items' => [] ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'blnotifier_links';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT link, type FROM $table_name WHERE link LIKE %s ORDER BY link ASC LIMIT 15",
            '%' . $wpdb->esc_like( $search ) . '%'
        ) ); // phpcs:ignore

        $items = [];
        foreach ( $rows as $row ) {
            $items[] = [
                'link' => $row->link,
                'type' => $row->type,
            ];
        }

        wp_send_json_success( [ 'items' => $items ] );
    } // End ajax_search_discovered_links()


    /**
     * Enqueue the term-form label/description swap and quick-add script
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_quick_add_script( $screen ) {
        if ( $screen !== 'edit-tags.php' ) {
            return;
        }

        global $current_screen;
        if ( !isset( $current_screen->id ) || ( $current_screen->id !== 'edit-omit-links' && $current_screen->id !== 'edit-omit-pages' ) ) {
            return;
        }

        wp_enqueue_style( 'blnotifier-omits', BLNOTIFIER_PLUGIN_CSS_PATH.'omits.css', [], BLNOTIFIER_SCRIPT_VERSION );

        if ( $current_screen->id === 'edit-omit-pages' ) {
            // Quick-add page picker for Omitted Pages
            $quick_add_handle = 'blnotifier_omits_quick_add_script';
            wp_register_script( $quick_add_handle, BLNOTIFIER_PLUGIN_JS_PATH.'omits-quick-add.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
            wp_localize_script( $quick_add_handle, 'blnotifier_omits_quick_add', [
                'post_types' => $this->get_scannable_post_type_choices(),
                'nonce'      => wp_create_nonce( $this->nonce ),
                'ajaxurl'    => admin_url( 'admin-ajax.php' ),
            ] );
            wp_enqueue_script( $quick_add_handle );

        } elseif ( $current_screen->id === 'edit-omit-links' ) {
            // Link autocomplete for Omitted Links
            $autocomplete_handle = 'blnotifier_omits_link_autocomplete_script';
            wp_register_script( $autocomplete_handle, BLNOTIFIER_PLUGIN_JS_PATH.'omits-link-autocomplete.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
            wp_localize_script( $autocomplete_handle, 'blnotifier_omits_link_autocomplete', [
                'nonce'   => wp_create_nonce( $this->nonce ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ] );
            wp_enqueue_script( $autocomplete_handle );
        }
    } // End enqueue_quick_add_script()


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

        // Taxonomy screens get their own stylesheet (both the list table and the single-term edit page)
        if ( $screen === 'edit-tags.php' || $screen === 'term.php' ) {
            $current_screen = get_current_screen();
            if ( isset( $current_screen->id ) && ( $current_screen->id === 'edit-omit-links' || $current_screen->id === 'edit-omit-pages' ) ) {
                wp_enqueue_style( 'blnotifier-taxonomies', BLNOTIFIER_PLUGIN_CSS_PATH.'taxonomies.css', [ 'blnotifier-theme' ], BLNOTIFIER_SCRIPT_VERSION );
            }
        }

        if ( 
            ( $screen == $options_page && $tab == 'scan-single' ) || 
            ( $screen == $options_page && $tab == 'results' ) ||
            ( $screen == 'edit.php' && 
                ( 
                    isset( $_REQUEST[ '_wpnonce' ] ) && 
                    wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), 'blnotifier_blinks' ) && 
                    isset( $_GET[ 'blinks' ] ) && 
                    sanitize_key( $_GET[ 'blinks' ] ) == 'true' 
                ) 
            ) 
        ) {
        
        if ( $tab == 'results' ) {
                $tab = 'scan-results';
            } elseif ( !$tab ) {
                $tab = 'scan-multi';
            }
            $nonce = wp_create_nonce( $this->nonce );
            $handle = 'blnotifier_omits_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'omits.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
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