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
            'scan-multi'   => [ __( 'Multi-Scan', 'broken-link-notifier' ) ],
            'settings'    => [ __( 'Settings', 'broken-link-notifier' ) ],
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
        register_setting( $this->page_slug, $has_updated_settings );

        // Enable emailing
        $enable_emailing_option_name = 'blnotifier_enable_emailing';
        register_setting( $this->page_slug, $enable_emailing_option_name, [ $this, 'sanitize_checkbox' ] );
        add_settings_field(
            $enable_emailing_option_name,
            'Enable emailing',
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
            'Emails to send notifications',
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
                'Enable '.$webhook_field[ 'label' ].' notifications',
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

        // Timeout
        $timeout_option_name = 'blnotifier_timeout';
        register_setting( $this->page_slug, $timeout_option_name, 'absint' );
        add_settings_field(
            $timeout_option_name,
            'Timeout (seconds)',
            [ $this, 'field_number' ],
            $this->page_slug,
            'general',
            [
                'class'    => $timeout_option_name,
                'name'     => $timeout_option_name,
                'default'  => 10,
                'min'      => 5,
                'comments' => 'How long to try to connect to a link\'s server before quitting'
            ]
        );

        // Other checkboxes
        $checkboxes = [
            [ 
                'name'     => 'enable_warnings',
                'label'    => 'Enable warnings',
                'default'  => true,
                'comments' => 'Includes warnings in all scans'
            ],
            [ 
                'name'     => 'ssl_verify', 
                'label'    => 'Warn if SSL is not verified',
                'default'  => true,
                'comments' => 'How long to try to connect to a link\'s server before quitting'
            ],
            [ 
                'name'     => 'scan_header', 
                'label'    => 'Scan <code>&#x3c;header&#x3e;</code> elements', 
                'default'  => false,
                'comments' => 'Only applies to page load scans - the header elements usually include the navigation menu(s) at the top of the page'
            ],
            [ 
                'name'     => 'scan_footer', 
                'label'    => 'Scan <code>&#x3c;footer&#x3e;</code> elements', 
                'default'  => false,
                'comments' => 'Only applies to page load scans - the footer elements include any links at the bottom of every page'
            ],
            [ 
                'name'     => 'show_in_console', 
                'label'    => 'Show results in dev console', 
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

        // Post types
        $post_types_option_name = 'blnotifier_post_types';
        register_setting( $this->page_slug, $post_types_option_name, [ $this, 'sanitize_checkboxes' ] );
        add_settings_field(
            $post_types_option_name,
            'Enable Multi-Scan for these post types',
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
            $args[ 'name' ],
            $args[ 'name' ],
            sanitize_text_field( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            $args[ 'comments' ]
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
            $args[ 'name' ],
            $args[ 'name' ],
            filter_var( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ), FILTER_SANITIZE_URL ),
            $args[ 'comments' ]
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
            $args[ 'name' ],
            $args[ 'name' ],
            checked( 1, $value, false ),
            $args[ 'comments' ]
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
        $value = !empty( $value ) ? array_keys( $value ) : $args[ 'default' ];
        if ( isset( $args[ 'options' ] ) ) {
            foreach ( $args[ 'options' ] as $key => $label ) {
                $checked = in_array( $key, $value ) ? 'checked' : '';
                printf(
                    '<input type="checkbox" id="%s" name="%s[%s]" value="1" %s/> <label for="%s">%s</label><br>',
                    $args[ 'name' ].'_'.$key,
                    $args[ 'name' ],
                    $key,
                    $checked,
                    $args[ 'name' ].'_'.$key,
                    $label
                );
            }
        }
    } // field_checkboxes()


    /**
     * Sanitize checkboxes
     *
     * @param array $value
     * @return boolean
     */
    public function sanitize_checkboxes( $value ) {
        return filter_var_array( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkboxes()


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
            $args[ 'name' ],
            $args[ 'name' ],
            esc_html( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            '([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+)(\s*,\s*([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+))*',
            $args[ 'comments' ]
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
            $args[ 'name' ],
            $args[ 'name' ],
            absint( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            $args[ 'min' ],
            $args[ 'comments' ]
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
            $args[ 'name' ],
            $args[ 'name' ],
            esc_html( get_option( $args[ 'name' ], '' ) ),
            $args[ 'comments' ]
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
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'settings.js', [ 'jquery' ], BLNOTIFIER_VERSION );
            wp_enqueue_script( $handle );
            wp_enqueue_script( 'jquery' );
        }
    } // End enqueue_scripts()

}