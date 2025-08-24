<?php
/**
 * Main plugin class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Main plugin class.
 */
class BLNOTIFIER_LOADER {

    /**
     * Define your custom capability
     */
    public $capability = 'manage_broken_links';


    /**
     * Define your custom role slug
     */
    public $role_slug = 'blnotifier_link_manager';


    /**
	 * Constructor
	 */
	public function __construct() {

        // Load dependencies.
        if ( is_admin() ) {
			$this->load_admin_dependencies();
		}
        $this->load_dependencies();

        // Add custom capability to the admin
        add_action( 'admin_init', [ $this, 'add_custom_capability_to_admin' ] );
        
	} // End __construct()


    /**
     * Admin-only dependencies
     *
	 * @return void
     */
    public function load_admin_dependencies() {
        
        // Add a settings link to plugins list page
        add_filter( 'plugin_action_links_'.BLNOTIFIER_TEXTDOMAIN.'/'.BLNOTIFIER_TEXTDOMAIN.'.php', [ $this, 'settings_link' ] );

        // Add links to the website and discord
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

        // Requires
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'scan.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'scan-multi.php';

    } // End load_admin_dependencies()


    /**
     * Add a settings link to plugins list page
     *
     * @param array $links
     * @return array
     */
    public function settings_link( $links ) {
        array_unshift(
            $links,
            '<a href="'.(new BLNOTIFIER_MENU)->get_plugin_page().'">' . __( 'Settings', 'broken-link-notifier' ) . '</a>'
        );
        return $links;
    } // End settings_link()


    /**
     * Add link to our website to plugin page
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {
        $text_domain = BLNOTIFIER_TEXTDOMAIN;
        if ( $text_domain . '/' . $text_domain . '.php' == $file ) {

            $guide_url = BLNOTIFIER_GUIDE_URL;
            $docs_url = BLNOTIFIER_DOCS_URL;
            $support_url = BLNOTIFIER_SUPPORT_URL;
            $plugin_name = BLNOTIFIER_NAME;

            $our_links = [
                'guide' => [
                    // translators: Link label for the plugin's user-facing guide.
                    'label' => __( 'How-To Guide', 'broken-link-notifier' ),
                    'url'   => $guide_url
                ],
                'docs' => [
                    // translators: Link label for the plugin's developer documentation.
                    'label' => __( 'Developer Docs', 'broken-link-notifier' ),
                    'url'   => $docs_url
                ],
                'support' => [
                    // translators: Link label for the plugin's support page.
                    'label' => __( 'Support', 'broken-link-notifier' ),
                    'url'   => $support_url
                ],
            ];

            $row_meta = [];
            foreach ( $our_links as $key => $link ) {
                // translators: %1$s is the link label, %2$s is the plugin name.
                $aria_label = sprintf( __( '%1$s for %2$s', 'broken-link-notifier' ), $link[ 'label' ], $plugin_name );
                $row_meta[ $key ] = '<a href="' . esc_url( $link[ 'url' ] ) . '" target="_blank" aria-label="' . esc_attr( $aria_label ) . '">' . esc_html( $link[ 'label' ] ) . '</a>';
            }

            // Add the links
            return array_merge( $links, $row_meta );
        }

        // Return the links
        return (array) $links;
    } // End plugin_row_meta()


    /**
     * Front-end dependencies
     * 
     * @return void
     */
    public function load_dependencies() {

        // Requires
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'menu.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'cache.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'helpers.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'omits.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'discord.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'msteams.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'results.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'integrations.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'export.php';
        
    } // End load_dependencies()


    /**
     * Plugin activation hook callback
     */
    public function plugin_activate() {
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( $this->capability );
        }

        add_role(
            $this->role_slug,
            __( 'Broken Link Manager', 'broken-link-notifier' ),
            [
                'read' => true,
                $this->capability  => true,
            ]
        );
    } // End plugin_activate()


    /**
     * Plugin deactivation hook callback
     */
    public function plugin_deactivate() {
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->remove_cap( $this->capability );
        }

        remove_role( $this->role_slug );
    } // End plugin_deactivate()


    /**
     * Ensures the custom capability exists for administrators on admin_init
     */
    public function add_custom_capability_to_admin() {
        $role = get_role( 'administrator' );
        if ( $role && !$role->has_cap( $this->capability ) ) {
            $role->add_cap( $this->capability );
        }
    } // End add_custom_capability_to_admin()

}