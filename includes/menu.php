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
     * The menu items
     *
     * @var array
     */
    public $menu_items;


    /**
     * The capability required to access the menu
     *
     * @var string
     */
    public $capability = 'manage_broken_links';


    /**
	 * Constructor
	 */
	public function __construct() {

        // The menu items
        $this->menu_items = [
            'results'      => [ __( 'Results', 'broken-link-notifier' ) ],
            'omit-links'   => [ __( 'Omitted Links', 'broken-link-notifier' ), 'edit-tags.php?taxonomy=omit-links' ],
            'omit-pages'   => [ __( 'Omitted Pages', 'broken-link-notifier' ), 'edit-tags.php?taxonomy=omit-pages' ],
            'scan-single'  => [ __( 'Page Scan', 'broken-link-notifier' ) ],
            'site-scan'    => [ __( 'Site Scan', 'broken-link-notifier' ) ],
            'link-browser' => [ __( 'Link Browser', 'broken-link-notifier' ) ],
            'settings'     => [ __( 'Settings', 'broken-link-notifier' ) ],
        ];

        // Legacy Multi-Scan only shows up if explicitly enabled or in test mode
        if ( apply_filters( 'blnotifier_enable_legacy_multiscan', false ) || (new BLNOTIFIER_HELPERS)->is_test_mode() ) {
            $this->menu_items = array_slice( $this->menu_items, 0, 4, true )
                + [ 'scan-multi' => [ __( 'Multi-Scan', 'broken-link-notifier' ) ] ]
                + array_slice( $this->menu_items, 4, null, true );
        }

    } // End __construct()


    /**
	 * Load on init
	 */
	public function init() {

        // Add the menu
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );

        // Fix the Manage link to show active
        add_filter( 'parent_file', [ $this, 'submenus' ] );

        // Add the header
        add_action( 'in_admin_header', [ $this, 'admin_header' ] );

        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_theme_assets' ] );

	} // End init()


    /**
     * Add page to Tools menu
     * 
     * @return void
     */
    public function admin_menu() {
        // Get the current user's roles
        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;

        $capability = 'do_not_allow';
        $show_menu = false;

        if ( in_array( 'administrator', $user_roles ) ) {
            $capability = 'manage_options';
            $show_menu = true;

        } else {
            $allowed_roles = get_option( 'blnotifier_editable_roles', [] );
            if ( is_array( $allowed_roles ) && !empty( $allowed_roles ) ) {
                foreach ( $allowed_roles as $role_slug => $value ) {
                    $role_slug = sanitize_key( $role_slug );

                    if ( in_array( $role_slug, $user_roles ) ) {
                        $capability = $this->capability;
                        $show_menu = true;
                        break;
                    }
                }
            }
        }

        if ( !$show_menu ) {
            return;
        }

        // Count broken links
        $count = (new BLNOTIFIER_HELPERS)->count_broken_links();
        $notif = $count > 0 ? ' <span class="awaiting-mod">'.(new BLNOTIFIER_HELPERS)->count_broken_links().'</span>' : '';

        // Admin menu title
        $admin_menu_title = apply_filters( 'blnotifier_admin_menu_title', __( 'Broken Links', 'broken-link-notifier' ) );

        // Add the menu
        add_menu_page(
            BLNOTIFIER_NAME,
            $admin_menu_title . $notif,
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
            $submenu_file = 'edit-tags.php?taxonomy=omit-links';
            $parent_file = $this->get_plugin_page_short_path( null );
        } elseif ( $current_screen->id == 'edit-omit-pages' ) {
            $submenu_file = 'edit-tags.php?taxonomy=omit-pages';
            $parent_file = $this->get_plugin_page_short_path( null );
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
     * Get the full plugin page path
     *
     * @param string $tab
     * @return string
     */
    public function get_plugin_page( $tab = 'settings' ) {
        if ( $tab == 'omit-links' || $tab == 'omit-pages' ) {
            return admin_url( 'edit-tags.php?taxonomy='.$tab );
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
     * Render the header above Screen Options and admin notices on our toplevel page
     *
     * @return void
     */
    public function admin_header() {
        $screen = get_current_screen();
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;

        if ( isset( $screen->id ) && $screen->id === $options_page ) {
            include BLNOTIFIER_PLUGIN_INCLUDES_PATH.'header.php';
        }
    } // End admin_header()


    /**
     * Enqueue the shared theme stylesheet
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_theme_assets( $screen ) {
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $current_screen = get_current_screen();
        $is_taxonomy_screen = isset( $current_screen->id ) && ( $current_screen->id === 'edit-omit-links' || $current_screen->id === 'edit-omit-pages' );

        if ( $screen !== $options_page && !$is_taxonomy_screen ) {
            return;
        }

        wp_enqueue_style( 'blnotifier-theme', BLNOTIFIER_PLUGIN_CSS_PATH.'theme.css', [], BLNOTIFIER_SCRIPT_VERSION );

        $colors = apply_filters( 'blnotifier_theme_colors', [] );
        if ( !empty( $colors ) ) {
            $declarations = [];
            foreach ( $colors as $var => $value ) {
                $declarations[] = '--blnotifier-color-'.sanitize_key( $var ).': '.sanitize_text_field( $value ).';';
            }
            wp_add_inline_style( 'blnotifier-theme', ':root {'.implode( '', $declarations ).'}' );
        }
    } // End enqueue_theme_assets()

}