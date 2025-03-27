<?php
/**
 * Main plugin class file.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initialize the class
 */

new BLNOTIFIER_LOADER();


/**
 * Main plugin class.
 */
class BLNOTIFIER_LOADER {

    /**
	 * Constructor
	 */
	public function __construct() {

        // Load dependencies.
        if ( is_admin() ) {
			$this->load_admin_dependencies();
		}
        $this->load_dependencies();
        
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
        if ( BLNOTIFIER_TEXTDOMAIN.'/'.BLNOTIFIER_TEXTDOMAIN.'.php' == $file ) {
            $row_meta = [
                // 'docs' => '<a href="'.esc_url( BLNOTIFIER_AUTHOR_URL.'wordpress-broken-link-notifier/' ).'" target="_blank" aria-label="'.esc_attr__( 'Plugin Website Link', 'broken-link-notifier' ).'">'.esc_html__( 'Website', 'broken-link-notifier' ).'</a>',
                'discord' => '<a href="'.esc_url( BLNOTIFIER_DISCORD_SUPPORT_URL ).'" target="_blank" aria-label="'.esc_attr__( 'Plugin Support on Discord', 'broken-link-notifier' ).'">'.esc_html__( 'Discord Support', 'broken-link-notifier' ).'</a>'
            ];
            return array_merge( $links, $row_meta );
        }
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
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'helpers.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'omits.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'discord.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'msteams.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'results.php';
        require_once BLNOTIFIER_PLUGIN_INCLUDES_PATH.'integrations.php';
        
    } // End load_dependencies()

}