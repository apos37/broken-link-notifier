<?php
/**
 * Admin options page
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
if ( !is_network_admin() ) {
    add_action( 'init', function() {
        (new BLNOTIFIER_MENU)->init();
    } );
}


/**
 * Main plugin class.
 */
class BLNOTIFIER_MENU {

    /**
     * The menu slug
     *
     * @var string
     */
    public $page_slug = 'blnotifier-settings';


    /**
     * The menu items
     *
     * @var array
     */
    public $menu_items;


    /**
	 * Constructor
	 */
	public function __construct() {

        // The menu items
        $this->menu_items = [
            'results'     => [ __( 'Results', 'broken-link-notifier' ), 'edit.php?post_type=blnotifier-results' ],
            'omit-links'  => [ __( 'Omitted Links', 'broken-link-notifier' ), 'edit-tags.php?taxonomy=omit-links&post_type=blnotifier-results' ],
            'omit-pages'  => [ __( 'Omitted Pages', 'broken-link-notifier' ), 'edit-tags.php?taxonomy=omit-pages&post_type=blnotifier-results' ],
            'scan-single' => [ __( 'Page Scan', 'broken-link-notifier' ) ],
            'scan-multi'  => [ __( 'Multi-Scan', 'broken-link-notifier' ) ],
            'settings'    => [ __( 'Settings', 'broken-link-notifier' ) ],
            'link-search' => [ __( 'Link Search', 'broken-link-notifier' ) ],
            'help'        => [ __( 'Help', 'broken-link-notifier' ) ],
        ];

	} // End __construct()


    /**
	 * Load on init
	 */
	public function init() {

        // Add the menu
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );

        // Fix the Manage link to show active
        add_filter( 'parent_file', [ $this, 'submenus' ] );

        // Settings page fields
        add_action( 'admin_init', [  $this, 'settings_fields' ] );

        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	} // End init()


    /**
     * Add page to Tools menu
     * 
     * @return void
     */
    public function admin_menu() {
        // Capability
        $capability = sanitize_key( apply_filters( 'blnotifier_capability', 'manage_options' ) );

        // Count broken links
        $count = (new BLNOTIFIER_HELPERS)->count_broken_links();
        $notif = $count > 0 ? ' <span class="awaiting-mod">'.(new BLNOTIFIER_HELPERS)->count_broken_links().'</span>' : '';

        // Add the menu
        add_menu_page(
            BLNOTIFIER_NAME,
            BLNOTIFIER_NAME. $notif,
            $capability,
            BLNOTIFIER_TEXTDOMAIN,
            [ $this, 'settings_page' ],
            'dashicons-editor-unlink'
        );

        // Add the submenus
        global $submenu;
        foreach ( $this->menu_items as $key => $menu_item ) {
            $link = isset( $menu_item[1] ) ? $menu_item[1] : 'admin.php?page='.BLNOTIFIER_TEXTDOMAIN.'&tab='.$key;
            $submenu[ BLNOTIFIER_TEXTDOMAIN ][] = [ $menu_item[0], $capability, $link ];
        }
    } // End admin_menu()


    /**
     * Fix the Manage link to show active
     *
     * @param string $parent_file
     * @return string
     */
    public function submenus( $parent_file ) {
        global $submenu_file, $current_screen;
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;

        // Top level page
        if ( $current_screen->id == $options_page ) {
            $tab = (new BLNOTIFIER_HELPERS)->get_tab() ?? '';
            $submenu_file = 'admin.php?page='.BLNOTIFIER_TEXTDOMAIN.'&tab='.$tab;

        // Taxonomies first
        } elseif ( $current_screen->id == 'edit-omit-links' ) {
            $submenu_file = 'edit-tags.php?taxonomy=omit-links&post_type=blnotifier-results';
            $parent_file = $this->get_plugin_page_short_path( null );
        } elseif ( $current_screen->id == 'edit-omit-pages' ) {
            $submenu_file = 'edit-tags.php?taxonomy=omit-pages&post_type=blnotifier-results';
            $parent_file = $this->get_plugin_page_short_path( null );
        
        // Post Type
        } elseif ( $current_screen->post_type == 'blnotifier-results' ) {
            $submenu_file = 'edit.php?post_type=blnotifier-results';
            $parent_file = $this->get_plugin_page_short_path();
        }

        // Return
        return $parent_file;
    } // End submenus()


    /**
     * Settings page
     *
     * @return void
     */
    public function settings_page() {
        include BLNOTIFIER_PLUGIN_INCLUDES_PATH.'page.php';
    } // End settings_page()

    
    /**
     * Settings fields
     *
     * @return void
     */
    public function settings_fields() {
        // Add section
        add_settings_section( 
            'general',
            'Settings',
            '',
            $this->page_slug
        );

        // Has updated settings
        $has_updated_settings = 'blnotifier_has_updated_settings';
        register_setting( $this->page_slug, $has_updated_settings, [ $this, 'sanitize_boolean' ] );

        // Pause front-end scanning
        $pause_frontend_scanning_option_name = 'blnotifier_pause_frontend_scanning';
        register_setting( $this->page_slug, $pause_frontend_scanning_option_name, [ $this, 'sanitize_checkbox' ] );
        add_settings_field(
            $pause_frontend_scanning_option_name,
            'Pause Front-End Scanning',
            [ $this, 'field_checkbox' ],
            $this->page_slug,
            'general',
            [
                'class'    => $pause_frontend_scanning_option_name,
                'name'     => $pause_frontend_scanning_option_name,
                'default'  => false,
                'comments' => 'You can pause front-end scanning if you just want to scan manually; disabling this means you will NOT get notified when someone visits a page with broken links'
            ]
        );

        // Pause results verification
        $pause_results_verification_option_name = 'blnotifier_pause_results_verification';
        register_setting( $this->page_slug, $pause_results_verification_option_name, [ $this, 'sanitize_checkbox' ] );
        add_settings_field(
            $pause_results_verification_option_name,
            'Pause Results Auto-Verification',
            [ $this, 'field_checkbox' ],
            $this->page_slug,
            'general',
            [
                'class'    => $pause_results_verification_option_name,
                'name'     => $pause_results_verification_option_name,
                'default'  => false,
                'comments' => 'You can pause the automatic verification and removal on the results page'
            ]
        );

        // Enable emailing
        $enable_emailing_option_name = 'blnotifier_enable_emailing';
        register_setting( $this->page_slug, $enable_emailing_option_name, [ $this, 'sanitize_checkbox' ] );
        add_settings_field(
            $enable_emailing_option_name,
            'Enable Emailing',
            [ $this, 'field_checkbox' ],
            $this->page_slug,
            'general',
            [
                'class'    => $enable_emailing_option_name,
                'name'     => $enable_emailing_option_name,
                'default'  => true,
                'comments' => 'You can turn off email notifications and still get website notifications'
            ]
        );

        // Emails
        $emails_option_name = 'blnotifier_emails';
        register_setting( $this->page_slug, $emails_option_name, 'sanitize_text_field' );
        add_settings_field(
            $emails_option_name,
            'Emails to Send Notifications',
            [ $this, 'field_emails' ],
            $this->page_slug,
            'general',
            [
                'class'    => $emails_option_name,
                'name'     => $emails_option_name,
                'default'  => get_bloginfo( 'admin_email' ),
                'comments' => 'Separated by commas'
            ]
        );

        // Webhook fields
        $webhook_fields = [
            [ 
                'name'     => 'discord',
                'label'    => 'Discord',
                'comments' => 'URL should look like this: https://discord.com/api/webhooks/xxx/xxx...'
            ],
            [ 
                'name'     => 'msteams',
                'label'    => 'Microsoft Teams',
                'comments' => 'URL should look like this: https://yourdomain.webhook.office.com/xxx/xxx...'
            ]
        ];
        foreach ( $webhook_fields as $webhook_field ) {

            // Enable checkbox
            $enable_option_name = 'blnotifier_enable_'.$webhook_field[ 'name' ];
            register_setting( $this->page_slug, $enable_option_name, [ $this, 'sanitize_checkbox' ] );
            add_settings_field(
                $enable_option_name,
                'Enable '.$webhook_field[ 'label' ].' Notifications',
                [ $this, 'field_checkbox' ],
                $this->page_slug,
                'general',
                [
                    'class'    => $enable_option_name,
                    'name'     => $enable_option_name,
                    'default'  => false,
                    'comments' => 'You can also send notifications to a '.$webhook_field[ 'label' ].' channel'
                ]
            );

            // The url
            $url_field_option_name = 'blnotifier_'.$webhook_field[ 'name' ];
            register_setting( $this->page_slug, $url_field_option_name, [ $this, 'sanitize_url' ] );
            add_settings_field(
                $url_field_option_name,
                $webhook_field[ 'label' ].' Webhook URL',
                [ $this, 'field_url' ],
                $this->page_slug,
                'general',
                [
                    'class'    => $url_field_option_name,
                    'name'     => $url_field_option_name,
                    'default'  => '',
                    'comments' => $webhook_field[ 'comments' ]
                ]
            );
        }

        // Text
        $user_agent_option_name = 'blnotifier_user_agent';
        register_setting( $this->page_slug, $user_agent_option_name, 'sanitize_text_field' );
        add_settings_field(
            $user_agent_option_name,
            'User Agent',
            [ $this, 'field_text' ],
            $this->page_slug,
            'general',
            [
                'class'    => $user_agent_option_name,
                'name'     => $user_agent_option_name,
                'default'  => 'WordPress/{blog_version}; {blog_url}',
                'comments' => 'Only change this if you know what you are doing. Default is "WordPress/{blog_version}; {blog_url}" (WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . ')'
            ]
        );

        // Define an array of number fields
        $number_fields = [
            [ 
                'name'     => 'timeout',
                'label'    => 'Timeout (seconds)',
                'default'  => 5,
                'min'      => 5,
                'comments' => 'How long to try to connect to a link\'s server before quitting'
            ],
            [ 
                'name'     => 'max_redirects',
                'label'    => 'Max Redirects',
                'default'  => 5,
                'min'      => 0,
                'comments' => 'Maximum number of redirects before giving up on a link (will only be used if you allow redirects below)'
            ]
        ];

        // Loop through the array to add number fields
        foreach ( $number_fields as $field ) {
            $field_option_name = 'blnotifier_' . $field[ 'name' ];
            
            // Register the field with a sanitization callback
            register_setting( $this->page_slug, $field_option_name, 'absint' );
            
            // Add the number field to the settings page
            add_settings_field(
                $field_option_name,
                $field[ 'label' ],
                [ $this, 'field_number' ],
                $this->page_slug,
                'general',
                [
                    'class'    => $field_option_name,
                    'name'     => $field_option_name,
                    'default'  => $field[ 'default' ],
                    'min'      => $field[ 'min' ],
                    'comments' => $field[ 'comments' ]
                ]
            );
        }

        // Other checkboxes
        $checkboxes = [
            [ 
                'name'     => 'allow_redirects',
                'label'    => 'Allow Redirects',
                'default'  => true,
                'comments' => 'Changes the method of checking for broken links from <code>HEAD</code> to <code>GET</code>. May cause issues linking to larger documents.'
            ],
            [ 
                'name'     => 'documents_use_head',
                'label'    => 'Force Documents to Use <code>HEAD</code> Requests',
                'default'  => false,
                'comments' => 'If you have enabled allowing redirects (above), by default images, videos, and audio files force the use of <code>HEAD</code> requests rather than <code>GET</code>. Some servers automatically block <code>HEAD</code> requests for documents, so we don\'t force them by default. If you are having issues with large documents not completing a scan, then you can try enabling this option to see if it helps. If they are blocked, at least you will know why.'
            ],
            [ 
                'name'     => 'enable_warnings',
                'label'    => 'Enable Warnings',
                'default'  => true,
                'comments' => 'Includes warnings in all scans'
            ],
            [ 
                'name'     => 'enable_delete_source',
                'label'    => 'Enable Delete Source Action Link',
                'default'  => false,
                'comments' => 'An action link will appear on the Results tab under the source where you can trash the page entirely'
            ],
            [ 
                'name'     => 'include_images', 
                'label'    => 'Check for Broken Images',
                'default'  => true,
                'comments' => 'Includes image src links in all scans'
            ],
            [ 
                'name'     => 'ssl_verify', 
                'label'    => 'Warn if SSL is Not Verified',
                'default'  => true,
                'comments' => 'If you are not concerned about insecure links, you can disable this'
            ],
            [ 
                'name'     => 'scan_header', 
                'label'    => 'Scan <code>&#x3c;header&#x3e;</code> Elements', 
                'default'  => false,
                'comments' => 'Only applies to page load scans - the header elements usually include the navigation menu(s) at the top of the page'
            ],
            [ 
                'name'     => 'scan_footer', 
                'label'    => 'Scan <code>&#x3c;footer&#x3e;</code> Elements', 
                'default'  => false,
                'comments' => 'Only applies to page load scans - the footer elements include any links at the bottom of every page'
            ],
            [ 
                'name'     => 'show_in_console', 
                'label'    => 'Show Results in Dev Console', 
                'default'  => false,
                'comments' => 'Only applies to page load scans'
            ]
        ];
        foreach ( $checkboxes as $checkbox ) {
            $checkbox_option_name = 'blnotifier_'.$checkbox[ 'name' ];
            register_setting( $this->page_slug, $checkbox_option_name, [ $this, 'sanitize_checkbox' ] );
            add_settings_field(
                $checkbox_option_name,
                $checkbox[ 'label' ],
                [ $this, 'field_checkbox' ],
                $this->page_slug,
                'general',
                [
                    'class'    => $checkbox_option_name,
                    'name'     => $checkbox_option_name,
                    'default'  => $checkbox[ 'default' ],
                    'comments' => $checkbox[ 'comments' ]
                ]
            );
        }

        // Caching
        $cache_option_name = 'blnotifier_cache';
        register_setting( $this->page_slug, $cache_option_name, 'sanitize_text_field' );
        add_settings_field(
            $cache_option_name,
            'Length of Time to Cache Good Links (in Seconds)',
            [ $this, 'field_number' ],
            $this->page_slug,
            'general',
            [
                'class'    => $cache_option_name,
                'name'     => $cache_option_name,
                'default'  => 0,
                'comments' => 'Use 0 to disable caching. If you are experienced performance issues, you can set the value to 28800 (8 hours), 43200 (12 hours), 86400 (24 hours) or whatever you feel is best. Broken and warning links will never be cached. Deactivating or uninstalling the plugin will clear the cache completely.'
            ]
        );

        // Post types
        $post_types_option_name = 'blnotifier_post_types';
        register_setting( $this->page_slug, $post_types_option_name, [ $this, 'sanitize_checkboxes' ] );
        add_settings_field(
            $post_types_option_name,
            'Enable Multi-Scan for These Post Types',
            [ $this, 'field_checkboxes' ],
            $this->page_slug,
            'general',
            [
                'class'    => $post_types_option_name,
                'name'     => $post_types_option_name,
                'options'  => $this->get_post_type_choices(),
                'default'  => [ 'post', 'page' ]
            ]
        );

        // Status codes
        $status_codes_option_name = 'blnotifier_status_codes';
        register_setting( $this->page_slug, $status_codes_option_name, [] );
        add_settings_field(
            $status_codes_option_name,
            'Status Codes',
            [ $this, 'field_status_codes' ],
            $this->page_slug,
            'general',
            [
                'class'    => $status_codes_option_name,
                'name'     => $status_codes_option_name,
                'options'  => (new BLNOTIFIER_HELPERS)->get_status_codes(),
            ]
        );   
    } // End settings_fields()


    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function field_text( $args ) {
        printf(
            '<input type="text" id="%s" name="%s" value="%s"/><br><p class="description">%s</p>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_html( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            esc_html( $args[ 'comments' ] )
        );
    } // End field_text()


    /**
     * Custom callback function to print url field
     *
     * @param array $args
     * @return void
     */
    public function field_url( $args ) {
        printf(
            '<input type="url" id="%s" name="%s" value="%s"/><br><p class="description">%s</p>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_url( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            esc_html( $args[ 'comments' ] )
        );
    } // End field_url()


    /**
     * Sanitize url
     *
     * @param string $value
     * @return string
     */
    public function sanitize_url( $value ) {
        return filter_var( $value, FILTER_SANITIZE_URL );
    } // End sanitize_url()


    /**
     * Custom callback function to print checkbox field
     *
     * @param array $args
     * @return void
     */
    public function field_checkbox( $args ) {
        $has_updated_settings = get_option( 'blnotifier_has_updated_settings' );
        if ( !$has_updated_settings ) {
            $value = isset( $args[ 'default' ] ) ? $args[ 'default' ] : false;
        } else {
            $value = $this->sanitize_checkbox( get_option( $args[ 'name' ] ) );
        }
        printf(
            '<input type="checkbox" id="%s" name="%s" value="yes" %s/> <p class="description">%s</p>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_html( checked( 1, $value, false ) ),
            wp_kses( $args[ 'comments' ], [ 'code' => [] ] )
        );        
    } // End field_checkbox()


    /**
     * Sanitize checkbox
     *
     * @param int $value
     * @return boolean
     */
    public function sanitize_checkbox( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkbox()


    /**
     * Custom callback function to print checkboxes field
     *
     * @param array $args
     * @return void
     */
    public function field_checkboxes( $args ) {
        $value = get_option( $args[ 'name' ] );
        if ( get_option( 'blnotifier_has_updated_settings' ) ) {
            $value = !empty( $value ) ? array_keys( $value ) : [];
        } else {
            $value = $args[ 'default' ];
        }
        
        if ( isset( $args[ 'options' ] ) ) {
            foreach ( $args[ 'options' ] as $key => $label ) {
                $checked = in_array( $key, $value ) ? 'checked' : '';
                printf(
                    '<input type="checkbox" id="%s" name="%s[%s]" value="1" %s/> <label for="%s">%s</label><br>',
                    esc_html( $args[ 'name' ].'_'.$key ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $key ),
                    esc_html( $checked ),
                    esc_html( $args[ 'name' ].'_'.$key ),
                    esc_html( $label )
                );
            }
        }
    } // field_checkboxes()


    /**
     * Custom callback function to print status codes field
     *
     * @param array $args
     * @return void
     */
    public function field_status_codes( $args ) {
        $value = filter_var_array( get_option( $args[ 'name' ], [] ), FILTER_SANITIZE_SPECIAL_CHARS );

        $HELPERS = new BLNOTIFIER_HELPERS;
        $broken = $HELPERS->get_bad_status_codes();
        $warning = $HELPERS->get_warning_status_codes( true );

        if ( empty( $value ) ) {
            $mark_zero_as_broken = filter_var( get_option( 'blnotifier_mark_code_zero_broken' ), FILTER_VALIDATE_BOOLEAN );
            if ( $mark_zero_as_broken ) {
                $value[ 0 ] = 'broken'; 
            } else {
                $value[ 0 ] = 'warning'; 
            }
            
            foreach ( $broken as $code ) {
                $value[ $code ] = 'broken';
            }
            
            foreach ( $warning as $code ) {
                if ( !in_array( $code, array_keys( $value ) ) ) {
                    $value[ $code ] = 'warning';
                }
            }
        }
    
        if ( isset( $args[ 'options' ] ) ) {
            echo '<style>
                .status-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 2rem;
                    border-radius: 5px;
                    border: 1px solid #ccc;
                    padding: 10px;
                    font-weight: bold;
                }
                .status-row.warning .type {
                    background: yellow;
                    color: black;
                }
                .status-row.broken .type {
                    background: red;
                    color: white;
                }
                .status-row.good .type {
                    background: #008000;
                    color: white;
                }
                .status-row .type {
                    float: right;
                    padding: 5px 10px;
                    font-weight: bold;
                    margin: 10px;
                    width: 100px;
                    text-align: center;
                    text-transform: uppercase;
                    box-shadow: 0 2px 4px 0 rgba(7, 36, 86, 0.075);
                    border: 1px solid rgba(7, 36, 86, 0.075);
                    border-radius: 10px;
                }
                .status-row .code-msg, 
                .status-row .description {
                    margin-bottom: 1rem;
                    margin-left: 0 !important;
                }
                .status-row label {
                    margin-right: 10px;
                }
                .status-container {
                    margin-top: 1rem;
                    display: none; /* Initially hidden */
                }
                .toggle-link {
                    cursor: pointer;
                    color: #0073aa;
                    text-decoration: underline;
                    margin-bottom: 10px;
                    display: inline-block;
                }
            </style>';

            echo '<strong>Broken Status Codes:</strong> ' . esc_html( !empty( $broken ) ? implode( ', ', $broken ) : 'None' ).'<br>';
            echo '<strong>Warning Status Codes:</strong> ' . esc_html( !empty( $warning ) ? implode( ', ', $warning ) : 'None' ).'<br><br><br>';

            echo '<a class="toggle-link" data-target="status-container">View/Change Status Types</a>';
            echo '<div class="status-container">';

            foreach ( $args[ 'options' ] as $code => $c ) {
                $type = isset( $value[ $code ] ) ? $value[ $code ] : 'good';
                $checked_good = $type === 'good' ? 'checked' : '';
                $checked_warning = $type === 'warning' ? 'checked' : '';
                $checked_broken = $type === 'broken' ? 'checked' : '';
                $display_code = isset( $c[ 'official' ] ) && !$c[ 'official' ] ? $code : '<a href="https://http.dev/' . $code . '" target="_blank">' . $code . '</a>';
    
                printf(
                    '<div class="status-row ' . esc_attr( $type ) . '">
                        <div class="info-input">
                            <div class="code-msg">
                                <span class="code">%s</span> <span class="message">(%s)</span>
                            </div>
                            <div class="description">%s</div>
                            <div class="selections">
                                <input type="radio" id="%s_good" name="%s[%s]" value="good" %s/> 
                                <label for="%s_good">Good</label>
                                <input type="radio" id="%s_warning" name="%s[%s]" value="warning" %s/> 
                                <label for="%s_warning">Warning</label>
                                <input type="radio" id="%s_broken" name="%s[%s]" value="broken" %s/> 
                                <label for="%s_broken">Broken</label>
                            </div>
                        </div>
                        <div class="indicator">
                            <div class="type">%s</div>
                        </div>
                    </div><br><br>',
                    wp_kses( $display_code, [ 'a' => [ 'href' => [], 'target' => [] ] ] ),
                    esc_html( $c[ 'msg' ] ),
                    wp_kses( $c[ 'desc' ], [ 'code' ] ),

                    esc_html( $args[ 'name' ].'_'.$code ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $code ),
                    esc_html( $checked_good ),
                    esc_html( $args[ 'name' ].'_'.$code ),

                    esc_html( $args[ 'name' ].'_'.$code ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $code ),
                    esc_html( $checked_warning ),
                    esc_html( $args[ 'name' ].'_'.$code ),

                    esc_html( $args[ 'name' ].'_'.$code ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $code ),
                    esc_html( $checked_broken ),
                    esc_html( $args[ 'name' ].'_'.$code ),

                    esc_attr( strtoupper( $type ) )
                );
            }

            echo '</div>';
        }
    } // End field_status_codes()


    /**
     * Sanitize checkboxes
     *
     * @param array $value
     * @return boolean
     */
    public function sanitize_checkboxes( $value ) {
        if ( !is_null( $value ) ) {
            return filter_var_array( $value, FILTER_VALIDATE_BOOLEAN );
        } else {
            return [];
        }
    } // End sanitize_checkboxes()


    /**
     * Sanitize boolean
     *
     * @param mixed $input
     * @return boolean
     */
    public function sanitize_boolean( $input ) {
        return (bool) $input;
    } // End sanitize_boolean()


    /**
     * Get post type choices
     *
     * @return array
     */
    public function get_post_type_choices() {
        $HELPERS = new BLNOTIFIER_HELPERS;
        $results = [];
        $post_types = $HELPERS->get_post_types();
        foreach ( $post_types as $post_type ) {
            $post_type_name = $HELPERS->get_post_type_name( $post_type );
            $results[ $post_type ] = $post_type_name;
        }
        return $results;
    } // End get_post_type_choices()


    /**
     * Custom callback function to print multiple emails field
     *
     * @param array $args
     * @return void
     */
    public function field_emails( $args ) {
        printf(
            '<input type="text" id="%s" name="%s" value="%s" pattern="%s"/><br><p class="description">%s</p>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_html( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            '([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+)(\s*,\s*([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+))*',
            esc_html( $args[ 'comments' ] )
        );
    } // field_text()


    /**
     * Custom callback function to print number field
     *
     * @param array $args
     * @return void
     */
    public function field_number( $args ) {
        printf(
            '<input type="number" id="%s" name="%s" value="%d" min="%d" required/><br><p class="description">%s</p>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_attr( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            esc_attr( $args[ 'min' ] ),
            esc_html( $args[ 'comments' ] )
        );
    } // End field_number()


    /**
     * Custom callback function to print textarea field
     * 
     * @param array $args
     * @return void
     */
    public function field_textarea( $args ) {
        printf(
            '<textarea class="textarea" id="%s" name="%s"/>%s</textarea><br><p class="description">%s</p>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_html( get_option( $args[ 'name' ], '' ) ),
            esc_html( $args[ 'comments' ] )
        );
    } // field_text()


    /**
     * Get the full plugin page path
     *
     * @param string $tab
     * @return string
     */
    public function get_plugin_page( $tab = 'settings' ) {
        if ( $tab == 'results' ) {
            return admin_url( 'edit.php?post_type=blnotifier-results' );
        } elseif ( $tab == 'omit-links' || $tab == 'omit-pages' ) {
            return admin_url( 'edit-tags.php?taxonomy='.$tab.'&post_type=blnotifier-results' );
        } else {
            return admin_url( 'admin.php?page='.BLNOTIFIER_TEXTDOMAIN ).'&tab='.sanitize_key( $tab );
        }
    } // End get_plugin_page()


    /**
     * Get the full plugin short path
     *
     * @param string $tab
     * @return string
     */
    public function get_plugin_page_short_path( $tab = 'settings' ) {
        if ( !is_null( $tab ) ) {
            $add_tab = '&tab='.sanitize_key( $tab );
        } else {
            $add_tab = '';
        }
        return BLNOTIFIER_TEXTDOMAIN.$add_tab;
    } // End get_plugin_page_short_path()


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
        if ( ( $screen == $options_page && $tab == 'settings' ) ) {
            $handle = 'blnotifier_settings_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'settings.js', [ 'jquery' ], BLNOTIFIER_VERSION, true );
            wp_enqueue_script( $handle );
            wp_enqueue_script( 'jquery' );
        }
    } // End enqueue_scripts()

}